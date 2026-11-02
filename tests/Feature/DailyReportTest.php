<?php

namespace Tests\Feature;

use App\Filament\Merchant\Resources\Transactions\Pages\ListTransactions;
use App\Jobs\DeliverCallback;
use App\Mail\TransactionsEmail;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Mail\DailyCollectionReportMail;
use App\Models\AccountRoute;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Payments\Mpesa\MpesaAccountConnector;
use App\Payments\ReversalService;
use App\Reports\DailyCollectionReport;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected PaymentAccount $paybill;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake([DeliverCallback::class]);
        $this->travelTo(CarbonImmutable::parse('2026-11-03 03:00', 'Africa/Nairobi'));

        $this->merchant = Merchant::create(['name' => 'Duka Ltd', 'email' => 'd@example.com', 'status' => 'active']);
        $this->user = User::factory()->create(['merchant_id' => $this->merchant->id, 'email_verified_at' => now()]);
        $this->paybill = app(MpesaAccountConnector::class)->connect($this->merchant, [
            'account_type' => 'paybill', 'display_name' => 'Main PayBill', 'external_account_reference' => '600984',
            'environment' => 'sandbox', 'consumer_key' => 'k', 'consumer_secret' => 's',
        ]);
        AccountRoute::create(['merchant_id' => $this->merchant->id, 'payment_account_id' => $this->paybill->id, 'code' => '100', 'name' => 'Rent', 'is_active' => true]);
        AccountRoute::create(['merchant_id' => $this->merchant->id, 'payment_account_id' => $this->paybill->id, 'code' => '200', 'name' => 'Water', 'is_active' => true]);

        // Yesterday (2 Nov, Nairobi): two Rent payments, one with no account number. Day before: one payment.
        $this->pay('TK1', '1000.00', '100#A1', '20261102091500');
        $this->pay('TK2', '500.00', '100#A2', '20261102143000');
        $this->pay('TK3', '250.00', 'XYZ', '20261102235900');
        $this->pay('TK0', '800.00', '100', '20261101120000');
        $this->pay('TK9', '999.00', '100', '20261103010000'); // today: excluded

        app(ReversalService::class)->record(Transaction::where('provider_transaction_id', 'TK2')->sole(), 'RV1', 'Paid twice');
        Transaction::where('provider_transaction_id', 'RV1')->toBase()->update(['paid_at' => CarbonImmutable::parse('2026-11-02 16:00', 'Africa/Nairobi')->utc()]);
    }

    protected function pay(string $id, string $amount, string $ref, string $time): void
    {
        $this->postJson('/api/c2b/'.$this->paybill->route_key.'/confirmation', [
            'TransactionType' => 'Pay Bill', 'TransID' => $id, 'TransTime' => $time, 'TransAmount' => $amount,
            'BusinessShortCode' => '600984', 'BillRefNumber' => $ref, 'MSISDN' => 'hash',
        ])->assertOk();
    }

    public function test_report_totals_by_shortcode_and_account(): void
    {
        $r = app(DailyCollectionReport::class)->build($this->merchant, CarbonImmutable::parse('2026-11-02', 'Africa/Nairobi'));

        $this->assertEquals(1750.0, $r['totals']['received']);
        $this->assertSame(3, $r['totals']['received_count']);
        $this->assertEquals(500.0, $r['totals']['reversed']);
        $this->assertEquals(1250.0, $r['totals']['net']);
        $this->assertEquals(800.0, $r['previous_net']);
        $this->assertSame(56, $r['change_percent']);

        $lines = collect($r['shortcodes'][0]['lines'])->keyBy('label');
        $this->assertEquals(1500.0, $lines['100 · Rent']['received']);
        $this->assertEquals(500.0, $lines['100 · Rent']['reversed']);
        $this->assertEquals(0.0, $lines['200 · Water']['received']);
        $this->assertEquals(250.0, $lines['No account number / other']['received']);
        $this->assertEquals(1000.0, $r['hours'][9]);
        $this->assertCount(1, $r['reversals']);
    }

    public function test_command_sends_once_per_day(): void
    {
        Mail::fake();

        $this->artisan('coinbox:daily-report')->assertSuccessful();
        $this->artisan('coinbox:daily-report')->assertSuccessful();

        Mail::assertSent(DailyCollectionReportMail::class, 1);
        Mail::assertSent(DailyCollectionReportMail::class, fn ($m) => $m->hasTo($this->user->email)
            && str_contains($m->envelope()->subject, 'KES 1,250.00'));
    }

    public function test_email_renders(): void
    {
        $report = app(DailyCollectionReport::class)->build($this->merchant, CarbonImmutable::parse('2026-11-02', 'Africa/Nairobi'));
        $html = (new DailyCollectionReportMail($report, $this->user))->render();

        $this->assertStringContainsString('KES 1,250.00', $html);
        $this->assertStringContainsString('PayBill 600984', $html);
        $this->assertStringContainsString('100 · Rent', $html);
        $this->assertStringContainsString('Paid twice', $html);

        if ($path = env('REPORT_PREVIEW_PATH')) {
            file_put_contents($path, $html);
        }
    }

    public function test_unsubscribe_link(): void
    {
        $url = URL::signedRoute('reports.daily.unsubscribe', ['user' => $this->user->id]);

        $this->get($url)->assertOk()->assertSee('Stop daily reports');
        $this->assertTrue($this->user->fresh()->daily_report);

        $this->post($url)->assertOk();
        $this->assertFalse($this->user->fresh()->daily_report);

        Mail::fake();
        $this->artisan('coinbox:daily-report')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_email_me_sends_html_and_optional_excel(): void
    {
        Mail::fake();
        $this->actingAs($this->user);
        Filament::setCurrentPanel('merchant');

        Livewire::test(ListTransactions::class)
            ->callAction('emailMe', data: ['attach_excel' => false])->assertHasNoActionErrors()
            ->callAction('emailMe', data: ['attach_excel' => true])->assertHasNoActionErrors();

        $sent = Mail::sent(TransactionsEmail::class);
        $this->assertCount(2, $sent);
        $this->assertTrue($sent[0]->hasTo($this->user->email));
        $this->assertSame(6, $sent[0]->count); // 5 payments + 1 reversal
        $this->assertCount(0, $sent[0]->attachments());
        $this->assertCount(1, $sent[1]->attachments());

        $html = $sent[0]->render();
        $this->assertStringContainsString('TK1', $html);
        $this->assertStringContainsString('Duka Ltd', $html);

        if ($path = env('TXN_PREVIEW_PATH')) {
            file_put_contents($path, $html);
        }
    }
}
