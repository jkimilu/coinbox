@php
    $tone = [
        'critical' => ['ring-danger-600/20 bg-danger-50 dark:bg-danger-400/10', 'text-danger-600 dark:text-danger-400', 'Critical'],
        'warning' => ['ring-warning-600/20 bg-warning-50 dark:bg-warning-400/10', 'text-warning-600 dark:text-warning-400', 'Warning'],
        'info' => ['ring-gray-950/5 bg-white dark:bg-white/5 dark:ring-white/10', 'text-gray-500 dark:text-gray-400', 'Info'],
    ];
    $critical = collect($items)->where(0, 'critical')->count();
@endphp
<x-filament-widgets::widget>
    <div class="cb-card p-5 sm:p-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="cb-display text-xl font-bold text-gray-950 dark:text-white">Needs attention</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ count($items) ? count($items).' '.str('item')->plural(count($items)).($critical ? ', '.$critical.' critical' : '') : 'Everything is running normally.' }}</p>
            </div>
            <span class="text-xs text-gray-400">Updates every 30s</span>
        </div>

        @if (count($items))
            <ul class="mt-5 grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($items as [$level, $count, $title, $hint, $url, $icon])
                    <li>
                        <a @if ($url) href="{{ $url }}" @endif class="group flex h-full items-start gap-3 rounded-xl p-4 ring-1 ring-inset transition {{ $tone[$level][0] }} {{ $url ? 'hover:-translate-y-px hover:shadow-md' : '' }}">
                            <x-filament::icon :icon="$icon" class="mt-0.5 size-5 shrink-0 {{ $tone[$level][1] }}" />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline justify-between gap-3">
                                    <span class="font-semibold text-gray-950 dark:text-white">{{ $title }}</span>
                                    <span class="cb-display cb-num text-2xl font-bold {{ $tone[$level][1] }}">{{ number_format($count) }}</span>
                                </span>
                                <span class="mt-0.5 block text-xs text-gray-600 dark:text-gray-300">{{ $hint }}</span>
                                <span class="sr-only">{{ $tone[$level][2] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mt-5 flex items-center gap-3 rounded-xl bg-success-50 p-4 text-success-800 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-300">
                <x-filament::icon icon="heroicon-m-check-badge" class="size-6" />
                <p class="text-sm"><strong>All clear.</strong> No stuck payments, failing webhooks, overdue invoices or unanswered tickets.</p>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
