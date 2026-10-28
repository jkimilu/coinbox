<?php

namespace App\Billing;

use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\FloatEntry;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Notifications\InvoiceIssued;
use App\Notifications\LowFloat;
use App\Support\Billing;
use App\Support\Pricing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Monthly fee invoices and the merchant's prepaid float.
 *
 * CoinBox never holds merchants' customer payments. The float is money the merchant
 * deliberately prepays to CoinBox (via PayBill) so fee invoices settle automatically.
 */
class BillingService
{
    public const TZ = 'Africa/Nairobi';

    /** Successful payment volume for a Nairobi calendar range. */
    public function volume(Merchant $merchant, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = Transaction::query()
            ->where('merchant_id', $merchant->id)
            ->where('type', Transaction::TYPE_PAYMENT)
            ->where('status', Transaction::STATUS_SUCCEEDED)
            ->whereBetween('paid_at', [$from->utc(), $to->utc()])
            ->toBase()
            ->selectRaw('coalesce(sum(amount),0)::text as total, count(*) as c')
            ->first();

        return ['amount' => (float) $row->total, 'count' => (int) $row->c];
    }

    /** Issue invoices for one month (default: last month). Safe to run twice. */
    public function generateForMonth(?CarbonImmutable $month = null): int
    {
        $start = ($month ?? CarbonImmutable::now(self::TZ)->subMonthNoOverflow())->setTimezone(self::TZ)->startOfMonth();
        $end = $start->endOfMonth();
        $issued = 0;

        Merchant::query()->where('status', 'active')->each(function (Merchant $merchant) use ($start, $end, &$issued) {
            if ($merchant->invoices()->whereDate('period_start', $start->toDateString())->exists()) {
                return;
            }

            $vol = $this->volume($merchant, $start, $end);
            $fee = Pricing::feeFor($vol['amount']);
            if ($fee <= 0) {
                return; // under the free allowance: nothing to bill
            }

            $invoice = DB::transaction(function () use ($merchant, $start, $end, $vol, $fee) {
                $invoice = BillingInvoice::create([
                    'merchant_id' => $merchant->id,
                    'number' => $this->nextNumber($merchant),
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'processed_volume' => $vol['amount'],
                    'payment_count' => $vol['count'],
                    'fee_percent' => Pricing::feePercent(),
                    'free_threshold' => Pricing::freeThreshold(),
                    'amount' => $fee,
                    'status' => 'open',
                    'issued_at' => now(),
                    'due_at' => now(self::TZ)->addDays(Billing::dueDays())->toDateString(),
                ]);

                $this->settleFromFloat($invoice);

                return $invoice->fresh();
            });

            AuditLog::record('billing.invoice_issued', $invoice, ['amount' => $fee], $merchant->id);
            Notification::send($this->recipients($merchant), new InvoiceIssued($invoice));
            $issued++;
        });

        return $issued;
    }

