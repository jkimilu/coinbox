<?php

namespace App\Payments\Webhooks;

use App\Jobs\DeliverCallback;
use App\Models\AccountRoute;
use App\Models\CallbackDelivery;
use App\Models\CallbackEndpoint;
use App\Models\Transaction;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    /** Create a delivery for every endpoint that wants this event; jobs run after the DB commit. */
    public function dispatch(Transaction $txn, string $event): void
    {
        $endpoints = $this->endpointsFor($txn);

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
     * Where a payment's notifications go, most specific first:
     *  1. the webhook of the account number it was paid to (e.g. PayBill 4072129, account "100"),
     *  2. the webhook chosen for the PayBill/Till,
     *  3. the merchant's general webhooks, i.e. those not dedicated to an account number.
     */
    public function endpointsFor(Transaction $txn)
    {
        $route = $txn->accountRoute;
        if ($route?->callback_endpoint_id) {
            return CallbackEndpoint::query()->whereKey($route->callback_endpoint_id)->get();
        }

        $account = $txn->paymentAccount;
        if ($account->callback_endpoint_id) {
            return CallbackEndpoint::query()->whereKey($account->callback_endpoint_id)->get();
        }

        $dedicated = AccountRoute::query()->where('merchant_id', $txn->merchant_id)
            ->whereNotNull('callback_endpoint_id')->pluck('callback_endpoint_id');

        return CallbackEndpoint::query()->where('merchant_id', $txn->merchant_id)
            ->whereNotIn('id', $dedicated)->get();
    }

    /**
     * Signature header value: t=<unix>,v1=<hex hmac_sha256(secret, "<t>.<body>")>
     */
    public static function sign(string $secret, string $body, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
