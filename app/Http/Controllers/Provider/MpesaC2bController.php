<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessProviderEvent;
use App\Models\PaymentAccount;
use App\Models\ProviderEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives Safaricom Daraja C2B callbacks. The raw event is stored durably before
 * we acknowledge, then processed asynchronously.
 *
 * Note: Daraja rejects callback URLs containing words like "mpesa", "safaricom", "sql",
 * "query", "exec" or "cmd", so routes use the neutral "/c2b/" path.
 */
class MpesaC2bController extends Controller
{
    public function confirmation(Request $request, string $routeKey): JsonResponse
    {
        $account = PaymentAccount::with('provider')->where('route_key', $routeKey)->first();
        $payload = $request->json()->all() ?: $request->all();

        if (! $account) {
            Log::warning('C2B confirmation for unknown route key', ['ip' => $request->ip()]);

            // Acknowledge so Safaricom doesn't retry indefinitely; nothing is recorded.
            return $this->ack('Success');
        }

        $adapter = $account->provider->adapter();
        $valid = $adapter->validateWebhook($account, $payload, $request->headers->all(), $request->ip());

        try {
            // Own transaction (a savepoint if nested) so a duplicate doesn't abort an outer transaction.
            $event = DB::transaction(fn () => ProviderEvent::create([
                'provider_id' => $account->provider_id,
                'payment_account_id' => $account->id,
                'event_type' => 'c2b.confirmation',
                'idempotency_key' => $adapter->eventKey($payload),
                'payload' => $payload,
                'headers' => collect($request->headers->all())->only(['user-agent', 'content-type', 'x-forwarded-for'])->all(),
                'source_ip' => $request->ip(),
                'processing_status' => $valid ? 'received' : 'rejected',
                'processing_error' => $valid ? null : 'Webhook validation failed (shortcode, payload or source IP).',
            ]));
        } catch (UniqueConstraintViolationException) {
            // Safaricom re-sent a confirmation we already stored.
            return $this->ack('Success');
        }

        if ($valid) {
            ProcessProviderEvent::dispatch($event->id);
        }

        return $this->ack('Success');
    }

    /**
     * Only called if Safaricom has enabled external validation on the shortcode.
     */
    public function validation(Request $request, string $routeKey): JsonResponse
    {
        $account = PaymentAccount::with(['provider', 'merchant'])->where('route_key', $routeKey)->first();
        $payload = $request->json()->all() ?: $request->all();

        if ($account) {
            ProviderEvent::create([
                'provider_id' => $account->provider_id,
                'payment_account_id' => $account->id,
                'event_type' => 'c2b.validation',
                'idempotency_key' => null,
                'payload' => $payload,
                'source_ip' => $request->ip(),
                'processing_status' => 'processed',
                'processed_at' => now(),
            ]);
        }

        if (! $account || $account->status === 'disabled' || ! $account->merchant->isActive()) {
            return response()->json(['ResultCode' => 'C2B00015', 'ResultDesc' => 'Rejected']);
        }

        if (! $account->provider->adapter()->validateWebhook($account, $payload, [], $request->ip())) {
            return response()->json(['ResultCode' => 'C2B00016', 'ResultDesc' => 'Rejected']);
        }

        $min = $account->config('min_amount');
        if ($min && (float) ($payload['TransAmount'] ?? 0) < (float) $min) {
            return response()->json(['ResultCode' => 'C2B00013', 'ResultDesc' => 'Rejected']);
        }

        if ($account->config('require_account_reference') && trim((string) ($payload['BillRefNumber'] ?? '')) === '') {
            return response()->json(['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected']);
        }

        return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
    }

    protected function ack(string $desc): JsonResponse
    {
        return response()->json(['ResultCode' => 0, 'ResultDesc' => $desc]);
    }
}
