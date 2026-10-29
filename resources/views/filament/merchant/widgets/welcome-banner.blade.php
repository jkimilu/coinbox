<x-filament-widgets::widget>
    <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $merchant->name }} · Merchant no. <span class="cb-num font-semibold text-gray-700 dark:text-gray-200">{{ $merchant->account_number }}</span></p>
            <h1 class="cb-display mt-1 text-3xl font-bold text-gray-950 sm:text-4xl dark:text-white">{{ $greeting }}, {{ $firstName }}</h1>
            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                @if ($live > 0)
                    <span class="inline-flex items-center gap-2 font-medium text-gray-700 dark:text-gray-200">
                        <span class="cb-live size-2 rounded-full bg-primary-500"></span>
                        Receiving payments on {{ $live }} {{ str('account')->plural($live) }}
                    </span>
                @elseif ($accounts > 0)
                    <span class="inline-flex items-center gap-2 font-medium text-warning-700 dark:text-warning-400">
                        <x-filament::icon icon="heroicon-m-clock" class="size-4" /> Waiting for your first payment
                    </span>
                @else
                    <span class="inline-flex items-center gap-2 font-medium text-gray-600 dark:text-gray-300">
                        <x-filament::icon icon="heroicon-m-link" class="size-4" /> No M-Pesa account connected yet
                    </span>
                @endif
                @if ($lastPayment)
                    <span class="text-gray-500 dark:text-gray-400">Last payment {{ $lastPayment->paid_at->diffForHumans() }} · <span class="cb-num font-medium text-gray-700 dark:text-gray-200">KES {{ number_format((float) $lastPayment->amount, 2) }}</span></span>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-filament::button tag="a" :href="$urls['accounts']" icon="heroicon-m-device-phone-mobile" color="gray" outlined size="sm">Accounts</x-filament::button>
            <x-filament::button tag="a" :href="$urls['webhooks']" icon="heroicon-m-bell-alert" color="gray" outlined size="sm">Webhooks</x-filament::button>
            <x-filament::button tag="a" :href="$urls['keys']" icon="heroicon-m-command-line" color="gray" outlined size="sm">API keys</x-filament::button>
        </div>
    </div>
</x-filament-widgets::widget>
