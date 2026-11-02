<?php

namespace App\Payments\Mpesa;

use App\Billing\StkTopUpService;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\PaymentAccount;
use App\Models\ProviderEvent;
use App\Models\StkRequest;
use App\Models\Transaction;
use App\Payments\Exceptions\ProviderException;
use App\Payments\PaymentIngestor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * M-Pesa PIN prompts (STK push) a merchant sends from their own PayBill/Till via the API.
 *
 * A successful prompt is recorded through the normal C2B pipeline, keyed by the M-Pesa receipt,
 * so it becomes one transaction even when Safaricom also sends the C2B confirmation.
 */
class MerchantStkService
{
    public const MIN = 1;
    public const MAX = 250000; // M-Pesa per-transaction limit

    public function __construct(protected DarajaClient $daraja, protected PaymentIngestor $ingestor) {}

    public function initiate(PaymentAccount $account, ?ApiKey $key, string $phone, int $amount, ?string $accountReference, ?string $description): StkRequest
    {
        if ($account->status === 'disabled') {
            throw new ProviderException('This payment account is disabled.');
        }
        if (blank($account->credentials?->secret('passkey'))) {
            throw new ProviderException('This account has no Lipa na M-Pesa Online passkey. Add it under Payment accounts → Update Daraja credentials.');
        }
        $phone = StkTopUpService::normalizePhone($phone) ?? throw new ProviderException('Enter a valid Safaricom number, e.g. 254712345678.');
        if ($amount < self::MIN || $amount > self::MAX) {
            throw new ProviderException('Amount must be between KES '.self::MIN.' and KES '.number_format(self::MAX).'.');
        }

        // Daraja needs an AccountReference; Till payments ignore it.
        $reference = trim((string) $accountReference);
        if ($reference === '') {
            $account->account_type === 'till' || throw new ProviderException('account_reference is required for PayBill accounts.');
            $reference = 'Payment';
        }

        $stk = StkRequest::create([
            'merchant_id' => $account->merchant_id,
            'payment_account_id' => $account->id,
            'api_key_id' => $key?->id,
            'purpose' => StkRequest::PURPOSE_COLLECTION,
            'phone' => $phone,
            'amount' => $amount,
            'account_reference' => $reference,
            'description' => trim((string) $description) ?: 'Payment',
        ]);

        try {
            $res = $this->daraja->stkPush($account, $phone, $amount, $reference, $stk->description, $stk->callbackUrl());
        } catch (Throwable $e) {
            $stk->update(['status' => 'failed', 'result_desc' => $e->getMessage(), 'completed_at' => now()]);

            throw $e instanceof ProviderException ? $e : new ProviderException('M-Pesa could not send the prompt. Please try again.');
        }

        $stk->update(['merchant_request_id' => $res['MerchantRequestID'] ?? null, 'checkout_request_id' => $res['CheckoutRequestID'] ?? null]);
        AuditLog::record('stk.sent', $stk, ['amount' => $amount, 'phone' => $stk->maskedPhone(), 'api_key_id' => $key?->id], $account->merchant_id);

        return $stk;
    }

    /** Safaricom's STK result callback for a collection prompt. */
    public function handleCallback(StkRequest $stk, array $payload): StkRequest
    {
        $cb = data_get($payload, 'Body.stkCallback');

        if (! $cb || ($stk->checkout_request_id && $stk->checkout_request_id !== ($cb['CheckoutRequestID'] ?? null))) {
            return $stk;
        }

        $meta = collect(data_get($cb, 'CallbackMetadata.Item', []))->mapWithKeys(fn ($i) => [$i['Name'] => $i['Value'] ?? null]);
        $stk->update(['callback_payload' => $payload]);

        return $this->complete($stk, (string) ($cb['ResultCode'] ?? ''), (string) ($cb['ResultDesc'] ?? ''), $meta->all());
    }

    /** If no callback has arrived yet, ask Safaricom. Called when the merchant polls the prompt. */
    public function refresh(StkRequest $stk): StkRequest
    {
        // Paid, but the C2B confirmation was still being processed when the callback came in.
        if ($stk->status === 'succeeded' && $stk->mpesa_receipt && ! $stk->transaction_id) {
            $this->linkTransaction($stk);

            return $stk->fresh();
        }

        if (! $stk->isPending() || ! $stk->checkout_request_id || $stk->created_at->gt(now()->subSeconds(20))) {
            return $stk;
        }

        try {
            $res = $this->daraja->stkQuery($stk->paymentAccount, $stk->checkout_request_id);
        } catch (Throwable) {
            return $stk;
        }

        if (isset($res['ResultCode'])) {
            return $this->complete($stk, (string) $res['ResultCode'], (string) ($res['ResultDesc'] ?? ''), []);
        }

        if ($stk->created_at->lt(now()->subMinutes(3))) {
            $stk->update(['status' => 'timeout', 'result_desc' => 'No response from M-Pesa.', 'completed_at' => now()]);
        }

        return $stk->fresh();
    }

