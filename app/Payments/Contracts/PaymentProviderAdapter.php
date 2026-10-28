<?php

namespace App\Payments\Contracts;

use App\Models\PaymentAccount;
use App\Payments\Data\NormalizedPayment;
use App\Payments\Data\ProviderTransactionResult;

/**
 * Internal contract every provider integration (M-Pesa, banks, card processors) implements.
 * Callers must check capabilities() before using optional operations.
 */
interface PaymentProviderAdapter
{
    public const CAP_WEBHOOKS = 'webhooks';
    public const CAP_VALIDATION = 'validation';
    public const CAP_REGISTER_URLS = 'register_urls';
    public const CAP_VERIFY = 'verify_transaction';
    public const CAP_BALANCE = 'balance_enquiry';
    public const CAP_STATEMENT_IMPORT = 'statement_import';

    public function validateWebhook(PaymentAccount $account, array $payload, array $headers, ?string $sourceIp = null): bool;

    public function normalizeTransaction(PaymentAccount $account, array $payload): NormalizedPayment;

    public function verifyTransaction(PaymentAccount $account, string $providerTransactionId): ProviderTransactionResult;

    /** Idempotency key for a raw provider event, used for duplicate-event protection. */
    public function eventKey(array $payload): ?string;

    /** @return list<string> */
    public function capabilities(): array;
}
