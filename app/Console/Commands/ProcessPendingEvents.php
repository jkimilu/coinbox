<?php

namespace App\Console\Commands;

use App\Jobs\DeliverCallback;
use App\Jobs\ProcessProviderEvent;
use App\Models\CallbackDelivery;
use App\Models\ProviderEvent;
use Illuminate\Console\Command;

/**
 * Safety net: re-queues provider events and webhook deliveries that were stored but never
 * processed (e.g. a worker crashed or the queue was lost).
 */
class ProcessPendingEvents extends Command
{
    protected $signature = 'coinbox:sweep';

    protected $description = 'Re-queue stuck provider events and overdue webhook deliveries';

    public function handle(): int
    {
        $events = ProviderEvent::query()
            ->where('processing_status', 'received')
            ->where('event_type', 'c2b.confirmation')
            ->where('created_at', '<', now()->subMinutes(2))
            ->limit(500)->pluck('id');
        $events->each(fn ($id) => ProcessProviderEvent::dispatch($id));

        $deliveries = CallbackDelivery::query()
            ->where('status', 'pending')
            ->where('next_attempt_at', '<', now()->subMinutes(10))
            ->limit(500)->pluck('id');
        $deliveries->each(fn ($id) => DeliverCallback::dispatch($id));

        $this->info("Re-queued {$events->count()} events and {$deliveries->count()} deliveries.");

        return self::SUCCESS;
    }
}