    /** Pay as much of the invoice as the float allows. */
    public function settleFromFloat(BillingInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            Merchant::query()->whereKey($invoice->merchant_id)->lockForUpdate()->first();
            $invoice->refresh();

            $due = $invoice->outstanding();
            $balance = $invoice->merchant->floatBalance();
            $take = round(min($due, $balance), 2);

            if ($invoice->status !== 'open' || $take <= 0) {
                return;
            }

            FloatEntry::create([
                'merchant_id' => $invoice->merchant_id,
                'type' => 'invoice_charge',
                'amount' => -$take,
                'billing_invoice_id' => $invoice->id,
                'description' => "Payment for {$invoice->number} ({$invoice->periodLabel()})",
            ]);

            $paid = round((float) $invoice->amount_paid + $take, 2);
            $invoice->update([
                'amount_paid' => $paid,
                'status' => $paid >= (float) $invoice->amount ? 'paid' : 'open',
                'paid_at' => $paid >= (float) $invoice->amount ? now() : null,
            ]);
        });
    }

    /**
     * Credit a payment from the merchant to their float, then settle open invoices
     * (the referenced one first, then oldest first). Idempotent per receipt.
     */
    public function recordPayment(Merchant $merchant, float $amount, string $receipt, ?string $invoiceNumber = null, ?Transaction $txn = null, ?int $recordedBy = null): ?FloatEntry
    {
        $receipt = strtoupper(trim($receipt));
        if ($amount <= 0 || FloatEntry::query()->where('type', 'topup')->where('reference', $receipt)->exists()) {
            return null;
        }

        $entry = DB::transaction(function () use ($merchant, $amount, $receipt, $invoiceNumber, $txn, $recordedBy) {
            $entry = FloatEntry::create([
                'merchant_id' => $merchant->id,
                'type' => 'topup',
                'amount' => round($amount, 2),
                'transaction_id' => $txn?->id,
                'reference' => $receipt,
                'description' => $invoiceNumber ? "M-Pesa payment for {$invoiceNumber}" : 'M-Pesa float top-up',
                'recorded_by' => $recordedBy,
            ]);

            $open = $merchant->invoices()->where('status', 'open')
                ->orderByRaw('number = ? desc', [strtoupper((string) $invoiceNumber)])
                ->orderBy('period_start')->get();

            foreach ($open as $invoice) {
                $this->settleFromFloat($invoice);
            }

            return $entry;
        });

        AuditLog::record('billing.payment_recorded', $merchant, ['amount' => $amount, 'receipt' => $receipt, 'invoice' => $invoiceNumber], $merchant->id);

        if ($this->advice($merchant)['top_up'] <= 0) {
            $merchant->forceFill(['low_float_notified_at' => null])->save();
        }

        return $entry;
    }

    /**
     * A payment arrived on CoinBox's own PayBill. Account reference is "234" (top-up)
     * or "234#INV001" (invoice payment). Unrecognised references are left for an admin.
     */
    public function applyPlatformPayment(Transaction $txn): ?FloatEntry
    {
        if (! preg_match('/^\s*(\d+)\s*(?:#\s*([A-Za-z0-9-]+))?\s*$/', (string) $txn->merchant_reference, $m)) {
            return null;
        }

        $merchant = Merchant::query()->where('account_number', (int) $m[1])->first();

        return $merchant
            ? $this->recordPayment($merchant, (float) $txn->amount, $txn->provider_transaction_id, $m[2] ?? null, $txn)
            : null;
    }

    /**
     * Top-up advice: keep enough float for the larger of last month's invoice and this
     * month's projected fee, plus anything still owed.
     */
    public function advice(Merchant $merchant): array
    {
        $now = CarbonImmutable::now(self::TZ);
        $monthStart = $now->startOfMonth();
        $toDate = $this->volume($merchant, $monthStart, $now);
        $elapsed = max(1, $now->day);
        $projectedVolume = $toDate['amount'] / $elapsed * $now->daysInMonth;
        $projectedFee = Pricing::feeFor($projectedVolume);
        $lastInvoice = (float) ($merchant->invoices()->latest('period_start')->value('amount') ?? 0);
        $outstanding = (float) $merchant->invoices()->where('status', 'open')->get()->sum(fn ($i) => $i->outstanding());
        $balance = $merchant->floatBalance();

        $recommended = (float) (ceil(max($projectedFee, $lastInvoice) / 100) * 100);
        $topUp = max(0, ceil(($recommended + $outstanding - $balance) / 10) * 10);

        return [
            'balance' => $balance,
            'outstanding' => $outstanding,
            'month_volume' => $toDate['amount'],
            'month_fee_so_far' => Pricing::feeFor($toDate['amount']),
            'projected_fee' => $projectedFee,
            'recommended_float' => $recommended,
            'top_up' => (float) $topUp,
        ];
    }

    /** Email merchants whose float is short, at most once a week each. */
    public function notifyLowFloat(): int
    {
        $sent = 0;
        Merchant::query()->where('status', 'active')->each(function (Merchant $merchant) use (&$sent) {
            $advice = $this->advice($merchant);
            if ($advice['top_up'] <= 0 || ($merchant->low_float_notified_at && $merchant->low_float_notified_at->gt(now()->subDays(7)))) {
                return;
            }
            Notification::send($this->recipients($merchant), new LowFloat($merchant, $advice));
            $merchant->forceFill(['low_float_notified_at' => now()])->save();
            $sent++;
        });

        return $sent;
    }

    protected function nextNumber(Merchant $merchant): string
    {
        $last = $merchant->invoices()->lockForUpdate()->pluck('number')
            ->map(fn ($n) => (int) preg_replace('/\D/', '', $n))->max() ?? 0;

        return 'INV'.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }

    protected function recipients(Merchant $merchant)
    {
        return $merchant->users()->get();
    }
}
