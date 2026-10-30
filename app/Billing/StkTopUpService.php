<?php

namespace App\Billing;

use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\StkRequest;
use App\Models\User;
use App\Payments\Exceptions\ProviderException;
use App\Payments\Mpesa\DarajaClient;
use App\Support\Billing;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Float top-ups by M-Pesa PIN prompt (STK push) on CoinBox's own PayBill.
 * Account reference is the merchant number ("101") or "101#INV001" for an invoice.
 */
class StkTopUpService
{
    public const PRESETS = [200, 500, 1000, 3000];
    public const MIN = 10;
    public const MAX = 250000; // M-Pesa per-transaction limit

    public function __construct(protected DarajaClient $daraja, protected BillingService $billing) {}

    /** CoinBox's billing account, if it can send prompts (needs a passkey). */
    public function account(): ?PaymentAccount
    {
        $id = Billing::platformAccountId();
        $account = $id ? PaymentAccount::with('credentials')->find($id) : null;

        return $account && filled($account->credentials?->secret('passkey')) ? $account : null;
    }

    /** 0712345678 / 712345678 / +254712345678 / 254112345678 → 254XXXXXXXXX, or null. */
    public static function normalizePhone(?string $input): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $input);
        $digits = match (true) {
            str_starts_with($digits, '254') => $digits,
            str_starts_with($digits, '0') => '254'.substr($digits, 1),
            strlen($digits) === 9 => '254'.$digits,
            default => $digits,
        };

        return preg_match('/^254[17]\d{8}$/', $digits) ? $digits : null;
    }

    public function initiate(Merchant $merchant, ?User $user, string $phone, int $amount, ?string $invoiceNumber = null): StkRequest
    {
        $account = $this->account() ?? throw new ProviderException('M-Pesa prompts are not available right now. Please pay via PayBill instead.');
        $phone = self::normalizePhone($phone) ?? throw new ProviderException('Enter a valid Safaricom number, e.g. 0712 345 678.');
        if ($amount < self::MIN || $amount > self::MAX) {
            throw new ProviderException('Amount must be between KES '.self::MIN.' and KES '.number_format(self::MAX).'.');
        }

        $reference = $merchant->account_number.($invoiceNumber ? '#'.$invoiceNumber : '');

        $stk = StkRequest::create([
            'merchant_id' => $merchant->id,
            'payment_account_id' => $account->id,
            'user_id' => $user?->id,
            'invoice_number' => $invoiceNumber,
            'phone' => $phone,
            'amount' => $amount,
            'account_reference' => $reference,
        ]);

        try {
            $res = $this->daraja->stkPush($account, $phone, $amount, $reference, 'CoinBox float', $stk->callbackUrl());
        } catch (Throwable $e) {
            $stk->update(['status' => 'failed', 'result_desc' => $e->getMessage(), 'completed_at' => now()]);

            throw $e instanceof ProviderException ? $e : new ProviderException('M-Pesa could not send the prompt. Please try again.');
        }

        $stk->update(['merchant_request_id' => $res['MerchantRequestID'] ?? null, 'checkout_request_id' => $res['CheckoutRequestID'] ?? null]);
        AuditLog::record('billing.stk_sent', $stk, ['amount' => $amount, 'phone' => $stk->maskedPhone()], $merchant->id);

        return $stk;
    }

    /** Safaricom's STK result callback. */
    public function handleCallback(string $token, array $payload): ?StkRequest
    {
        $cb = data_get($payload, 'Body.stkCallback');
        $stk = StkRequest::query()->where('callback_token', $token)->first();

        if (! $stk || ! $cb || ($stk->checkout_request_id && $stk->checkout_request_id !== ($cb['CheckoutRequestID'] ?? null))) {
            return null;
        }

        $meta = collect(data_get($cb, 'CallbackMetadata.Item', []))->mapWithKeys(fn ($i) => [$i['Name'] => $i['Value'] ?? null]);
        $stk->update(['callback_payload' => $payload]);

        return $this->complete($stk, (string) ($cb['ResultCode'] ?? ''), (string) ($cb['ResultDesc'] ?? ''), $meta['MpesaReceiptNumber'] ?? null, isset($meta['Amount']) ? (float) $meta['Amount'] : null);
    }

    /** If no callback arrived yet, ask Safaricom. Called while the merchant's screen is waiting. */
    public function refresh(StkRequest $stk): StkRequest
    {
        if (! $stk->isPending() || ! $stk->checkout_request_id || $stk->created_at->gt(now()->subSeconds(20))) {
            return $stk;
        }

        try {
            $res = $this->daraja->stkQuery($stk->paymentAccount, $stk->checkout_request_id);
        } catch (Throwable) {
            return $stk;
        }

        $code = isset($res['ResultCode']) ? (string) $res['ResultCode'] : null;

        if ($code !== null) {
            return $this->complete($stk, $code, (string) ($res['ResultDesc'] ?? ''), null, null);
        }

        // Still processing (Daraja answers with an errorCode). Give up after 3 minutes.
        if ($stk->created_at->lt(now()->subMinutes(3))) {
            $stk->update(['status' => 'timeout', 'result_desc' => 'No response from M-Pesa.', 'completed_at' => now()]);
        }

        return $stk->fresh();
    }

    protected function complete(StkRequest $stk, string $code, string $desc, ?string $receipt, ?float $amount): StkRequest
    {
        return DB::transaction(function () use ($stk, $code, $desc, $receipt, $amount) {
            $stk = StkRequest::query()->lockForUpdate()->find($stk->id);

            if (! $stk->isPending()) {
                return $stk; // already settled (callback + query race)
            }

            if ($code !== '0') {
                $stk->update(['status' => $code === '1032' ? 'cancelled' : 'failed', 'result_code' => $code, 'result_desc' => $desc, 'completed_at' => now()]);

                return $stk;
            }

            $stk->update(['status' => 'succeeded', 'result_code' => '0', 'result_desc' => $desc, 'mpesa_receipt' => $receipt, 'completed_at' => now()]);

            $this->billing->recordPayment(
                $stk->merchant,
                $amount ?? (float) $stk->amount,
                $receipt ?? 'STK-'.$stk->checkout_request_id,
                $stk->invoice_number,
            );

            return $stk;
        });
    }

    /** Plain-language result for the merchant. */
    public static function message(StkRequest $stk): string
    {
        return match (true) {
            $stk->status === 'succeeded' => 'Payment received. KES '.number_format($stk->amount).' added to your float.',
            $stk->status === 'cancelled' => 'The prompt was cancelled on the phone.',
            $stk->status === 'timeout' || $stk->result_code === '1037' => 'The phone didn’t respond in time. Check it is on and has signal, then try again.',
            $stk->result_code === '2001' => 'Wrong M-Pesa PIN entered.',
            $stk->result_code === '1' => 'Not enough M-Pesa balance.',
            $stk->status === 'failed' => $stk->result_desc ?: 'The payment did not go through.',
            default => 'Waiting for you to enter your M-Pesa PIN on '.$stk->maskedPhone().'…',
        };
    }
}
