@php
    $tone = ['success' => 'text-success-700 bg-success-50 ring-success-600/20 dark:text-success-400 dark:bg-success-400/10', 'warning' => 'text-warning-700 bg-warning-50 ring-warning-600/20 dark:text-warning-400 dark:bg-warning-400/10', 'danger' => 'text-danger-700 bg-danger-50 ring-danger-600/20 dark:text-danger-400 dark:bg-danger-400/10', 'gray' => 'text-gray-600 bg-gray-50 ring-gray-500/20 dark:text-gray-300 dark:bg-white/5'];
    $webhookState = match (true) {
        $endpoints === 0 => ['No webhook', 'warning', 'heroicon-m-exclamation-triangle'],
        $failing > 0 => [$failing.' failed', 'danger', 'heroicon-m-x-circle'],
        $retrying > 0 => [$retrying.' retrying', 'warning', 'heroicon-m-arrow-path'],
        default => ['Healthy', 'success', 'heroicon-m-check-circle'],
    };
@endphp
<x-filament-widgets::widget>
    <div class="cb-card flex h-full flex-col p-5">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Integration health</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Are payment notifications reaching you?</p>

        <div class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-gray-50 p-3 dark:bg-white/5">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 whitespace-nowrap">Delivered (7d)</p>
                <p class="cb-display cb-num mt-0.5 text-2xl font-bold text-gray-950 dark:text-white">{{ $rate === null ? '—' : $rate.'%' }}</p>
                <p class="text-xs text-gray-500">{{ $total ? number_format($total).' sent to '.$endpoints.' '.str('endpoint')->plural($endpoints) : 'Nothing sent yet' }}</p>
            </div>
            <span class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset {{ $tone[$webhookState[1]] }}">
                <x-filament::icon :icon="$webhookState[2]" class="size-4" />{{ $webhookState[0] }}
            </span>
        </div>

        <p class="mt-5 text-xs font-medium uppercase tracking-wide text-gray-500">Accounts</p>
        <ul class="mt-2 divide-y divide-gray-100 dark:divide-white/5">
            @forelse ($accounts as $a)
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $a['name'] }}</p>
                        <p class="cb-num text-xs text-gray-500">{{ $a['type'] }} {{ $a['code'] }}@if ($a['sandbox']) · <span class="font-semibold text-warning-600">Sandbox</span>@endif</p>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset {{ $tone[$a['state'][1]] }}">
                        <x-filament::icon :icon="$a['state'][2]" class="size-4" />{{ $a['state'][0] }}
                    </span>
                </li>
            @empty
                <li class="py-3 text-sm text-gray-500">No accounts yet.</li>
            @endforelse
        </ul>

        <div class="mt-auto flex gap-4 pt-4 text-sm">
            <x-filament::link :href="$accountsUrl" size="sm">Manage accounts</x-filament::link>
            <x-filament::link :href="$deliveriesUrl" size="sm" color="gray">Logs</x-filament::link>
        </div>
    </div>
</x-filament-widgets::widget>
