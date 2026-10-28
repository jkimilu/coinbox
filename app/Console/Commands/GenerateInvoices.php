<?php

namespace App\Console\Commands;

use App\Billing\BillingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateInvoices extends Command
{
    protected $signature = 'coinbox:invoices {--month= : YYYY-MM, defaults to last month}';

    protected $description = 'Issue monthly CoinBox fee invoices and settle them from merchant floats';

    public function handle(BillingService $billing): int
    {
        $month = $this->option('month') ? CarbonImmutable::createFromFormat('Y-m', $this->option('month'), 'Africa/Nairobi') : null;
        $this->info('Issued '.$billing->generateForMonth($month).' invoice(s).');

        return self::SUCCESS;
    }
}
