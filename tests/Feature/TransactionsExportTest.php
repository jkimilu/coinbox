<?php

namespace Tests\Feature;

use App\Filament\Merchant\Resources\Transactions\Pages\ListTransactions;
use App\Models\Merchant;
use App\Models\User;
use App\Payments\Mpesa\MpesaAccountConnector;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class TransactionsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_respects_filters_and_merchant_scope(): void
    {
        $this->seed(DatabaseSeeder::class);
        $connect = fn (Merchant $m, string $sc) => app(MpesaAccountConnector::class)->connect($m, [
            'account_type' => 'paybill', 'display_name' => "PB {$sc}", 'external_account_reference' => $sc,
            'environment' => 'sandbox', 'consumer_key' => 'k', 'consumer_secret' => 's',
        ]);
        $pay = fn ($acct, $id, $amt, $ref) => $this->postJson('/api/c2b/'.$acct->route_key.'/confirmation', [
            'TransactionType' => 'Pay Bill', 'TransID' => $id, 'TransTime' => '20261020101530', 'TransAmount' => $amt,
            'BusinessShortCode' => $acct->external_account_reference, 'BillRefNumber' => $ref, 'MSISDN' => '2547***1', 'FirstName' => 'JANE',
        ]);

        $mine = Merchant::create(['name' => 'Mine', 'email' => 'm@example.com', 'status' => 'active']);
        $a1 = $connect($mine, '111111');
        $a2 = $connect($mine, '222222');
        $pay($a1, 'R1', '100.50', 'INV1');
        $pay($a2, 'R2', '200', 'INV2');
        $other = Merchant::create(['name' => 'Other', 'email' => 'o@example.com', 'status' => 'active']);
        $pay($connect($other, '333333'), 'R3', '999', 'X');

        $this->actingAs(User::factory()->create(['merchant_id' => $mine->id, 'email_verified_at' => now()]));
        Filament::setCurrentPanel('merchant');

        $component = Livewire::test(ListTransactions::class)
            ->assertActionVisible('export')
            ->filterTable('payment_account_id', $a1->id);

        $response = $component->instance()->exportToExcel();
        $this->assertNotNull($response);

        $reader = new Reader;
        $reader->open($response->getFile()->getPathname());
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();

        $this->assertSame('Date (EAT)', $rows[0][0]);
        $this->assertSame('R1', $rows[1][1]);                  // only the filtered account
        $this->assertEquals(100.5, $rows[1][4]);               // numeric amount
        $this->assertInstanceOf(\DateTimeInterface::class, $rows[1][0]);
        $this->assertSame('2026-10-20 10:15', $rows[1][0]->format('Y-m-d H:i')); // Nairobi wall time
        $flat = json_encode($rows);
        $this->assertStringNotContainsString('R2', $flat);
        $this->assertStringNotContainsString('R3', $flat);   // never another merchant's data
        $this->assertSame('Total', $rows[count($rows) - 1][0]);
    }
}
