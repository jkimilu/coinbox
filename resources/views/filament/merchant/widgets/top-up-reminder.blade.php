<x-filament-widgets::widget>
    <div role="status" class="flex flex-col gap-3 rounded-2xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-900 sm:flex-row sm:items-center sm:justify-between dark:border-warning-400/30 dark:bg-warning-400/10 dark:text-warning-200">
        <div class="flex items-start gap-3">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-warning-600 dark:text-warning-400" />
            <p>
                <strong>Top up your CoinBox float: KES {{ number_format($advice['top_up'], 2) }} recommended.</strong>
                Balance KES {{ number_format($advice['balance'], 2) }}{{ $advice['outstanding'] > 0 ? ', KES '.number_format($advice['outstanding'], 2).' owed' : '' }}.
                Pay PayBill <span class="cb-num font-mono font-semibold">{{ $paybill }}</span>, Account <span class="cb-num font-mono font-semibold">{{ $merchant->account_number }}</span>.
            </p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            {{ $this->payWithMpesaAction }}
            <x-filament::button tag="a" href="{{ \App\Filament\Merchant\Resources\Billing\BillingResource::getUrl() }}" color="gray" size="sm" outlined>View billing</x-filament::button>
        </div>
    </div>
    @if ($pending)
        <div wire:poll.4s="checkPendingStk" class="mt-3 flex items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 p-3 text-sm text-primary-900 dark:border-primary-400/30 dark:bg-primary-400/10 dark:text-primary-100">
            <x-filament::loading-indicator class="size-5 text-primary-600" />
            <span><strong>Check your phone.</strong> {{ $pendingMessage }}</span>
        </div>
    @endif
    <x-filament-actions::modals />
</x-filament-widgets::widget>
