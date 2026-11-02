<?php

namespace Tests\Feature;

use App\Filament\Merchant\Resources\Transactions\Pages\ListTransactions;
use App\Jobs\DeliverCallback;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\ReversalRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Payments\Exceptions\ProviderException;
use App\Payments\Mpesa\MpesaAccountConnector;
use App\Payments\Mpesa\MpesaReversalService;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MpesaReversalTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected User $user;
    protected PaymentAccount $account;
    protected Transaction $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake([DeliverCallback::class]);

        $this->merchant = Merchant::create(['name' => 'Duka', 'email' => 'd@example.com', 'status' => 'active']);
        $this->user = User::factory()->create(['merchant_id' => $this->merchant->id, 'email_verified_at' => now()]);
        $this->account = app(MpesaAccountConnector::class)->connect($this->merchant, [
            'account_type' => 'paybill', 'display_name' => 'Shop', 'external_account_reference' => '600984',
            'environment' => 'sandbox', 'consumer_key' => 'k', 'consumer_secret' => 's',
            'initiator_name' => 'testapi', 'security_credential' => 'ENCRYPTED==',
        ]);

        $this->postJson('/api/c2b/'.$this->account->route_key.'/confirmation', [
            'TransactionType' => 'Pay Bill', 'TransID' => 'TKA1REV001', 'TransTime' => '20261102101530',
            'TransAmount' => '250.00', 'BusinessShortCode' => '600984', 'BillRefNumber' => 'INV1', 'MSISDN' => '2547***1',
        ])->assertOk();
        $this->payment = Transaction::where('provider_transaction_id', 'TKA1REV001')->sole();

        Http::fake([
            'sandbox.safaricom.co.ke/oauth/*' => Http::response(['access_token' => 'tok']),
            'sandbox.safaricom.co.ke/mpesa/reversal/v1/request' => Http::response([
                'OriginatorConversationID' => 'OC1', 'ConversationID' => 'AG_1', 'ResponseCode' => '0', 'ResponseDescription' => 'Accept the service request successfully.',
            ]),
        ]);
    }

    protected function sendResult(ReversalRequest $req, int $code, ?string $receipt = 'TKB9REV999'): void
    {
        $this->postJson('/api/rv/'.$req->callback_token.'/result', ['Result' => array_filter([
            'ResultType' => 0, 'ResultCode' => $code, 'ResultDesc' => $code ? 'The transaction is already reversed.' : 'The service request is processed successfully.',
            'OriginatorConversationID' => 'OC1', 'ConversationID' => 'AG_1', 'TransactionID' => $receipt,
        ], fn ($v) => $v !== null)])->assertOk()->assertJson(['ResultCode' => 0]);
    }

    public function test_reversal_is_sent_to_safaricom_and_completed_by_callback(): void
    {
        $req = app(MpesaReversalService::class)->request($this->payment, $this->user, 'Paid twice');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/mpesa/reversal/v1/request')
            && $r['TransactionID'] === 'TKA1REV001'
            && $r['Amount'] === '250'
            && $r['ReceiverParty'] === '600984'
            && $r['Initiator'] === 'testapi'
            && $r['SecurityCredential'] === 'ENCRYPTED=='
            && str_contains($r['ResultURL'], '/api/rv/'.$req->callback_token.'/result'));

        // Nothing changes until Safaricom confirms.
        $this->assertSame('pending', $req->fresh()->status);
        $this->assertSame('succeeded', $this->payment->fresh()->status);

        $this->sendResult($req, 0);
        $this->sendResult($req, 0); // duplicate callback is harmless

        $this->assertSame('succeeded', $req->fresh()->status);
        $this->assertSame('reversed', $this->payment->fresh()->status);
        $reversal = Transaction::where('type', 'reversal')->sole();
        $this->assertSame('TKB9REV999', $reversal->provider_transaction_id);
        $this->assertSame($this->payment->id, $reversal->reverses_transaction_id);
    }

    public function test_failed_result_leaves_payment_untouched(): void
    {
        $req = app(MpesaReversalService::class)->request($this->payment, $this->user, 'Wrong account');
        $this->sendResult($req, 2001, null);

        $this->assertSame('failed', $req->fresh()->status);
        $this->assertSame('succeeded', $this->payment->fresh()->status);
        $this->assertSame(0, Transaction::where('type', 'reversal')->count());
    }

    public function test_timeout_marks_request(): void
    {
        $req = app(MpesaReversalService::class)->request($this->payment, $this->user, 'Wrong account');
        $this->postJson('/api/rv/'.$req->callback_token.'/timeout', ['Result' => ['ResultCode' => 1]])->assertOk();

        $this->assertSame('timeout', $req->fresh()->status);
        $this->assertSame('succeeded', $this->payment->fresh()->status);
    }

    public function test_second_reversal_blocked_while_pending(): void
    {
        app(MpesaReversalService::class)->request($this->payment, $this->user, 'Paid twice');

        $this->expectException(ProviderException::class);
        app(MpesaReversalService::class)->request($this->payment, $this->user, 'Paid twice');
    }

    public function test_missing_initiator_credentials_gives_clear_error(): void
    {
        $cred = $this->account->credentials;
        $cred->update(['secrets' => ['consumer_key' => 'k', 'consumer_secret' => 's']]);

        try {
            app(MpesaReversalService::class)->request($this->payment->fresh(), $this->user, 'Paid twice');
            $this->fail('Expected an exception');
        } catch (ProviderException $e) {
            $this->assertStringContainsString('initiator', $e->getMessage());
        }

        $this->assertSame('failed', ReversalRequest::sole()->status);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'reversal'));
    }

    public function test_merchant_can_reverse_from_transactions_table(): void
    {
        $this->actingAs($this->user);
        Filament::setCurrentPanel('merchant');

        Livewire::test(ListTransactions::class)
            ->callTableAction('reverse', $this->payment, data: ['reason' => 'Paid twice'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('pending', ReversalRequest::sole()->status);
    }
}
