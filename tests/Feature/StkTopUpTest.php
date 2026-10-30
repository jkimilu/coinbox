<?php

namespace Tests\Feature;

use App\Billing\BillingService;
use App\Billing\StkTopUpService;
use App\Filament\Merchant\Resources\Billing\Pages\ListBilling;
use App\Filament\Merchant\Resources\Billing\Widgets\FloatOverview;
use App\Models\BillingInvoice;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\Setting;
use App\Models\StkRequest;
use App\Models\User;
use App\Payments\Mpesa\MpesaAccountConnector;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class StkTopUpTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected User $user;
    protected PaymentAccount $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $coinbox = Merchant::create(['name' => 'CoinBox', 'email' => 'billing@coinbox.test', 'status' => 'active']);
        $this->platform = app(MpesaAccountConnector::class)->connect($coinbox, [
            'account_type' => 'paybill', 'display_name' => 'CoinBox billing', 'external_account_reference' => '4072129',
            'environment' => 'sandbox', 'consumer_key' => 'k', 'consumer_secret' => 's', 'passkey' => 'PASSKEY',
        ]);
        Setting::put('billing.platform_account_id', $this->platform->id);

        $this->merchant = Merchant::create(['name' => 'Duka', 'email' => 'd@example.com', 'phone' => '0712345678', 'status' => 'active']);
        $this->user = User::factory()->create(['merchant_id' => $this->merchant->id, 'email_verified_at' => now()]);

        Http::fake([
            'sandbox.safaricom.co.ke/oauth/*' => Http::response(['access_token' => 'tok']),
            'sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response(['MerchantRequestID' => 'M1', 'CheckoutRequestID' => 'ws_CO_1', 'ResponseCode' => '0', 'CustomerMessage' => 'Success']),
        ]);
    }

    protected function sendStkCallback(StkRequest $stk, int $code, ?string $receipt = 'TKA123XYZ', int $amount = 500): void
    {
        $this->postJson('/api/stk/'.$stk->callback_token, ['Body' => ['stkCallback' => array_filter([
            'MerchantRequestID' => 'M1', 'CheckoutRequestID' => 'ws_CO_1', 'ResultCode' => $code, 'ResultDesc' => $code ? 'Request cancelled by user' : 'Processed',
            'CallbackMetadata' => $code ? null : ['Item' => [
                ['Name' => 'Amount', 'Value' => $amount], ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
                ['Name' => 'TransactionDate', 'Value' => 20261030101500], ['Name' => 'PhoneNumber', 'Value' => 254712345678],
            ]],
        ], fn ($v) => $v !== null)]])->assertOk()->assertJson(['ResultCode' => 0]);
    }

    public function test_phone_normalisation(): void
    {
        foreach (['0712345678', '712345678', '+254 712 345 678', '254112345678'] as $in) {
            $this->assertNotNull(StkTopUpService::normalizePhone($in), $in);
        }
        $this->assertSame('254712345678', StkTopUpService::normalizePhone('0712 345 678'));
        $this->assertNull(StkTopUpService::normalizePhone('0812345678'));
        $this->assertNull(StkTopUpService::normalizePhone('12345'));
    }

    public function test_prompt_sent_with_merchant_reference_and_float_credited_once(): void
    {
        $stk = app(StkTopUpService::class)->initiate($this->merchant, $this->user, '0712 345 678', 500);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'stkpush') && $r['Amount'] === 500 && $r['PhoneNumber'] === '254712345678'
            && $r['AccountReference'] === (string) $this->merchant->account_number && $r['TransactionType'] === 'CustomerPayBillOnline'
            && str_contains($r['CallBackURL'], '/api/stk/') && ! preg_match('/mpesa|safaricom/i', parse_url($r['CallBackURL'], PHP_URL_PATH)));

        $this->sendStkCallback($stk, 0);
        $this->sendStkCallback($stk, 0); // Safaricom retry

        $this->assertSame('succeeded', $stk->fresh()->status);
        $this->assertSame(500.0, $this->merchant->floatBalance());
    }

    public function test_cancelled_prompt_does_not_credit(): void
    {
        $stk = app(StkTopUpService::class)->initiate($this->merchant, $this->user, '0712345678', 1000);
        $this->sendStkCallback($stk, 1032);
        $this->assertSame('cancelled', $stk->fresh()->status);
        $this->assertSame(0.0, $this->merchant->floatBalance());
        $this->assertStringContainsString('cancelled', StkTopUpService::message($stk->fresh()));
    }

    public function test_invoice_prompt_pays_that_invoice(): void
    {
        $inv = BillingInvoice::create([
            'merchant_id' => $this->merchant->id, 'number' => 'INV001', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'processed_volume' => 50000, 'payment_count' => 10, 'fee_percent' => 1, 'free_threshold' => 10000, 'amount' => 500,
            'status' => 'open', 'issued_at' => now(), 'due_at' => now()->addDays(14),
        ]);
        $stk = app(StkTopUpService::class)->initiate($this->merchant, $this->user, '0712345678', 500, 'INV001');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'stkpush') && $r['AccountReference'] === $this->merchant->account_number.'#INV001');

        $this->sendStkCallback($stk, 0);
        $this->assertSame('paid', $inv->fresh()->status);
        $this->assertSame(0.0, $this->merchant->floatBalance());
    }

    public function test_merchant_can_pay_open_invoices_from_positive_float(): void
    {
        $inv = BillingInvoice::create([
            'merchant_id' => $this->merchant->id, 'number' => 'INV001', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'processed_volume' => 50000, 'payment_count' => 10, 'fee_percent' => 1, 'free_threshold' => 10000, 'amount' => 500,
            'status' => 'open', 'issued_at' => now(), 'due_at' => now()->addDays(14),
        ]);
        // Float credited by an admin adjustment (doesn't auto-settle)
        \App\Models\FloatEntry::create(['merchant_id' => $this->merchant->id, 'type' => 'adjustment', 'amount' => 300, 'description' => 'credit']);

        $this->actingAs($this->user);
        Filament::setCurrentPanel('merchant');

        Livewire::test(ListBilling::class)->callTableAction('payFromFloat', $inv);
        $inv->refresh();
        $this->assertSame('open', $inv->status);              // partly paid
        $this->assertSame(200.0, $inv->outstanding());
        $this->assertSame(0.0, $this->merchant->floatBalance());

        \App\Models\FloatEntry::create(['merchant_id' => $this->merchant->id, 'type' => 'adjustment', 'amount' => 1000, 'description' => 'credit']);
        Livewire::test(ListBilling::class)->assertTableActionVisible('payFromFloat', $inv)->callTableAction('payFromFloat', $inv);
        $this->assertSame('paid', $inv->fresh()->status);
        $this->assertSame(800.0, $this->merchant->floatBalance());

        // Paid invoices no longer offer it
        Livewire::test(ListBilling::class)->assertTableActionHidden('payFromFloat', $inv->fresh());
    }

    public function test_float_card_modal_preloads_phone_and_amount_and_sends(): void
    {
        $this->actingAs($this->user);
        Filament::setCurrentPanel('merchant');

        Livewire::test(FloatOverview::class)
            ->assertSee('Top up with M-Pesa')
            ->mountAction('payWithMpesa')
            ->assertSet('mountedActions.0.data.phone', '0712345678')   // registered business phone
            ->set('mountedActions.0.data.preset', 'custom')
            ->set('mountedActions.0.data.amount', 750)
            ->set('mountedActions.0.data.phone', '0722000111')           // editable
            ->callMountedAction()
            ->assertHasNoErrors()
            ->assertSet('pendingStkId', fn ($id) => $id !== null)
            ->assertSee('Check your phone');

        $this->assertSame(750, StkRequest::sole()->amount);
        $this->assertSame('254722000111', StkRequest::sole()->phone);
        $this->get('/merchant/billing')->assertOk()->assertSee('Top up with M-Pesa');
    }
}
