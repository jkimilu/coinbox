<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\CallbackDeliveries\CallbackDeliveryResource;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\PaymentAccounts\PaymentAccountResource;
use App\Filament\Admin\Resources\ProviderEvents\ProviderEventResource;
use App\Filament\Admin\Resources\ReconciliationRuns\ReconciliationRunResource;
use App\Filament\Admin\Resources\Tickets\TicketResource;
use App\Models\BillingInvoice;
use App\Models\CallbackDelivery;
use App\Models\PaymentAccount;
use App\Models\ProviderEvent;
use App\Models\ReconciliationRun;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/** Everything an operator should act on, most severe first. Hidden items are zero. */
class AttentionNeeded extends Widget
{
    protected string $view = 'filament.admin.widgets.attention-needed';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    public static function items(): array
    {
        $tickets = TicketResource::needsReplyQuery();
        $urgentTickets = (clone $tickets)->whereIn('priority', ['urgent', 'high'])->count();
        $ticketCount = (clone $tickets)->count();
        $oldestTicket = (clone $tickets)->min('last_customer_reply_at');

        $failedEvents = ProviderEvent::query()->where('processing_status', 'failed')->where('created_at', '>=', now()->subDays(7))->count();
        $stuckEvents = ProviderEvent::query()->where('processing_status', 'received')->where('event_type', 'c2b.confirmation')->where('created_at', '<', now()->subMinutes(5))->count();
        $rejected = ProviderEvent::query()->where('processing_status', 'rejected')->where('created_at', '>=', now()->subDay())->count();
        $failedHooks = CallbackDelivery::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count();
        $retrying = CallbackDelivery::query()->where('status', 'pending')->where('attempts', '>', 0)->count();
        $unregistered = PaymentAccount::query()->whereNull('urls_registered_at')->where('status', '!=', 'disabled')->where('created_at', '<', now()->subHour())->count();
        $overdue = BillingInvoice::query()->where('status', 'open')->whereDate('due_at', '<', now('Africa/Nairobi')->toDateString())->get();
        $failedJobs = DB::table('failed_jobs')->count();
        $backlog = DB::table('jobs')->where('available_at', '<', now()->subMinutes(5)->timestamp)->count();
        $recon = ReconciliationRun::query()->where('status', 'completed')->where('created_at', '>=', now()->subDays(7))
            ->get()->sum(fn ($r) => $r->missing_in_gateway_count + $r->missing_at_provider_count + $r->amount_mismatch_count);

        $kes = fn ($v) => 'KES '.number_format($v, 2);

        return collect([
            ['critical', $stuckEvents, 'Payments stuck in processing', 'Safaricom notifications received but not processed for 5+ minutes. Is the queue worker running?', ProviderEventResource::getUrl(), 'heroicon-m-bolt-slash'],
            ['critical', $backlog, 'Queue backlog', 'Jobs waiting more than 5 minutes. Start or scale queue:work.', null, 'heroicon-m-queue-list'],
            ['critical', $failedEvents, 'Failed provider events (7d)', 'Notifications that errored while being recorded. Review and reprocess.', ProviderEventResource::getUrl(), 'heroicon-m-x-circle'],
            [$urgentTickets ? 'critical' : 'warning', $ticketCount, 'Tickets waiting for a reply', ($urgentTickets ? $urgentTickets.' high/urgent. ' : '').($oldestTicket ? 'Oldest waiting since '.\Illuminate\Support\Carbon::parse($oldestTicket)->diffForHumans() : ''), TicketResource::getUrl(), 'heroicon-m-chat-bubble-left-ellipsis'],
            ['warning', $failedHooks, 'Webhooks failed (7d)', 'Merchant endpoints rejected all 10 attempts. Merchants may be missing payments.', CallbackDeliveryResource::getUrl(), 'heroicon-m-signal-slash'],
            ['warning', $failedJobs, 'Failed background jobs', 'See php artisan queue:failed.', null, 'heroicon-m-exclamation-triangle'],
            ['warning', $rejected, 'Rejected callbacks (24h)', 'Shortcode mismatch or blocked IP. Possible misconfiguration or spoofing.', ProviderEventResource::getUrl(), 'heroicon-m-shield-exclamation'],
            ['warning', $overdue->count(), 'Overdue invoices', $overdue->count() ? $kes($overdue->sum(fn ($i) => $i->outstanding())).' outstanding' : '', InvoiceResource::getUrl(), 'heroicon-m-document-text'],
            ['warning', $recon, 'Reconciliation discrepancies (7d)', 'Statement lines missing or mismatched.', ReconciliationRunResource::getUrl(), 'heroicon-m-scale'],
            ['info', $retrying, 'Webhooks retrying', 'Deliveries being retried automatically.', CallbackDeliveryResource::getUrl(), 'heroicon-m-arrow-path'],
            ['info', $unregistered, 'Accounts not registered', 'Connected over an hour ago but URLs never registered with Safaricom.', PaymentAccountResource::getUrl(), 'heroicon-m-link-slash'],
        ])->filter(fn ($i) => $i[1] > 0)
            ->sortBy(fn ($i) => ['critical' => 0, 'warning' => 1, 'info' => 2][$i[0]])
            ->values()->all();
    }

    protected function getViewData(): array
    {
        return ['items' => static::items()];
    }
}
