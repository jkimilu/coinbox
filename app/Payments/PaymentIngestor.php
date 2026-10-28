<?php

namespace App\Payments;

use App\Models\ProviderEvent;
use App\Models\Transaction;
use App\Payments\Webhooks\WebhookDispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns a stored provider event into a unified transaction exactly once.
 */
class PaymentIngestor
{
    public function __construct(protected WebhookDispatcher $webhooks) {}

    public function process(ProviderEvent $event): ?Transaction
    {
        if (in_array($event->processing_status, ['processed', 'duplicate', 'rejected'], true)) {
            return null;
        }

        $account = $event->paymentAccount;
        if (! $account) {
            $event->update(['processing_status' => 'rejected', 'processing_error' => 'Unknown payment account.', 'processed_at' => now()]);

            return null;
        }

        try {
            $adapter = $account->provider->adapter();
            $payment = $adapter->normalizeTransaction($account, $event->payload);

            return DB::transaction(function () use ($event, $account, $payment) {
                $existing = Transaction::query()
                    ->where('provider_id', $account->provider_id)
                    ->where('provider_transaction_id', $payment->providerTransactionId)
                    ->where('type', Transaction::TYPE_PAYMENT)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $event->update(['processing_status' => 'duplicate', 'processed_at' => now()]);

                    return null;
                }

                $txn = Transaction::create([
                    'merchant_id' => $account->merchant_id,
                    'payment_account_id' => $account->id,
                    'provider_id' => $account->provider_id,
                    'provider_event_id' => $event->id,
                    'type' => Transaction::TYPE_PAYMENT,
                    'provider_code' => $account->provider->code,
                    'payment_method' => $payment->paymentMethod,
                    'provider_transaction_id' => $payment->providerTransactionId,
                    'merchant_reference' => $payment->merchantReference,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'payer_identifier' => $payment->payerIdentifier,
                    'payer_name' => $payment->payerName,
                    'metadata' => $payment->metadata,
                    'paid_at' => $payment->paidAt,
                ]);

                $event->update(['processing_status' => 'processed', 'processed_at' => now(), 'processing_error' => null]);

                if ($account->status === 'pending') {
                    $account->update(['status' => 'active']);
                }

                $this->webhooks->dispatch($txn, 'transaction.'.$txn->status);

                return $txn;
            });
        } catch (UniqueConstraintViolationException) {
            $event->update(['processing_status' => 'duplicate', 'processed_at' => now()]);

            return null;
        } catch (Throwable $e) {
            $event->update(['processing_status' => 'failed', 'processing_error' => $e->getMessage()]);

            throw $e;
        }
    }
}
