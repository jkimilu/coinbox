<?php

namespace Tests\Feature;

use App\Billing\BillingService;
use App\Models\BillingInvoice;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\InvoiceIssued;
use App\Payments\Mpesa\MpesaAccountConnector;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected PaymentAccount $account;
    protected BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        $this->billing = app(BillingService::class);

        $this->merchant = Merchant::create(['name' => 'Duka', 'email' => 'd@example.com', 'status' => 'active']);
        User::factory()->create(['merchant_id' => $this->merchant->id]);
        $this->account = $this->connect($this->merchant, '899399');
    }

    protected function connect(Merchant $m, string $shortcode): PaymentAccount
    {
        return app(MpesaAccountConnector::class)->connect($m, [
            'account_type' => 'paybill', 'display_name' => "PB {$shortcode}", 'external_account_reference' => $shortcode,
            'environment' => 'sandbox', 'consumer_key' => 'k', 'consumer_secret' => 's',
        ]);
    }

    protected function pay(PaymentAccount $account, string $receipt, string $amount, string $ref, string $time = '20261015120000'): void
    {
        $this->postJson('/api/c2b/'.$account->route_key.'/confirmation', [
            'TransactionType' => 'Pay Bill', 'TransID' => $receipt, 'TransTime' => $time, 'TransAmount' => $amount,
            'BusinessShortCode' => $account->external_account_reference, 'BillRefNumber' => $ref, 'MSISDN' => '2547***1',
        ])->assertOk();
    }

    public function test_merchant_gets_a_numeric_account_number(): void
    {
        $this->assertIsInt($this->merchant->account_number);
        $this->assertGreaterThan(100, $this->merchant->account_number);
    }

    public function test_no_invoice_below_free_allowance(): void
    {
        $this->pay($this->account, 'A1', '9000', 'X');
        $this->assertSame(0, $this->billing->generateForMonth(CarbonImmutable::create(2026, 10, 1, 0, 0, 0, 'Africa/Nairobi')));
    }

    public function test_invoice_is_issued_and_paid_from_float(): void
    {
        $this->pay($this->account, 'A1', '200000', 'X');
        $this->pay($this->account, 'A2', '50000', 'Y');
        $this->billing->recordPayment($this->merchant, 3000, 'TOPUP1');

        $this->assertSame(1, $this->billing->generateForMonth(CarbonImmutable::create(2026, 10, 1, 0, 0, 0, 'Africa/Nairobi')));
        $this->assertSame(0, $this->billing->generateForMonth(CarbonImmutable::create(2026, 10, 1, 0, 0, 0, 'Africa/Nairobi')), 'idempotent');

        $inv = BillingInvoice::sole();
        $this->assertSame('INV001', $inv->number);
        $this->assertSame('2500.00', (string) $inv->amount);          // 1% of 250,000
        $this->assertSame('paid', $inv->status);
        $this->assertSame(500.0, $this->merchant->floatBalance());
        $this->assertSame($this->merchant->account_number.'#INV001', $inv->paymentReference());
        Notification::assertSentTo($this->merchant->users, InvoiceIssued::class);
    }

    public function test_unpaid_invoice_is_settled_by_payment_to_coinbox_paybill(): void
    {
        // CoinBox's own PayBill, connected as an account and selected in settings.
        $platform = Merchant::create(['name' => 'CoinBox', 'email' => 'billing@coinbox.test', 'status' => 'active']);
        $platformAccount = $this->connect($platform, '4072129');
        Setting::put('billing.platform_account_id', $platformAccount->id);

        $this->pay($this->account, 'A1', '100000', 'X');
        $this->billing->generateForMonth(CarbonImmutable::create(2026, 10, 1, 0, 0, 0, 'Africa/Nairobi'));
        $inv = BillingInvoice::where('merchant_id', $this->merchant->id)->sole();
        $this->assertSame('open', $inv->status);
        $this->assertSame(1000.0, $inv->outstanding());
        $this->assertGreaterThan(0, $this->billing->advice($this->merchant)['top_up']);

        // Merchant pays PayBill 4072129, Account "<number>#INV001", a little more than owed.
        $this->pay($platformAccount, 'PAYINV1', '1200', $this->merchant->account_number.'#INV001');

        $inv->refresh();
        $this->assertSame('paid', $inv->status);
        $this->assertSame(200.0, $this->merchant->floatBalance());

        // Same receipt again (Safaricom retry or manual entry) is ignored.
        $this->assertNull($this->billing->recordPayment($this->merchant, 1200, 'PAYINV1'));
        $this->assertSame(200.0, $this->merchant->floatBalance());

        // Plain merchant number = float top-up.
        $this->pay($platformAccount, 'TOP2', '800', (string) $this->merchant->account_number);
        $this->assertSame(1000.0, $this->merchant->floatBalance());
    }

    public function test_billing_pages_render(): void
    {
        $this->actingAs($this->merchant->users()->first()->forceFill(['email_verified_at' => now()]));
        $this->get('/merchant/billing')->assertOk()->assertSee('Float balance')->assertSee('4072129')->assertSee((string) $this->merchant->account_number);

        $this->actingAs(User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]));
        $this->get('/admin/invoices')->assertOk();
        $this->get('/admin/float')->assertOk();
        $this->get('/admin/pricing')->assertOk()->assertSee('How merchants pay CoinBox');
    }
}
