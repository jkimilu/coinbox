<?php

namespace App\Payments\Webhooks;

use App\Jobs\DeliverCallback;
use App\Models\CallbackDelivery;
use App\Models\CallbackEndpoint;
use App\Models\Transaction;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    /** Create a delivery for every endpoint that wants this event; jobs run after the DB commit. */
    public function dispatch(Transaction $txn, string $event): void
    {
        $account = $txn->paymentAccount;

        // An account-specific endpoint takes precedence; otherwise use all of the merchant's endpoints.
        $endpoints = $account->callback_endpoint_id
            ? CallbackEndpoint::query()->whereKey($account->callback_endpoint_id)->get()
            : CallbackEndpoint::query()->where('merchant_id', $txn->merchant_id)->get();

        foreach ($endpoints->filter(fn (CallbackEndpoint $e) => $e->wants($event)) as $endpoint) {
            $delivery = CallbackDelivery::create([
                'callback_endpoint_id' => $endpoint->id,
                'transaction_id' => $txn->id,
                'event' => $event,
                'payload' => [
                    'id' => 'evt_'.Str::ulid(),
                    'type' => $event,
                    'created_at' => now()->toIso8601String(),
                    'data' => $txn->toApiArray(),
                ],
                'status' => 'pending',
                'next_attempt_at' => now(),
            ]);

            DeliverCallback::dispatch($delivery->id)->afterCommit();
        }
    }

    /**
     * Signature header value: t=<unix>,v1=<hex hmac_sha256(secret, "<t>.<body>")>
     */
    public static function sign(string $secret, string $body, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
