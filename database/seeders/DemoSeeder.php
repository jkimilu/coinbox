<?php

namespace Database\Seeders;

use App\Jobs\ProcessProviderEvent;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\ProviderEvent;
use App\Models\User;
use App\Payments\Mpesa\MpesaAccountConnector;
use Illuminate\Database\Seeder;

/** Local demo data: php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        User::query()->updateOrCreate(['email' => 'admin@coinbox.test'], [
            'name' => 'Platform Admin', 'password' => 'Admin@12345', 'is_admin' => true, 'email_verified_at' => now(),
        ]);

        $merchant = Merchant::query()->firstOrCreate(['email' => 'merchant@coinbox.test'], [
            'name' => 'Demo Duka Ltd', 'phone' => '254700000000', 'status' => 'active',
        ]);

        User::query()->updateOrCreate(['email' => 'merchant@coinbox.test'], [
            'name' => 'Demo Merchant', 'password' => 'Merchant@12345', 'merchant_id' => $merchant->id, 'email_verified_at' => now(),
        ]);

        // Safaricom's public sandbox shortcode.
        $account = PaymentAccount::query()->where('external_account_reference', '600999')->first()
            ?? app(MpesaAccountConnector::class)->connect($merchant, [
                'account_type' => 'paybill', 'display_name' => 'Sandbox PayBill', 'external_account_reference' => '600999',
                'environment' => 'sandbox', 'consumer_key' => 'replace-me', 'consumer_secret' => 'replace-me',
            ]);

        foreach ([['DEMO0001', '2500.00', 'INV-001'], ['DEMO0002', '750.00', 'INV-002'], ['DEMO0003', '12000.00', 'INV-003']] as $i => [$id, $amt, $ref]) {
            $event = ProviderEvent::query()->firstOrCreate(
                ['provider_id' => $account->provider_id, 'event_type' => 'c2b.confirmation', 'idempotency_key' => $id],
                ['payment_account_id' => $account->id, 'source_ip' => '127.0.0.1', 'processing_status' => 'received', 'payload' => [
                    'TransactionType' => 'Pay Bill', 'TransID' => $id, 'TransTime' => now('Africa/Nairobi')->subHours($i * 5)->format('YmdHis'),
                    'TransAmount' => $amt, 'BusinessShortCode' => '600999', 'BillRefNumber' => $ref,
                    'MSISDN' => '2547****'.(100 + $i), 'FirstName' => 'TEST', 'LastName' => 'CUSTOMER'.($i + 1),
                ]],
            );
            ProcessProviderEvent::dispatchSync($event->id);
        }
    }
}
