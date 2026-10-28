<?php

namespace App\Payments\Mpesa;

use App\Models\PaymentAccount;
use App\Payments\Contracts\PaymentProviderAdapter;
use App\Payments\Data\NormalizedPayment;
use App\Payments\Data\ProviderTransactionResult;
use App\Payments\Exceptions\ProviderException;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * M-Pesa C2B (PayBill and Till / Buy Goods) via Safaricom Daraja.
 *
 * Daraja C2B callbacks are not signed, so authenticity relies on:
 *  - an unguessable per-account route key in the callback URL,
 *  - the payload shortcode matching the account,
 *  - an optional Safaricom source-IP allowlist (config/coinbox.php).
 */
class MpesaAdapter implements PaymentProviderAdapter
{
    public function __construct(protected DarajaClient $client) {}

    public function capabilities(): array
    {
        return [self::CAP_WEBHOOKS, self::CAP_VALIDATION, self::CAP_REGISTER_URLS, self::CAP_STATEMENT_IMPORT];
    }

    public function validateWebhook(PaymentAccount $account, array $payload, array $headers, ?string $sourceIp = null): bool
    {
        if (config('coinbox.mpesa.enforce_ip_allowlist') && ! IpUtils::checkIp((string) $sourceIp, config('coinbox.mpesa.allowed_ips'))) {
            return false;
        }

        if (empty($payload['TransID']) || ! isset($payload['TransAmount'])) {
            return false;
        }

        return (string) ($payload['BusinessShortCode'] ?? '') === (string) $account->external_account_reference;
    }

    public function eventKey(array $payload): ?string
    {
        return isset($payload['TransID']) ? (string) $payload['TransID'] : null;
    }

    public function normalizeTransaction(PaymentAccount $account, array $payload): NormalizedPayment
    {
        $amount = (string) $payload['TransAmount'];
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new ProviderException("Invalid amount [{$amount}].");
        }

        $paidAt = isset($payload['TransTime'])
            ? CarbonImmutable::createFromFormat('YmdHis', (string) $payload['TransTime'], 'Africa/Nairobi')->utc()
            : CarbonImmutable::now()->utc();

        $name = trim(implode(' ', array_filter([
            $payload['FirstName'] ?? null, $payload['MiddleName'] ?? null, $payload['LastName'] ?? null,
        ])));

        $method = match (strtolower((string) ($payload['TransactionType'] ?? ''))) {
            'buy goods', 'customer merchant payment' => 'till',
            'pay bill', 'customer paybill online' => 'paybill',
            default => $account->account_type,
        };

        $reference = trim((string) ($payload['BillRefNumber'] ?? '')) ?: null;

        return new NormalizedPayment(
            providerTransactionId: (string) $payload['TransID'],
            paymentMethod: $method,
            amount: number_format((float) $amount, 2, '.', ''),
            currency: 'KES',
            paidAt: $paidAt,
            merchantReference: $reference,
            payerIdentifier: isset($payload['MSISDN']) ? (string) $payload['MSISDN'] : null,
            payerName: $name ?: null,
            metadata: array_filter([
                'business_short_code' => $payload['BusinessShortCode'] ?? null,
                'invoice_number' => $payload['InvoiceNumber'] ?? null,
                'third_party_trans_id' => $payload['ThirdPartyTransID'] ?? null,
                'transaction_type' => $payload['TransactionType'] ?? null,
            ]),
        );
    }

    public function verifyTransaction(PaymentAccount $account, string $providerTransactionId): ProviderTransactionResult
    {
        // The Daraja Transaction Status API is asynchronous and needs an initiator + security credential.
        // Until those are configured per account, verification is reported as unsupported.
        return ProviderTransactionResult::unsupported('M-Pesa transaction status lookup is not enabled for this account.');
    }

    public function registerUrls(PaymentAccount $account): array
    {
        return $this->client->registerUrls($account);
    }
}
