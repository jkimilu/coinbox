<?php

namespace App\Http\Controllers\Provider;

use App\Billing\StkTopUpService;
use App\Http\Controllers\Controller;
use App\Models\StkRequest;
use App\Payments\Mpesa\MerchantStkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StkCallbackController extends Controller
{
    public function __invoke(Request $request, string $token, StkTopUpService $billing, MerchantStkService $collections): JsonResponse
    {
        $payload = $request->json()->all() ?: $request->all();
        $stk = StkRequest::query()->where('callback_token', $token)->first();

        // Merchant prompts sent through the API; everything else is a CoinBox float top-up.
        if ($stk?->purpose === StkRequest::PURPOSE_COLLECTION) {
            $collections->handleCallback($stk, $payload);
        } else {
            $billing->handleCallback($token, $payload);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
