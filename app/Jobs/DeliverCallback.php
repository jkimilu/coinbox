<?php

namespace App\Jobs;

use App\Models\CallbackDelivery;
use App\Payments\Webhooks\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Delivers one signed webhook to a merchant. Retries with backoff; every attempt is recorded.
 */
class DeliverCallback implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // retries are scheduled manually so attempts survive worker restarts

    public function __construct(public string $deliveryId) {}

    public function handle(): void
    {
        $delivery = CallbackDelivery::with('endpoint')->find($this->deliveryId);

        if (! $delivery || $delivery->status === 'delivered' || ! $delivery->endpoint?->is_active) {
            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES);
        $timestamp = now()->timestamp;
        $attempt = $delivery->attempts + 1;

        $update = ['attempts' => $attempt];

        try {
            $response = Http::timeout(config('coinbox.webhooks.timeout'))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'CoinBox-Webhooks/1.0',
                    'X-CoinBox-Event' => $delivery->event,
                    'X-CoinBox-Delivery' => $delivery->id,
                    'X-CoinBox-Signature' => WebhookDispatcher::sign($delivery->endpoint->secret, $body, $timestamp),
                ])
                ->withBody($body, 'application/json')
                ->post($delivery->endpoint->url);

            $update['last_response_code'] = $response->status();
            $update['last_response_body'] = Str::limit($response->body(), 2000);
            $update['last_error'] = null;

            if ($response->successful()) {
                $delivery->update($update + ['status' => 'delivered', 'delivered_at' => now(), 'next_attempt_at' => null]);

                return;
            }
        } catch (Throwable $e) {
            $update['last_error'] = Str::limit($e->getMessage(), 2000);
        }

        $this->scheduleRetry($delivery, $update, $attempt);
    }

    protected function scheduleRetry(CallbackDelivery $delivery, array $update, int $attempt): void
    {
        if ($attempt >= config('coinbox.webhooks.max_attempts')) {
            $delivery->update($update + ['status' => 'failed', 'next_attempt_at' => null]);

            return;
        }

        $backoff = config('coinbox.webhooks.backoff');
        $delay = $backoff[min($attempt - 1, count($backoff) - 1)];

        $delivery->update($update + ['status' => 'pending', 'next_attempt_at' => now()->addSeconds($delay)]);

        self::dispatch($delivery->id)->delay($delay);
    }
}
