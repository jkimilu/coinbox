<?php

namespace Tests\Feature;

use App\Models\CallbackEndpoint;
use App\Models\Merchant;
use App\Models\User;
use App\Payments\Mpesa\MpesaAccountConnector;
use Database\Seeders\DatabaseSeeder;
use App\Filament\Merchant\Resources\PaymentAccounts\Pages\ManagePaymentAccounts;
use App\Models\PaymentAccount;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PanelPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function makeMerchantUser(): User
    {
        $this->seed(DatabaseSeeder::class);
        $m = Merchant::create(['name' => 'Duka', 'email' => 'd@example.com', 'status' => 'active']);
        $account = app(MpesaAccountConnector::class)->connect($m, [
            'account_type' => 'till', 'display_name' => 'Shop till', 'external_account_reference' => '174379',
            'till_number' => '5551234', 'environment' => 'sandbox', 'consumer_key' => 'a', 'consumer_secret' => 'b',
        ]);
        CallbackEndpoint::create(['merchant_id' => $m->id, 'url' => 'https://x.example.com/hook']);
        $this->postJson('/api/c2b/'.$account->route_key.'/confirmation', [
            'TransactionType' => 'Buy Goods', 'TransID' => 'ABC1', 'TransTime' => '20261028101530',
            'TransAmount' => '100', 'BusinessShortCode' => '174379', 'BillRefNumber' => '', 'MSISDN' => '2547***1',
        ]);

        return User::factory()->create(['merchant_id' => $m->id, 'email_verified_at' => now()]);
    }

    public function test_merchant_pages_render(): void
    {
        $this->actingAs($this->makeMerchantUser());

        foreach (['/merchant', '/merchant/payment-accounts', '/merchant/transactions', '/merchant/callback-endpoints',
            '/merchant/callback-deliveries', '/merchant/api-keys', '/merchant/setup-guide'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/admin')->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $this->makeMerchantUser();
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($admin);

        foreach (['/admin', '/admin/merchants', '/admin/payment-accounts', '/admin/transactions', '/admin/provider-events',
            '/admin/callback-deliveries', '/admin/reconciliation-runs', '/admin/audit-logs', '/admin/payment-providers', '/admin/pricing'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_merchant_can_connect_paybill_through_wizard(): void
    {
        Http::fake([
            'api.safaricom.co.ke/oauth/*' => Http::response(['access_token' => 'tok']),
            'api.safaricom.co.ke/mpesa/c2b/v2/registerurl' => Http::response(['ResponseCode' => '0', 'ResponseDescription' => 'Success']),
        ]);
        $user = $this->makeMerchantUser();
        $this->actingAs($user);
        Filament::setCurrentPanel('merchant');

        Livewire::test(ManagePaymentAccounts::class)
            ->callAction('create', data: [
                'account_type' => 'paybill',
                'display_name' => 'Invoices PayBill',
                'external_account_reference' => '899399',
                'environment' => 'production',
                'consumer_key' => 'KEY',
                'consumer_secret' => 'SECRET',
                'register_now' => true,
            ])
            ->assertHasNoFormErrors();

        $account = PaymentAccount::where('external_account_reference', '899399')->sole();
        $this->assertSame($user->merchant_id, $account->merchant_id);
        $this->assertSame('active', $account->status);
        $this->assertNotNull($account->urls_registered_at);
        $this->assertSame('SECRET', $account->credentials->secret('consumer_secret'));
        $this->assertStringNotContainsString('SECRET', $account->credentials->getRawOriginal('secrets'));
    }

    public function test_merchant_can_change_shortcode(): void
    {
        $user = $this->makeMerchantUser();
        $this->actingAs($user);
        Filament::setCurrentPanel('merchant');

        $account = PaymentAccount::sole();
        $account->update(['urls_registered_at' => now(), 'status' => 'active']);

        Livewire::test(ManagePaymentAccounts::class)
            ->callTableAction('edit', $account, data: ['external_account_reference' => '600984'])
            ->assertHasNoTableActionErrors();

        $account->refresh();
        $this->assertSame('600984', $account->external_account_reference);
        $this->assertNull($account->urls_registered_at);
        $this->assertSame('pending', $account->status);
    }

    public function test_admin_can_filter_transactions_by_shortcode(): void
    {
        $this->makeMerchantUser(); // creates a till 174379 with one payment
        $this->actingAs(User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]));
        Filament::setCurrentPanel('admin');

        $account = PaymentAccount::sole();
        $txns = \App\Models\Transaction::all();

        Livewire::test(\App\Filament\Admin\Resources\Transactions\Pages\ListTransactions::class)
            ->filterTable('payment_account_id', [$account->id])
            ->assertCanSeeTableRecords($txns)
            ->assertTableFilterExists('payment_account_id');
    }

    public function test_admin_can_add_a_bank_provider(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]));
        Filament::setCurrentPanel('admin');

        Livewire::test(\App\Filament\Admin\Resources\PaymentProviders\Pages\ManagePaymentProviders::class)
            ->callAction('create', data: [
                'name' => 'Co-operative Bank of Kenya',
                'code' => " Coop Bank\u{00A0}",
                'kind' => 'bank',
                'adapter' => \App\Payments\Unintegrated\UnintegratedAdapter::class,
                'is_enabled' => false,
            ])
            ->assertHasNoFormErrors();

        $p = \App\Models\PaymentProvider::where('code', 'coop_bank')->sole();
        $this->assertSame('bank', $p->kind);
        $this->assertSame([], $p->adapter()->capabilities());
        $this->get('/admin/payment-providers')->assertOk()->assertSee('Co-operative Bank of Kenya');
    }
}
