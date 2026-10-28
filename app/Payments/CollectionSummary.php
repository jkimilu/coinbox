<?php

namespace App\Payments;

use App\Models\PaymentAccount;
use App\Models\ReconciliationRun;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Totals calculated from recorded transactions. This is NOT the Safaricom account balance:
 * funds settle directly into the merchant's own PayBill/Till and the gateway never holds them.
 */
class CollectionSummary
{
    public function forAccount(PaymentAccount $account): array
    {
        $base = Transaction::query()->where('payment_account_id', $account->id);
        $today = now('Africa/Nairobi')->startOfDay()->utc(); // timestamps are stored in UTC

        $lastPayment = (clone $base)->where('type', Transaction::TYPE_PAYMENT)->latest('paid_at')->first();
        $lastRecon = ReconciliationRun::query()->where('payment_account_id', $account->id)
            ->where('status', 'completed')->latest('completed_at')->first();

        return [
            'object' => 'collection_summary',
            'payment_account_id' => 'acct_'.$account->id,
            'currency' => $account->currency,
            'disclaimer' => 'Calculated from transactions recorded by the gateway. This is not your M-Pesa account balance and is not withdrawable funds.',
            'all_time' => $this->totals(clone $base),
            'today' => $this->totals((clone $base)->where('paid_at', '>=', $today)),
            'counts_by_status' => (clone $base)->where('type', Transaction::TYPE_PAYMENT)
                ->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status'),
            'last_payment_at' => $lastPayment?->paid_at?->toIso8601String(),
            'last_reconciled_at' => $lastRecon?->completed_at?->toIso8601String(),
        ];
    }

    public function forPeriod(Builder $query, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->totals($query->whereBetween('paid_at', [$from, $to]));
    }

    /** Sums are computed in PostgreSQL numeric to avoid float rounding. */
    public function totals(Builder $query): array
    {
        $row = $query->toBase()->selectRaw("
            coalesce(sum(amount) filter (where type = 'payment' and status in ('succeeded','reversed')), 0)::text as received,
            count(*) filter (where type = 'payment' and status in ('succeeded','reversed')) as received_count,
            coalesce(sum(amount) filter (where type = 'reversal'), 0)::text as reversed,
            count(*) filter (where type = 'reversal') as reversed_count,
            (coalesce(sum(amount) filter (where type = 'payment' and status in ('succeeded','reversed')), 0)
              - coalesce(sum(amount) filter (where type = 'reversal'), 0))::text as net,
            count(*) filter (where status = 'pending') as pending_count
        ")->first();

        return [
            'successful_payments' => ['amount' => $row->received, 'count' => (int) $row->received_count],
            'reversals' => ['amount' => $row->reversed, 'count' => (int) $row->reversed_count],
            'net_recorded_collections' => $row->net,
            'pending_count' => (int) $row->pending_count,
        ];
    }
}
