<?php

namespace Tests\Feature;

use App\Filament\Merchant\Resources\Transactions\Pages\ListTransactions;
use App\Jobs\DeliverCallback;
use App\Models\AccountRoute;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\StkRequest;
use App\Models\Transaction;
use App\Payments\Mpesa\MpesaAccountConnector;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MerchantStkApiTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected PaymentAccount $account;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake([DeliverCallback::class]);

        $this->merchant = Merchant::create(['name' => 'Duka', 'email' => 'd@example.com', 'status' => 'active']);
        $this->account = app(MpesaAccountConnector::class)->connect($this->merchant, [
            'account_type' => 'paybill', 'display_name' => 'Shop', 'external_account_reference' => '600984',
            'environment' => 'sandbox', 'consumer_key' => 'k', 'consumer_secret' => 's', 'passkey' => 'PASSKEY',
        ]);
        [, $this->token] = ApiKey::issue($this->merchant, 'POS', ['payments:create']);

        Http::fake([
            'sandbox.safaricom.co.ke/oauth/*' => Http::response(['access_token' => 'tok']),
            'sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response(['MerchantRequestID' => 'M1', 'CheckoutRequestID' => 'ws_CO_9', 'ResponseCode' => '0', 'CustomerMessage' => 'Success']),
        ]);
    }

    protected function push(array $body = [], ?string $token = null)
    {
        return $this->withToken($token ?? $this->token)->postJson('/api/v1/stk', $body + [
            'short_code' => '600984', 'phone' => '0712345678', 'amount' => 250, 'account_reference' => 'INV001',
        ]);
    }

    protected function sendCallback(StkRequest $stk, int $code, string $receipt = 'TKC1STK001'): void
    {
        $this->postJson('/api/stk/'.$stk->callback_token, ['Body' => ['stkCallback' => array_filter([
            'MerchantRequestID' => 'M1', 'CheckoutRequestID' => 'ws_CO_9', 'ResultCode' => $code, 'ResultDesc' => $code ? 'Request cancelled by user' : 'Processed',
            'CallbackMetadata' => $code ? null : ['Item' => [
                ['Name' => 'Amount', 'Value' => 250], ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
                ['Name' => 'TransactionDate', 'Value' => 20261103101500], ['Name' => 'PhoneNumber', 'Value' => 254712345678],
            ]],
        ], fn ($v) => $v !== null)]])->assertOk();
    }

    public function test_prompt_is_sent_and_success_becomes_a_transaction(): void
    {
        $res = $this->push()->assertStatus(202)->assertJson(['status' => 'pending', 'short_code' => '600984', 'amount' => 250]);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/stkpush/v1/processrequest')
            && $r['BusinessShortCode'] === '600984' && $r['PartyB'] === '600984'
            && $r['PhoneNumber'] === '254712345678' && $r['AccountReference'] === 'INV001'
            && $r['TransactionType'] === 'CustomerPayBillOnline');

        $stk = StkRequest::sole();
        $this->assertSame('stk_'.$stk->id, $res->json('id'));
        $this->assertSame('collection', $stk->purpose);

        $this->sendCallback($stk, 0);

        $txn = Transaction::sole();
        $this->assertSame('TKC1STK001', $txn->provider_transaction_id);
        $this->assertSame('250.00', (string) $txn->amount);
        $this->assertSame('INV001', $txn->merchant_reference);
        $this->assertSame($this->merchant->id, $txn->merchant_id);

        $this->withToken($this->token)->getJson('/api/v1/stk/stk_'.$stk->id)
            ->assertOk()->assertJson(['status' => 'succeeded', 'mpesa_receipt' => 'TKC1STK001', 'transaction' => ['id' => 'txn_'.$txn->id]]);
    }

    public function test_c2b_confirmation_for_same_receipt_is_not_double_counted(): void
    {
        $this->push()->assertStatus(202);
        $this->sendCallback(StkRequest::sole(), 0);

        $this->postJson('/api/c2b/'.$this->account->route_key.'/confirmation', [
            'TransactionType' => 'Pay Bill', 'TransID' => 'TKC1STK001', 'TransTime' => '20261103101500',
            'TransAmount' => '250.00', 'BusinessShortCode' => '600984', 'BillRefNumber' => 'INV001', 'MSISDN' => '2547***678',
        ])->assertOk();

        $this->assertSame(1, Transaction::count());
    }

    public function test_cancelled_prompt_records_nothing(): void
    {
        $this->push()->assertStatus(202);
        $stk = StkRequest::sole();
        $this->sendCallback($stk, 1032);

        $this->assertSame('cancelled', $stk->fresh()->status);
        $this->assertSame(0, Transaction::count());
    }

    public function test_read_only_key_cannot_send_prompts(): void
    {
        [, $readOnly] = ApiKey::issue($this->merchant, 'Reports');
        $this->push([], $readOnly)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_cannot_use_another_merchants_shortcode(): void
    {
        $other = Merchant::create(['name' => 'Other', 'email' => 'o@example.com', 'status' => 'active']);
        app(MpesaAccountConnector::class)->connect($other, [
            'account_type' => 'paybill', 'display_name' => 'Other', 'external_account_reference' => '700100',
            'environment' => 'sandbox', 'consumer_key' => 'k', 'consumer_secret' => 's', 'passkey' => 'P',
        ]);

        $this->push(['short_code' => '700100'])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_validation_errors(): void
    {
        $this->push(['phone' => '12345'])->assertStatus(422)->assertJsonPath('error.code', 'stk_failed');
        $this->push(['account_reference' => ''])->assertStatus(422);
        $this->push(['account_reference' => 'WAY-TOO-LONG-REF'])->assertStatus(422);
        $this->push(['amount' => 0])->assertStatus(422);
    }

    public function test_account_without_passkey_gives_clear_error(): void
    {
        $this->account->credentials->update(['secrets' => ['consumer_key' => 'k', 'consumer_secret' => 's']]);

        $this->push()->assertStatus(422)->assertJsonPath('error.message', fn ($m) => str_contains($m, 'passkey'));
    }

    public function test_merchant_can_request_payment_from_the_panel(): void
    {
        $route = AccountRoute::create(['merchant_id' => $this->merchant->id, 'payment_account_id' => $this->account->id, 'code' => '100', 'name' => 'Rent', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['merchant_id' => $this->merchant->id, 'email_verified_at' => now()]));
        Filament::setCurrentPanel('merchant');

        Livewire::test(ListTransactions::class)
            ->callAction('requestPayment', data: [
                'provider' => 'mpesa', 'payment_account_id' => $this->account->id, 'account_route_id' => $route->id,
                'reference' => 'INV001', 'amount' => 250, 'phone' => '0712345678',
            ])
            ->assertHasNoActionErrors()
            ->assertSet('pendingCollectionId', StkRequest::sole()->id);

        $this->assertSame('100#INV001', StkRequest::sole()->account_reference);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/stkpush/v1/processrequest') && $r['AccountReference'] === '100#INV001');
    }

    public function test_success_from_status_query_is_matched_to_the_c2b_payment(): void
    {
        Queue::fake([DeliverCallback::class, \App\Jobs\ProcessProviderEvent::class]); // no queue worker running
        Http::fake([
            'sandbox.safaricom.co.ke/oauth/*' => Http::response(['access_token' => 'tok']),
            'sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response(['MerchantRequestID' => 'M1', 'CheckoutRequestID' => 'ws_CO_9', 'ResponseCode' => '0']),
            'sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query' => Http::response(['ResultCode' => '0', 'ResultDesc' => 'The service request is processed successfully.']),
        ]);

        $this->push()->assertStatus(202);
        $stk = StkRequest::sole();
        $this->travel(30)->seconds();

        // Safaricom's STK callback never arrives; the status query says it succeeded (no receipt).
        $stk = app(\App\Payments\Mpesa\MerchantStkService::class)->refresh($stk);
        $this->assertSame('succeeded', $stk->status);
        $this->assertNull($stk->transaction_id);

        // The normal C2B confirmation arrives and waits in the queue.
        $this->postJson('/api/c2b/'.$this->account->route_key.'/confirmation', [
            'TransactionType' => 'Pay Bill', 'TransID' => 'TKC1Q00001', 'TransTime' => now('Africa/Nairobi')->format('YmdHis'),
            'TransAmount' => '250.00', 'BusinessShortCode' => '600984', 'BillRefNumber' => 'INV001', 'MSISDN' => 'abc123hash',
        ])->assertOk();
        $this->assertSame(0, Transaction::count());

        $stk = app(\App\Payments\Mpesa\MerchantStkService::class)->refresh($stk);
        $txn = Transaction::sole();
        $this->assertSame($txn->id, $stk->transaction_id);
        $this->assertSame('TKC1Q00001', $stk->mpesa_receipt);
    }
}
