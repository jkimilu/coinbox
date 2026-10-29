<?php

namespace Tests\Feature;

use App\Filament\Merchant\Widgets\IntegrationHealth;
use App\Filament\Merchant\Widgets\PaymentsChart;
use App\Filament\Merchant\Widgets\PaymentStats;
use App\Filament\Merchant\Widgets\RecentPayments;
use App\Filament\Merchant\Widgets\SetupChecklist;
use App\Filament\Merchant\Widgets\WelcomeBanner;
use App\Models\Merchant;
use App\Models\User;
use App\Payments\Mpesa\MpesaAccountConnector;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MerchantDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_every_widget_render(): void
    {
        $this->seed(DatabaseSeeder::class);
        $m = Merchant::create(['name' => 'Duka', 'email' => 'd@example.com', 'status' => 'active']);
        $account = app(MpesaAccountConnector::class)->connect($m, [
            'account_type' => 'paybill', 'display_name' => 'Main', 'external_account_reference' => '899399',
            'environment' => 'sandbox', 'consumer_key' => 'k', 'consumer_secret' => 's',
        ]);
        $this->postJson('/api/c2b/'.$account->route_key.'/confirmation', [
            'TransactionType' => 'Pay Bill', 'TransID' => 'D1', 'TransTime' => now('Africa/Nairobi')->format('YmdHis'),
            'TransAmount' => '1500', 'BusinessShortCode' => '899399', 'BillRefNumber' => 'INV1', 'MSISDN' => '2547***1', 'FirstName' => 'JANE',
        ]);

        $user = User::factory()->create(['merchant_id' => $m->id, 'name' => 'Jane Wanjiku', 'email_verified_at' => now()]);
        $this->actingAs($user);
        Filament::setCurrentPanel('merchant');

        $this->get('/merchant')->assertOk()->assertSee('Jane')->assertSee('Finish setting up CoinBox');

        Livewire::test(WelcomeBanner::class)->assertOk()->assertSee('Receiving payments');
        Livewire::test(SetupChecklist::class)->assertOk()->assertSee('Add a webhook');
        Livewire::test(PaymentStats::class)->assertOk()->assertSee('KES 1,500');
        Livewire::test(PaymentsChart::class)->assertOk()->set('filter', '7')->assertOk();
        Livewire::test(IntegrationHealth::class)->assertOk()->assertSee('No webhook');
        Livewire::test(RecentPayments::class)->assertOk()->assertSee('D1')->call('$refresh')->assertOk();
    }
}
