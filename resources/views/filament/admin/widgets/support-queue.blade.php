<x-filament-widgets::widget>
    <div class="cb-card flex h-full flex-col p-5">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Support queue</h2>
                <p class="text-sm text-gray-500">{{ $openTotal }} open · {{ $resolvedWeek }} resolved this week</p>
            </div>
            <x-filament::link :href="$inboxUrl" size="sm">Inbox</x-filament::link>
        </div>
        <ul class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
            @forelse ($tickets as $t)
                <li>
                    <a href="{{ \App\Filament\Admin\Resources\Tickets\TicketResource::getUrl('view', ['record' => $t]) }}" class="-mx-2 flex items-start gap-3 rounded-lg px-2 py-2.5 hover:bg-gray-50 dark:hover:bg-white/5">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ in_array($t->priority, ['urgent', 'high']) ? 'bg-danger-500' : 'bg-warning-500' }}" title="{{ ucfirst($t->priority) }} priority"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ $t->subject }}</span>
                            <span class="block truncate text-xs text-gray-500">{{ $t->reference() }} · {{ $t->requester_name }}{{ $t->company ? ' · '.$t->company : '' }} · {{ $t->source === 'contact_form' ? 'website' : 'merchant' }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-gray-400">{{ optional($t->last_customer_reply_at)->shortRelativeDiffForHumans() }}</span>
                    </a>
                </li>
            @empty
                <li class="flex flex-col items-center py-8 text-center text-sm text-gray-500">
                    <x-filament::icon icon="heroicon-o-inbox" class="mb-2 size-8 text-gray-300" />Inbox zero. Nothing waiting for a reply.
                </li>
            @endforelse
        </ul>
    </div>
</x-filament-widgets::widget>
