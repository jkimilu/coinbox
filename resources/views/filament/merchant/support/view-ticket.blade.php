<x-filament-panels::page>
    @php $ticket = $this->record->load('messages'); @endphp
    <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div class="cb-card p-5 sm:p-6">
            @include('filament.support.thread', ['ticket' => $ticket, 'staffView' => false])

            <form wire:submit="sendReply" class="mt-6 border-t border-gray-100 pt-5 dark:border-white/10">
                <label for="reply" class="text-sm font-semibold text-gray-900 dark:text-white">Reply</label>
                <textarea id="reply" wire:model="reply" rows="4" placeholder="Write a reply…"
                    class="mt-2 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm ring-1 ring-gray-200 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10"></textarea>
                @error('reply')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                <div class="mt-3 flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500">Replying reopens the ticket. We’ll answer by email.</p>
                    <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled">Send reply</x-filament::button>
                </div>
            </form>
        </div>

        <aside class="cb-card h-fit space-y-4 p-5 text-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</p>
                <x-filament::badge class="mt-1" :color="$ticket->status === 'awaiting_customer' ? 'success' : \App\Models\Ticket::statusColor($ticket->status)">
                    {{ $ticket->status === 'awaiting_customer' ? 'Reply received' : \App\Models\Ticket::STATUSES[$ticket->status] }}
                </x-filament::badge>
            </div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reference</p><p class="cb-num mt-1 font-mono font-semibold">{{ $ticket->reference() }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Priority</p><p class="mt-1">{{ ucfirst($ticket->priority) }}</p></div>
            <div class="border-t border-gray-100 pt-4 text-gray-500 dark:border-white/10">Prefer email? Write to <a href="mailto:{{ $ticket->mailboxAddress() }}" class="font-semibold text-primary-700">{{ $ticket->mailboxAddress() }}</a> and mention {{ $ticket->reference() }}.</div>
        </aside>
    </div>
</x-filament-panels::page>
