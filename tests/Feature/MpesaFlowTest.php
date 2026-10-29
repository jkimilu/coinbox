<?php

namespace Tests\Feature;

use App\Jobs\DeliverCallback;
use App\Models\ApiKey;
use App\Models\CallbackDelivery;
use App\Models\CallbackEndpoint;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\ProviderEvent;
use App\Models\Transaction;
use App\Payments\Mpesa\MpesaAccountConnector;
use App\Payments\ReversalService;
use App\Payments\Webhooks\WebhookDispatcher;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Tests\TestCase;

class MpesaFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected PaymentAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->merchant = Merchant::create(['name' => 'Duka Ltd', 'email' => 'duka@example.com', 'status' => 'active']);
        $this->account = app(MpesaAccountConnector::class)->connect($this->merchant, [
            'account_type' => 'paybill',
            'display_name' => 'Main PayBill',
            'external_account_reference' => '899399',
            'environment' => 'sandbox',
            'consumer_key' => 'ck',
            'consumer_secret' => 'cs',
        ]);
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'TransactionType' => 'Pay Bill',
            'TransID' => 'QHX123ABC',
            'TransTime' => '20261028101530',
            'TransAmount' => '2500.00',
            'BusinessShortCode' => '899399',
            'BillRefNumber' => 'INV-2026-001',
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '',
            'ThirdPartyTransID' => '',
            'MSISDN' => '2547****123',
            'FirstName' => 'JOHN',
            'MiddleName' => '',
            'LastName' => 'DOE',
        ], $overrides);
    }

    public function test_confirmation_records_transaction_once_and_notifies_merchant(): void
    {
        Http::fake(['https://merchant.example.com/*' => Http::response('ok', 200)]);
        CallbackEndpoint::create(['merchant_id' => $this->merchant->id, 'url' => 'https://merchant.example.com/hook']);

        $url = '/api/c2b/'.$this->account->route_key.'/confirmation';

        $this->postJson($url, $this->payload())->assertOk()->assertJson(['ResultCode' => 0]);
        $this->postJson($url, $this->payload())->assertOk(); // Safaricom retry

        $this->assertSame(1, Transaction::count());
        $txn = Transaction::first();
        $this->assertSame('2500.00', (string) $txn->amount);
        $this->assertSame('INV-2026-001', $txn->merchant_reference);
        $this->assertSame('succeeded', $txn->status);
        $this->assertSame('2026-10-28T10:15:30+03:00', $txn->paid_at->setTimezone('Africa/Nairobi')->toIso8601String());
        $this->assertSame('active', $this->account->fresh()->status);

        $delivery = CallbackDelivery::sole();
        $this->assertSame('delivered', $delivery->status);

        Http::assertSent(function ($request) {
            $endpoint = CallbackEndpoint::first();
            [$t, $v1] = array_map(fn ($p) => explode('=', $p, 2)[1], explode(',', $request->header('X-CoinBox-Signature')[0]));

            return hash_equals(hash_hmac('sha256', $t.'.'.$request->body(), $endpoint->secret), $v1)
                && $request['data']['provider_transaction_id'] === 'QHX123ABC'
                && $request['data']['short_code'] === '899399';
        });
    }

    public function test_shortcode_mismatch_is_rejected_and_not_recorded(): void
    {
        $this->postJson('/api/c2b/'.$this->account->route_key.'/confirmation', $this->payload(['BusinessShortCode' => '111111']))->assertOk();

        $this->assertSame(0, Transaction::count());
        $this->assertSame('rejected', ProviderEvent::sole()->processing_status);
    }

    public function test_unknown_route_key_records_nothing(): void
    {
        $this->postJson('/api/c2b/not-a-key/confirmation', $this->payload())->assertOk();
        $this->assertSame(0, ProviderEvent::count());
    }

    public function test_validation_rules(): void
    {
        $this->account->update(['configuration' => ['require_account_reference' => true, 'min_amount' => 10]]);
        $url = '/api/c2b/'.$this->account->route_key.'/validation';

        $this->postJson($url, $this->payload())->assertJson(['ResultCode' => '0']);
        $this->postJson($url, $this->payload(['BillRefNumber' => '']))->assertJson(['ResultCode' => 'C2B00012']);
        $this->postJson($url, $this->payload(['TransAmount' => '5']))->assertJson(['ResultCode' => 'C2B00013']);
    }

    public function test_transactions_are_immutable(): void
    {
        $this->postJson('/api/c2b/'.$this->account->route_key.'/confirmation', $this->payload());

        $this->expectException(LogicException::class);
        Transaction::first()->update(['amount' => '1.00']);
    }

    public function test_api_is_scoped_to_merchant_and_returns_summary(): void
    {
        $this->postJson('/api/c2b/'.$this->account->route_key.'/confirmation', $this->payload());
        $this->postJson('/api/c2b/'.$this->account->route_key.'/confirmation', $this->payload(['TransID' => 'QHX999', 'TransAmount' => '500']));

        app(ReversalService::class)->record(Transaction::where('provider_transaction_id', 'QHX999')->first(), 'RVS001', 'Customer error');

        [, $token] = ApiKey::issue($this->merchant, 'test');
        $h = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/me', $h)->assertOk()->assertJson(['id' => 'mer_'.$this->merchant->id]);
        $this->getJson('/api/v1/accounts', $h)->assertOk()->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/accounts/acct_'.$this->account->id.'/balance', $h)->assertOk()
            ->assertJsonPath('object', 'collection_summary')
            ->assertJsonPath('all_time.successful_payments.amount', '3000.00')
            ->assertJsonPath('all_time.reversals.amount', '500.00')
            ->assertJsonPath('all_time.net_recorded_collections', '2500.00');

        $this->getJson('/api/v1/transactions?type=payment', $h)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/transactions/receipt/qhx123abc', $h)->assertOk()->assertJsonPath('amount', '2500.00')->assertJsonPath('short_code', '899399');
        $this->getJson('/api/v1/transactions/reference/INV-2026-001', $h)->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/reports/collections?from=2026-10-01&to='.now()->addDay()->toDateString(), $h)->assertOk()
            ->assertJsonPath('totals.net_recorded_collections', '2500.00');

        // Another merchant cannot see these payments.
        $other = Merchant::create(['name' => 'Other', 'email' => 'o@example.com', 'status' => 'active']);
        [, $otherToken] = ApiKey::issue($other, 'other');
        $o = ['Authorization' => 'Bearer '.$otherToken];
        $txnId = Transaction::first()->id;

        $this->getJson('/api/v1/transactions/txn_'.$txnId, $o)->assertNotFound();
        $this->getJson('/api/v1/accounts/acct_'.$this->account->id.'/balance', $o)->assertNotFound();
        $this->getJson('/api/v1/transactions', $o)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_api_key_auth_and_abilities(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer cbx_bad'])->assertUnauthorized();

        [$key, $token] = ApiKey::issue($this->merchant, 'limited', ['accounts:read']);
        $h = ['Authorization' => 'Bearer '.$token];
        $this->getJson('/api/v1/accounts', $h)->assertOk();
        $this->getJson('/api/v1/transactions', $h)->assertForbidden();

        $key->forceFill(['revoked_at' => now()])->save();
        $this->getJson('/api/v1/accounts', $h)->assertUnauthorized();
    }

    public function test_failed_webhook_is_retried_later(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);
        Queue::fake([DeliverCallback::class]);
        CallbackEndpoint::create(['merchant_id' => $this->merchant->id, 'url' => 'https://merchant.example.com/hook']);

        $this->postJson('/api/c2b/'.$this->account->route_key.'/confirmation', $this->payload());

        $d = CallbackDelivery::sole();
        (new DeliverCallback($d->id))->handle();
        $d->refresh();
        $this->assertSame('pending', $d->status);
        $this->assertSame(1, $d->attempts);
        $this->assertSame(500, $d->last_response_code);
        $this->assertNotNull($d->next_attempt_at);
        Queue::assertPushed(DeliverCallback::class, 2); // initial + scheduled retry
    }

    public function test_register_urls_calls_daraja_with_account_urls(): void
    {
        Http::fake([
            'sandbox.safaricom.co.ke/oauth/*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
            'sandbox.safaricom.co.ke/mpesa/c2b/v2/registerurl' => Http::response(['OriginatorCoversationID' => 'x', 'ResponseCode' => '00000000', 'ResponseDescription' => 'Success']),
        ]);

        app(MpesaAccountConnector::class)->registerUrls($this->account);

        $this->assertNotNull($this->account->fresh()->urls_registered_at);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'registerurl')
            && $r['ShortCode'] === '899399'
            && $r['ConfirmationURL'] === $this->account->confirmationUrl()
            && ! preg_match('/mpesa|safaricom/i', parse_url($r['ConfirmationURL'], PHP_URL_PATH)));
    }

    public function test_signature_helper(): void
    {
        $this->assertSame('t=1,v1='.hash_hmac('sha256', '1.{}', 's'), WebhookDispatcher::sign('s', '{}', 1));
    }

    public function test_already_registered_with_current_urls_is_reported_as_up_to_date(): void
    {
        $already = Http::response(['requestId' => 'x', 'errorCode' => '500.003.1001', 'errorMessage' => 'URLs are already registered'], 500);
        Http::fakeSequence('sandbox.safaricom.co.ke/mpesa/*')
            ->push(['ResponseCode' => '00000000', 'ResponseDescription' => 'Success'])
            ->pushResponse($already)
            ->pushResponse($already);
        Http::fake(['sandbox.safaricom.co.ke/oauth/*' => Http::response(['access_token' => 'tok'])]);

        $connector = app(MpesaAccountConnector::class);
        $this->assertSame('registered', $connector->registerUrls($this->account)['status']);
        $this->assertSame('already_current', $connector->registerUrls($this->account->fresh())['status']);

        // After the shortcode changes, "already registered" means someone else's URLs.
        $this->account->fresh()->update(['external_account_reference' => '600111']);
        $this->expectExceptionMessage('already has callback URLs registered for shortcode 600111');
        $connector->registerUrls($this->account->fresh());
    }
}
