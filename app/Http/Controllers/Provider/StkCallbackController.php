<?php

namespace App\Http\Controllers\Provider;

use App\Billing\StkTopUpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StkCallbackController extends Controller
{
    public function __invoke(Request $request, string $token, StkTopUpService $stk): JsonResponse
    {
        $stk->handleCallback($token, $request->json()->all() ?: $request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
