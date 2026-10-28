<?php

namespace App\Console\Commands;

use App\Billing\BillingService;
use Illuminate\Console\Command;

class LowFloatReminders extends Command
{
    protected $signature = 'coinbox:low-float';

    protected $description = 'Email merchants whose float should be topped up';

    public function handle(BillingService $billing): int
    {
        $this->info('Sent '.$billing->notifyLowFloat().' reminder(s).');

        return self::SUCCESS;
    }
}