    /**
     * $meta is the callback metadata (Amount, MpesaReceiptNumber, TransactionDate, PhoneNumber).
     * A status query has no receipt, so the transaction is linked when the callback arrives.
     */
    protected function complete(StkRequest $stk, string $code, string $desc, array $meta): StkRequest
    {
        $stk = DB::transaction(function () use ($stk, $code, $desc, $meta) {
            $stk = StkRequest::query()->lockForUpdate()->find($stk->id);
            $receipt = $meta['MpesaReceiptNumber'] ?? null;

            // Settled already, unless this is the callback bringing the receipt after a status query.
            if (! $stk->isPending() && ! ($stk->status === 'succeeded' && ! $stk->mpesa_receipt && $receipt)) {
                return $stk;
            }

            $stk->update(match (true) {
                $code === '0' => ['status' => 'succeeded', 'result_code' => '0', 'result_desc' => $desc, 'mpesa_receipt' => $receipt, 'completed_at' => $stk->completed_at ?? now()],
                default => ['status' => $code === '1032' ? 'cancelled' : 'failed', 'result_code' => $code, 'result_desc' => $desc, 'completed_at' => now()],
            });

            return $stk;
        });

        if ($stk->status === 'succeeded' && $stk->mpesa_receipt && ! $stk->transaction_id) {
            $this->recordPayment($stk, $meta);
        }

        return $stk->fresh();
    }

    /** Store the payment as a C2B confirmation event (shared receipt key) and ingest it. */
    protected function recordPayment(StkRequest $stk, array $meta): void
    {
        $account = $stk->paymentAccount()->with('provider')->first();
        $till = $account->account_type === 'till';

        $payload = [
            'TransactionType' => $till ? 'Customer Merchant Payment' : 'Customer PayBill Online',
            'TransID' => (string) $stk->mpesa_receipt,
            'TransTime' => (string) ($meta['TransactionDate'] ?? now('Africa/Nairobi')->format('YmdHis')),
            'TransAmount' => (string) ($meta['Amount'] ?? $stk->amount),
            'BusinessShortCode' => $account->external_account_reference,
            'BillRefNumber' => $till ? '' : $stk->account_reference,
            'MSISDN' => (string) ($meta['PhoneNumber'] ?? $stk->phone),
            'ThirdPartyTransID' => $stk->checkout_request_id,
        ];

        try {
            $event = DB::transaction(fn () => ProviderEvent::create([
                'provider_id' => $account->provider_id,
                'payment_account_id' => $account->id,
                'event_type' => 'c2b.confirmation',
                'idempotency_key' => $stk->mpesa_receipt,
                'payload' => $payload,
                'processing_status' => 'received',
            ]));
            $this->ingestor->process($event);
        } catch (UniqueConstraintViolationException) {
            // Safaricom's C2B confirmation for this receipt got here first; linkTransaction records it.
        }

        $this->linkTransaction($stk);
    }

    /**
     * Point the prompt at its transaction. If the C2B confirmation is stored but still waiting
     * in the queue, record it now so the payment shows up immediately.
     */
    protected function linkTransaction(StkRequest $stk): void
    {
        $find = fn () => Transaction::query()
            ->where('payment_account_id', $stk->payment_account_id)
            ->where('provider_transaction_id', $stk->mpesa_receipt)
            ->where('type', Transaction::TYPE_PAYMENT)
            ->first();

        $txn = $find();

        if (! $txn) {
            $event = ProviderEvent::query()
                ->where('payment_account_id', $stk->payment_account_id)
                ->where('event_type', 'c2b.confirmation')
                ->where('idempotency_key', $stk->mpesa_receipt)
                ->whereIn('processing_status', ['received', 'failed'])
                ->first();

            if ($event) {
                try {
                    $this->ingestor->process($event);
                } catch (Throwable) {
                    // Left for the queued job to retry.
                }
                $txn = $find();
            }
        }

        $txn && $stk->update(['transaction_id' => $txn->id]);
    }

    /** Plain-language result for the merchant. */
    public static function message(StkRequest $stk): string
    {
        return match (true) {
            $stk->status === 'succeeded' => 'KES '.number_format($stk->amount).' received from '.$stk->maskedPhone().($stk->mpesa_receipt ? ' · receipt '.$stk->mpesa_receipt : '').'.',
            $stk->status === 'cancelled' => 'The customer cancelled the prompt.',
            $stk->status === 'timeout' || $stk->result_code === '1037' => 'The customer’s phone didn’t respond in time. Check it is on and has signal, then try again.',
            $stk->result_code === '2001' => 'The customer entered the wrong M-Pesa PIN.',
            $stk->result_code === '1' => 'The customer doesn’t have enough M-Pesa balance.',
            $stk->status === 'failed' => $stk->result_desc ?: 'The payment did not go through.',
            default => 'Waiting for the customer to enter their PIN on '.$stk->maskedPhone().'…',
        };
    }
}
