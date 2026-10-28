<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PaymentAccount;
use App\Payments\CollectionSummary;
use Illuminate\Http\Request;

class AccountController extends ApiController
{
    public function index(Request $request)
    {
        $accounts = PaymentAccount::with('provider')
            ->where('merchant_id', $this->merchant($request)->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (PaymentAccount $a) => $this->present($a));

        return response()->json(['object' => 'list', 'data' => $accounts]);
    }

    public function balance(Request $request, string $id, CollectionSummary $summary)
    {
        $account = PaymentAccount::query()
            ->where('merchant_id', $this->merchant($request)->id)
            ->find($this->stripPrefix($id, 'acct'));

        return $account ? response()->json($summary->forAccount($account)) : $this->notFound('Account');
    }

    protected function present(PaymentAccount $a): array
    {
        return [
            'id' => 'acct_'.$a->id,
            'object' => 'payment_account',
            'provider' => $a->provider->code,
            'account_type' => $a->account_type,
            'display_name' => $a->display_name,
            'external_account_reference' => $a->external_account_reference,
            'currency' => $a->currency,
            'status' => $a->status,
            'urls_registered_at' => $a->urls_registered_at?->toIso8601String(),
            'created_at' => $a->created_at->toIso8601String(),
        ];
    }
}
