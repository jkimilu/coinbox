<?php

namespace App\Payments\Mpesa;

use App\Models\AuditLog;
use App\Models\PaymentProvider;
use App\Models\ReversalRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Payments\Exceptions\ProviderException;
use App\Payments\ReversalService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reverses an M-Pesa payment through Safaricom so the money goes back to the customer.
 * Safaricom answers asynchronously; only a successful result marks the payment as reversed.
 */
class MpesaReversalService
{
    public function __construct(protected DarajaClient $daraja, protected ReversalService $reversals) {}

    /** Why this payment can't be reversed through Safaricom, or null if it can. */
    public function blocker(Transaction $txn): ?string
    {
        return match (true) {
            $txn->type !== Transaction::TYPE_PAYMENT => 'Only payments can be reversed.',
            $txn->status !== Transaction::STATUS_SUCCEEDED => 'Only succeeded payments can be reversed.',
            $txn->provider_code !== PaymentProvider::MPESA => 'Only M-Pesa payments can be reversed here.',
            $txn->reversalRequests()->where('status', 'pending')->exists() => 'A reversal is already waiting for Safaricom.',
            default => null,
        };
    }

    public function request(Transaction $txn, ?User $user, string $reason): ReversalRequest
    {
        if ($blocker = $this->blocker($txn)) {
            throw new ProviderException($blocker);
        }

        $account = $txn->paymentAccount()->with('credentials')->firstOrFail();

        $request = ReversalRequest::create([
            'merchant_id' => $txn->merchant_id,
            'transaction_id' => $txn->id,
            'user_id' => $user?->id,
            'amount' => $txn->amount,
            'reason' => $reason,
        ]);

        try {
            $res = $this->daraja->reverse($account, $txn->provider_transaction_id, (string) $txn->amount, $reason, $request->resultUrl(), $request->timeoutUrl());
        } catch (Throwable $e) {
            $request->update(['status' => 'failed', 'result_desc' => $e->getMessage(), 'completed_at' => now()]);
            AuditLog::record('transaction.reversal_rejected', $txn, ['error' => $e->getMessage()], $txn->merchant_id);

            throw $e instanceof ProviderException ? $e : new ProviderException('Could not reach Safaricom. Please try again.');
        }

        $request->update([
            'conversation_id' => $res['ConversationID'] ?? null,
            'originator_conversation_id' => $res['OriginatorConversationID'] ?? null,
        ]);
        AuditLog::record('transaction.reversal_requested', $txn, ['reversal_request_id' => $request->id, 'reason' => $reason], $txn->merchant_id);

        return $request;
    }

    /** Safaricom's result callback. */
    public function handleResult(string $token, array $payload): ?ReversalRequest
    {
        $result = data_get($payload, 'Result');
        $request = ReversalRequest::query()->where('callback_token', $token)->first();

        if (! $request || ! is_array($result)
            || ($request->conversation_id && isset($result['ConversationID']) && $request->conversation_id !== $result['ConversationID'])) {
            return null;
        }

        return DB::transaction(function () use ($request, $result, $payload) {
            $request = ReversalRequest::query()->lockForUpdate()->find($request->id);

            if (! $request->isPending()) {
                return $request; // duplicate callback
            }

            $code = (string) ($result['ResultCode'] ?? '');
            $receipt = $result['TransactionID'] ?? null;

            $request->update([
                'status' => $code === '0' ? 'succeeded' : 'failed',
                'result_code' => $code,
                'result_desc' => $result['ResultDesc'] ?? null,
                'reversal_receipt' => $receipt,
                'result_payload' => $payload,
                'completed_at' => now(),
            ]);

            $txn = $request->transaction;

            if ($code !== '0') {
                AuditLog::record('transaction.reversal_failed', $txn, ['code' => $code, 'desc' => $result['ResultDesc'] ?? null], $request->merchant_id);

                return $request;
            }

            // Someone may already have recorded it by hand.
            if ($txn->status === Transaction::STATUS_SUCCEEDED) {
                $this->reversals->record($txn, (string) ($receipt ?: 'REV-'.$request->id), $request->reason);
            }

            return $request;
        });
    }

    /** Safaricom's queue timed out before processing the request. */
    public function handleTimeout(string $token, array $payload): ?ReversalRequest
    {
        $request = ReversalRequest::query()->where('callback_token', $token)->where('status', 'pending')->first();

        $request?->update([
            'status' => 'timeout',
            'result_desc' => 'Safaricom timed out before processing the reversal. Check the M-Pesa portal before trying again.',
            'result_payload' => $payload,
            'completed_at' => now(),
        ]);

        return $request;
    }
}
