<?php

namespace App\Payments;

use App\Models\AccountRoute;
use App\Models\PaymentAccount;

/**
 * Splits the account number a customer typed ("100#INV001") into an account route ("100")
 * and the reference after the separator ("INV001").
 */
class AccountRouter
{
    /**
     * @return array{route: ?AccountRoute, reference: ?string}
     */
    public function match(PaymentAccount $account, ?string $billRef): array
    {
        $raw = trim((string) $billRef);
        if ($raw === '') {
            return ['route' => null, 'reference' => null];
        }

        [$code, $rest] = array_pad(explode(AccountRoute::SEPARATOR, $raw, 2), 2, null);
        $code = strtoupper(trim($code));

        $route = AccountRoute::query()
            ->where('payment_account_id', $account->id)
            ->where('is_active', true)
            ->where('code', $code)
            ->first();

        if (! $route) {
            return ['route' => null, 'reference' => $raw];
        }

        $reference = $rest !== null ? trim($rest) : null;

        return ['route' => $route, 'reference' => $reference !== '' ? $reference : null];
    }

    public function accountHasRoutes(PaymentAccount $account): bool
    {
        return AccountRoute::query()->where('payment_account_id', $account->id)->where('is_active', true)->exists();
    }
}
