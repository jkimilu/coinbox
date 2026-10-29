<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use Filament\Widgets\Widget;

class SupportQueue extends Widget
{
    protected string $view = 'filament.admin.widgets.support-queue';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        return [
            'tickets' => TicketResource::needsReplyQuery()->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->oldest('last_customer_reply_at')->limit(6)->get(),
            'openTotal' => Ticket::query()->whereIn('status', ['open', 'awaiting_customer'])->count(),
            'resolvedWeek' => Ticket::query()->where('resolved_at', '>=', now()->subDays(7))->count(),
            'inboxUrl' => TicketResource::getUrl(),
        ];
    }
}
