<?php

namespace App\Payments\Mpesa;

use App\Models\PaymentAccount;
use App\Payments\Exceptions\ProviderException;
use App\Payments\Exceptions\UrlsAlreadyRegisteredException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for Safaricom Daraja, using the merchant's own app credentials.
 */
class DarajaClient
{
    public function baseUrl(string $environment): string
    {
        return $environment === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';
    }

    public function accessToken(PaymentAccount $account): string
    {
        $cred = $account->credentials ?? throw new ProviderException('No Daraja credentials configured for this account.');
        $key = $cred->secret('consumer_key');
        $secret = $cred->secret('consumer_secret');

        if (! $key || ! $secret) {
            throw new ProviderException('Consumer key and secret are required.');
        }

        $cacheKey = 'daraja-token:'.$cred->id.':'.md5($key);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($cred, $key, $secret) {
            $response = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->timeout(20)
                ->get($this->baseUrl($cred->environment).'/oauth/v1/generate', ['grant_type' => 'client_credentials']);

            if (! $response->successful() || ! $response->json('access_token')) {
                $detail = trim($response->body()) ?: 'no details returned';

                throw new ProviderException(
                    "Daraja authentication failed (HTTP {$response->status()}, {$cred->environment}): {$detail}. "
                    .'Check the Consumer Key and Secret, and that the environment matches the app (sandbox keys only work in Sandbox).'
                );
            }

            return $response->json('access_token');
        });
    }

    protected function request(PaymentAccount $account): PendingRequest
    {
        return Http::withToken($this->accessToken($account))
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->baseUrl($this->baseUrl($account->credentials->environment));
    }

    /**
     * Register confirmation/validation URLs for the account's shortcode (C2B Register URL v2).
     *
     * @return array<string, mixed> Daraja response body
     */
    public function registerUrls(PaymentAccount $account): array
    {
        $payload = [
            'ShortCode' => $account->external_account_reference,
            // What Safaricom does if our validation URL is unreachable: Completed = accept payment.
            'ResponseType' => $account->config('validation_fallback', 'Completed'),
            'ConfirmationURL' => $account->confirmationUrl(),
            'ValidationURL' => $account->validationUrl(),
        ];

        $response = $this->request($account)->post('/mpesa/c2b/v2/registerurl', $payload);

        $body = $response->json() ?? ['raw' => $response->body()];

        // Daraja returns "0" or "00000000" for success.
        $code = (string) data_get($body, 'ResponseCode', '1');
        if (! $response->successful() || ! preg_match('/^0+$/', $code)) {
            if (preg_match('/already registered/i', (string) data_get($body, 'errorMessage'))) {
                throw new UrlsAlreadyRegisteredException(json_encode($body));
            }

            $hint = data_get($body, 'errorCode') === '500.003.1001'
                ? ' Safaricom returns this when the service is briefly unavailable (common in sandbox) or the shortcode is not enabled for C2B. Retry in a minute; in sandbox use shortcode 600000.'
                : '';

            throw new ProviderException('URL registration failed: '.json_encode($body).$hint);
        }

        return $body;
    }

    /** Sandbox only: simulate a C2B payment so you can test end-to-end. */
    public function simulate(PaymentAccount $account, string $amount, string $billRef, string $msisdn = '254708374149'): array
    {
        $response = $this->request($account)->post('/mpesa/c2b/v1/simulate', [
            'ShortCode' => $account->external_account_reference,
            'CommandID' => $account->account_type === 'till' ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
            'Amount' => $amount,
            'Msisdn' => $msisdn,
            'BillRefNumber' => $account->account_type === 'till' ? null : $billRef,
        ]);

        return $response->json() ?? ['raw' => $response->body()];
    }
}
