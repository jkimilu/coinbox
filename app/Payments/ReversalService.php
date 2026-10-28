<?php

namespace App\Payments;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Payments\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records a reversal that already happened at the provider (e.g. an M-Pesa reversal done by
 * the merchant or Safaricom). The original row stays intact; a new "reversal" row is written.
 */
class ReversalService
{
    public function __construct(protected WebhookDispatcher $webhooks) {}

    public function record(Transaction $original, string $reversalReceipt, ?string $reason = null): Transaction
    {
        if ($original->type !== Transaction::TYPE_PAYMENT || $original->status !== Transaction::STATUS_SUCCEEDED) {
            throw new InvalidArgumentException('Only succeeded payments can be reversed.');
        }

        return DB::transaction(function () use ($original, $reversalReceipt, $reason) {
            $original = Transaction::query()->lockForUpdate()->findOrFail($original->id);

            $reversal = Transaction::create([
                'merchant_id' => $original->merchant_id,
                'payment_account_id' => $original->payment_account_id,
                'provider_id' => $original->provider_id,
                'type' => Transaction::TYPE_REVERSAL,
                'reverses_transaction_id' => $original->id,
                'provider_code' => $original->provider_code,
                'payment_method' => $original->payment_method,
                'provider_transaction_id' => $reversalReceipt,
                'merchant_reference' => $original->merchant_reference,
                'amount' => $original->amount,
                'currency' => $original->currency,
                'status' => Transaction::STATUS_SUCCEEDED,
                'payer_identifier' => $original->payer_identifier,
                'payer_name' => $original->payer_name,
                'metadata' => array_filter(['reason' => $reason]),
                'paid_at' => now(),
            ]);

            $original->update(['status' => Transaction::STATUS_REVERSED]);

            AuditLog::record('transaction.reversal_recorded', $original, ['reversal_id' => $reversal->id, 'reason' => $reason], $original->merchant_id);

            $this->webhooks->dispatch($original->fresh(), 'transaction.reversed');

            return $reversal;
        });
    }
}
