<x-filament-panels::page>
    @php $ticket = $this->record->load(['messages', 'merchant']); @endphp
    <div class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            <div class="cb-card p-5 sm:p-6">
                @include('filament.support.thread', ['ticket' => $ticket, 'staffView' => true])
            </div>

            <form wire:submit="sendReply" class="cb-card space-y-4 p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Reply by email</h2>
                    <span class="text-xs text-gray-500">From CoinBox {{ $ticket->mailbox === 'sales' ? 'Sales' : 'Support' }} · replies go to {{ $ticket->mailboxAddress() }}</span>
                </div>
                {{ $this->form }}
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-filament::button type="button" color="gray" outlined icon="heroicon-m-pencil-square" wire:click="addNote" wire:loading.attr="disabled">Add internal note</x-filament::button>
                    <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled">Send reply</x-filament::button>
                </div>
            </form>
        </div>

        <aside class="space-y-6">
            <div class="cb-card space-y-3 p-5 text-sm">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Requester</h2>
                <p class="font-medium text-gray-900 dark:text-white">{{ $ticket->requester_name }}</p>
                <p><a href="mailto:{{ $ticket->requester_email }}" class="text-primary-700 hover:underline">{{ $ticket->requester_email }}</a></p>
                @if ($ticket->requester_phone)<p class="cb-num text-gray-600 dark:text-gray-300">{{ $ticket->requester_phone }}</p>@endif
                @if ($ticket->company)<p class="text-gray-600 dark:text-gray-300">{{ $ticket->company }}</p>@endif
                @if ($ticket->merchant)
                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-gray-700 dark:bg-white/5 dark:text-gray-200">Merchant no. <span class="cb-num font-semibold">{{ $ticket->merchant->account_number }}</span> · {{ ucfirst($ticket->merchant->status) }}<br>
                        <span class="text-xs text-gray-500">Float KES {{ number_format($ticket->merchant->floatBalance(), 2) }} · {{ $ticket->merchant->paymentAccounts()->count() }} account(s)</span></p>
                @else
                    <p class="text-xs text-gray-500">Not a merchant yet (website enquiry).</p>
                @endif
            </div>
            <div class="cb-card p-5">
                <h2 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">Ticket</h2>
                {{ $this->metaForm }}
            </div>
        </aside>
    </div>
</x-filament-panels::page>
