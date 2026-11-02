<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PaymentAccount;
use App\Models\StkRequest;
use App\Payments\Exceptions\ProviderException;
use App\Payments\Mpesa\MerchantStkService;
use Illuminate\Http\Request;

/**
 * Send an M-Pesa PIN prompt to a customer's phone, paying into one of the merchant's shortcodes.
 */
class StkController extends ApiController
{
    public function store(Request $request, MerchantStkService $stk)
    {
        $data = $request->validate([
            'account_id' => 'required_without:short_code|nullable|string',
            'short_code' => 'required_without:account_id|nullable|string|max:10',
            'phone' => 'required|string|max:20',
            'amount' => 'required|integer|min:'.MerchantStkService::MIN.'|max:'.MerchantStkService::MAX,
            'account_reference' => 'nullable|string|max:12',
            'description' => 'nullable|string|max:13',
        ]);

        $merchant = $this->merchant($request);
        $account = PaymentAccount::query()->with('credentials')
            ->where('merchant_id', $merchant->id)
            ->when($data['account_id'] ?? null,
                fn ($q, $v) => $q->whereKey($this->stripPrefix($v, 'acct')),
                // Accept the PayBill/store number, or a till's customer-facing number.
                fn ($q) => $q->where(fn ($q) => $q->where('external_account_reference', $data['short_code'])
                    ->orWhere('configuration->till_number', $data['short_code'])))
            ->first();

        if (! $account) {
            return $this->notFound('Payment account');
        }

        try {
            $prompt = $stk->initiate($account, $request->attributes->get('api_key'), $data['phone'], (int) $data['amount'], $data['account_reference'] ?? null, $data['description'] ?? null);
        } catch (ProviderException $e) {
            return response()->json(['error' => ['code' => 'stk_failed', 'message' => $e->getMessage()]], 422);
        }

        return response()->json($prompt->load('paymentAccount')->toApiArray(), 202);
    }

    public function show(Request $request, string $id, MerchantStkService $stk)
    {
        $found = StkRequest::query()
            ->where('merchant_id', $this->merchant($request)->id)
            ->where('purpose', StkRequest::PURPOSE_COLLECTION)
            ->find($this->stripPrefix($id, 'stk'));

        if (! $found) {
            return $this->notFound('STK request');
        }

        return response()->json($stk->refresh($found)->load(['paymentAccount', 'transaction'])->toApiArray());
    }
}
