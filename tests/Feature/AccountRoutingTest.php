<?php

namespace Tests\Feature;

use App\Filament\Merchant\Resources\PaymentAccounts\Pages\ManagePaymentAccounts;
use App\Models\AccountRoute;
use App\Models\ApiKey;
use App\Models\CallbackDelivery;
use App\Models\CallbackEndpoint;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Payments\Mpesa\MpesaAccountConnector;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AccountRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected PaymentAccount $paybill;
    protected CallbackEndpoint $sokofiti;
    protected CallbackEndpoint $other;
    protected CallbackEndpoint $general;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Http::fake(['*' => Http::response('ok')]);

        $this->merchant = Merchant::create(['name' => 'Portworth', 'email' => 'p@example.com', 'status' => 'active']);
        $this->paybill = app(MpesaAccountConnector::class)->connect($this->merchant, [
            'account_type' => 'paybill', 'display_name' => 'Portworth', 'external_account_reference' => '4072129',
            'environment' => 'production', 'consumer_key' => 'k', 'consumer_secret' => 's',
        ]);

        $m = ['merchant_id' => $this->merchant->id];
        $this->sokofiti = CallbackEndpoint::create($m + ['url' => 'https://sokofiti.example/api/payments/process']);
        $this->other = CallbackEndpoint::create($m + ['url' => 'https://other.example/hook']);
        $this->general = CallbackEndpoint::create($m + ['url' => 'https://general.example/hook']);

        AccountRoute::create(['payment_account_id' => $this->paybill->id, 'code' => '100', 'name' => 'Sokofiti', 'callback_endpoint_id' => $this->sokofiti->id]);
        AccountRoute::create(['payment_account_id' => $this->paybill->id, 'code' => '200', 'name' => 'Other shop', 'callback_endpoint_id' => $this->other->id]);
    }

    protected function pay(string $receipt, string $billRef, string $amount = '500'): Transaction
    {
        $this->postJson('/api/c2b/'.$this->paybill->route_key.'/confirmation', [
            'TransactionType' => 'Pay Bill', 'TransID' => $receipt, 'TransTime' => '20261029101500', 'TransAmount' => $amount,
            'BusinessShortCode' => '4072129', 'BillRefNumber' => $billRef, 'MSISDN' => '2547***1',
        ])->assertOk();

        return Transaction::where('provider_transaction_id', $receipt)->sole();
    }

    protected function deliveredTo(Transaction $t): array
    {
        return CallbackDelivery::where('transaction_id', $t->id)->with('endpoint')->get()->pluck('endpoint.url')->all();
    }

    public function test_account_100_goes_only_to_sokofiti_with_invoice_reference(): void
    {
        $t = $this->pay('R1', '100#INV001');

        $this->assertSame('100', $t->accountRoute->code);
        $this->assertSame('INV001', $t->merchant_reference);
        $this->assertSame('100#INV001', $t->metadata['bill_ref_number']);
        $this->assertSame(['https://sokofiti.example/api/payments/process'], $this->deliveredTo($t));
        Http::assertSent(fn ($r) => $r->url() === 'https://sokofiti.example/api/payments/process'
            && $r['data']['account_code'] === '100' && $r['data']['merchant_reference'] === 'INV001');
    }

    public function test_account_200_goes_to_its_own_webhook(): void
    {
        $t = $this->pay('R2', ' 200 # inv-77 ');
        $this->assertSame('200', $t->accountRoute->code);
        $this->assertSame('inv-77', $t->merchant_reference);
        $this->assertSame(['https://other.example/hook'], $this->deliveredTo($t));
    }

    public function test_plain_account_number_and_unknown_numbers(): void
    {
        $plain = $this->pay('R3', '100');
        $this->assertSame('100', $plain->accountRoute->code);
        $this->assertNull($plain->merchant_reference);

        // Unknown account number: kept as typed, sent only to general webhooks (not 100's or 200's).
        $unknown = $this->pay('R4', '999#X1');
        $this->assertNull($unknown->account_route_id);
        $this->assertSame('999#X1', $unknown->merchant_reference);
        $this->assertSame(['https://general.example/hook'], $this->deliveredTo($unknown));
    }

    public function test_validation_can_reject_unknown_account_numbers(): void
    {
        $this->paybill->update(['configuration' => ['require_known_account' => true]]);
        $url = '/api/c2b/'.$this->paybill->route_key.'/validation';
        $base = ['TransID' => 'V1', 'TransAmount' => '100', 'BusinessShortCode' => '4072129'];

        $this->postJson($url, $base + ['BillRefNumber' => '100#INV9'])->assertJson(['ResultCode' => '0']);
        $this->postJson($url, $base + ['BillRefNumber' => '555#INV9'])->assertJson(['ResultCode' => 'C2B00012']);
    }

    public function test_api_filters_by_account_code(): void
    {
        $this->pay('R5', '100#A');
        $this->pay('R6', '200#B');
        [, $token] = ApiKey::issue($this->merchant, 'k');

        $this->getJson('/api/v1/transactions?account_code=200', ['Authorization' => "Bearer {$token}"])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.account_code', '200')
            ->assertJsonPath('data.0.account_number', '200#B');
    }

    public function test_merchant_manages_account_numbers_in_the_panel(): void
    {
        $user = User::factory()->create(['merchant_id' => $this->merchant->id, 'email_verified_at' => now()]);
        $this->actingAs($user);
        Filament::setCurrentPanel('merchant');

        Livewire::test(ManagePaymentAccounts::class)
            ->mountTableAction('accountNumbers', $this->paybill)
            ->assertTableActionDataSet(fn ($data) => count($data['routes'] ?? []) === 2)
            ->setTableActionData(['routes' => [
                ['code' => '100', 'name' => 'Sokofiti', 'callback_endpoint_id' => $this->sokofiti->id, 'is_active' => true],
                ['code' => '300', 'name' => 'New branch', 'callback_endpoint_id' => $this->general->id, 'is_active' => true],
            ]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertEqualsCanonicalizing(['100', '300'], $this->paybill->routes()->pluck('code')->all());
        $this->get('/merchant/payment-accounts')->assertOk();
    }
}
