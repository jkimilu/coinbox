<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Payments\Mpesa\MpesaReversalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReversalCallbackController extends Controller
{
    public function result(Request $request, string $token, MpesaReversalService $reversals): JsonResponse
    {
        $reversals->handleResult($token, $request->json()->all() ?: $request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function timeout(Request $request, string $token, MpesaReversalService $reversals): JsonResponse
    {
        $reversals->handleTimeout($token, $request->json()->all() ?: $request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
