<?php

namespace App\Jobs;

use App\Models\ProviderEvent;
use App\Payments\PaymentIngestor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessProviderEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public array $backoff = [5, 30, 120, 600];

    public function __construct(public string $eventId) {}

    public function handle(PaymentIngestor $ingestor): void
    {
        $event = ProviderEvent::find($this->eventId);

        if ($event) {
            $ingestor->process($event);
        }
    }
}
