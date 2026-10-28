<?php

namespace App\Payments\Mpesa;

use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\PaymentProvider;
use App\Models\ProviderCredential;
use App\Payments\Exceptions\ProviderException;
use App\Payments\Exceptions\UrlsAlreadyRegisteredException;
use Illuminate\Support\Facades\DB;

/**
 * Connects a merchant's own PayBill/Till (and their Daraja app credentials) to the gateway.
 */
class MpesaAccountConnector
{
    public function __construct(protected DarajaClient $client) {}

    public function connect(Merchant $merchant, array $data): PaymentAccount
    {
        $provider = PaymentProvider::byCode(PaymentProvider::MPESA);

        return DB::transaction(function () use ($merchant, $provider, $data) {
            $credential = ProviderCredential::create([
                'merchant_id' => $merchant->id,
                'provider_id' => $provider->id,
                'environment' => $data['environment'] ?? 'production',
                'secrets' => array_filter([
                    'consumer_key' => trim($data['consumer_key']),
                    'consumer_secret' => trim($data['consumer_secret']),
                    'passkey' => isset($data['passkey']) ? trim($data['passkey']) : null,
                ]),
            ]);

            $account = PaymentAccount::create([
                'merchant_id' => $merchant->id,
                'provider_id' => $provider->id,
                'account_type' => $data['account_type'],
                'display_name' => $data['display_name'],
                'external_account_reference' => trim($data['external_account_reference']),
                'currency' => 'KES',
                'credentials_id' => $credential->id,
                'callback_endpoint_id' => $data['callback_endpoint_id'] ?? null,
                'configuration' => [
                    'till_number' => $data['till_number'] ?? null,
                    'require_account_reference' => (bool) ($data['require_account_reference'] ?? false),
                    'min_amount' => $data['min_amount'] ?? null,
                    'validation_fallback' => $data['validation_fallback'] ?? 'Completed',
                ],
                'status' => 'pending',
            ]);

            AuditLog::record('payment_account.connected', $account, ['shortcode' => $account->external_account_reference], $merchant->id);

            return $account;
        });
    }

    public function updateCredentials(PaymentAccount $account, array $data): void
    {
        $cred = $account->credentials;
        $secrets = $cred->secrets;
        foreach (['consumer_key', 'consumer_secret', 'passkey'] as $field) {
            if (filled($data[$field] ?? null)) {
                $secrets[$field] = trim($data[$field]);
            }
        }
        $cred->update(['secrets' => $secrets, 'environment' => $data['environment'] ?? $cred->environment]);

        AuditLog::record('payment_account.credentials_updated', $account, [], $account->merchant_id);
    }

    /**
     * Calls Daraja C2B Register URL with the merchant's credentials.
     *
     * Returns ['status' => 'registered'] or ['status' => 'already_current'] (Safaricom already has
     * exactly these URLs). Throws ProviderException otherwise.
     */
    public function registerUrls(PaymentAccount $account): array
    {
        try {
            $response = $this->client->registerUrls($account);
        } catch (UrlsAlreadyRegisteredException $e) {
            if ($this->registeredUrlsAreCurrent($account)) {
                $account->update(['last_registration_response' => 'Already registered with the current URLs. '.$e->getMessage()]);

                return ['status' => 'already_current'];
            }

            $message = 'Safaricom already has callback URLs registered for shortcode '.$account->external_account_reference
                .' and will not replace them through the API. '
                .($account->credentials?->environment === 'sandbox'
                    ? 'Sandbox test shortcodes are shared, so another developer may hold it: wait and retry later, or use another test shortcode from your Daraja app.'
                    : 'Email M-PESABusiness@safaricom.co.ke (copy apisupport@safaricom.co.ke) asking them to delete the C2B URLs for this shortcode, then click "Register URLs with Safaricom" again.');

            $account->update(['last_registration_response' => $message.' '.$e->getMessage()]);
            AuditLog::record('payment_account.url_registration_failed', $account, ['error' => $e->getMessage()], $account->merchant_id);

            throw new ProviderException($message, previous: $e);
        } catch (\Throwable $e) {
            $account->update(['last_registration_response' => $e->getMessage()]);
            AuditLog::record('payment_account.url_registration_failed', $account, ['error' => $e->getMessage()], $account->merchant_id);

            throw $e;
        }

        $account->update([
            'urls_registered_at' => now(),
            'last_registration_response' => json_encode($response),
            'status' => $account->status === 'disabled' ? 'disabled' : 'active',
            'configuration' => array_merge($account->configuration ?? [], [
                'registered' => [
                    'shortcode' => $account->external_account_reference,
                    'confirmation_url' => $account->confirmationUrl(),
                    'validation_url' => $account->validationUrl(),
                ],
            ]),
        ]);

        AuditLog::record('payment_account.urls_registered', $account, $response, $account->merchant_id);

        return ['status' => 'registered'] + $response;
    }

    /** True when the URLs we last registered for this shortcode are the ones we would register now. */
    public function registeredUrlsAreCurrent(PaymentAccount $account): bool
    {
        $reg = $account->config('registered');

        return $account->urls_registered_at !== null
            && ($reg['shortcode'] ?? null) === $account->external_account_reference
            && ($reg['confirmation_url'] ?? null) === $account->confirmationUrl()
            && ($reg['validation_url'] ?? null) === $account->validationUrl();
    }
}
