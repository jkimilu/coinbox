<?php

namespace App\Payments\Unintegrated;

use App\Models\PaymentAccount;
use App\Payments\Contracts\PaymentProviderAdapter;
use App\Payments\Data\NormalizedPayment;
use App\Payments\Data\ProviderTransactionResult;
use App\Payments\Exceptions\ProviderException;

/**
 * Placeholder for a provider that has been registered (e.g. a bank) but has no integration yet.
 * It advertises no capabilities and refuses every notification.
 */
class UnintegratedAdapter implements PaymentProviderAdapter
{
    public function capabilities(): array
    {
        return [];
    }

    public function validateWebhook(PaymentAccount $account, array $payload, array $headers, ?string $sourceIp = null): bool
    {
        return false;
    }

    public function normalizeTransaction(PaymentAccount $account, array $payload): NormalizedPayment
    {
        throw new ProviderException('This provider is not integrated yet.');
    }

    public function verifyTransaction(PaymentAccount $account, string $providerTransactionId): ProviderTransactionResult
    {
        return ProviderTransactionResult::unsupported('This provider is not integrated yet.');
    }

    public function eventKey(array $payload): ?string
    {
        return null;
    }
}
