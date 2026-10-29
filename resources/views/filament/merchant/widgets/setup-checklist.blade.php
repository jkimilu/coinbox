@php $total = count($steps); $pct = (int) round($done / $total * 100); $next = collect($steps)->first(fn ($s) => ! $s[2]); @endphp
<x-filament-widgets::widget>
    <div class="cb-card p-5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="cb-display text-lg font-bold text-gray-950 dark:text-white">Finish setting up CoinBox</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $done }} of {{ $total }} done. Next: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $next[0] }}</span></p>
            </div>
            <div class="flex items-center gap-3">
                <div class="h-2 w-40 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Setup progress">
                    <div class="h-full rounded-full bg-primary-500" style="width: {{ $pct }}%"></div>
                </div>
                <span class="cb-num text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $pct }}%</span>
                <x-filament::link :href="$guide" size="sm" icon="heroicon-m-book-open">Guide</x-filament::link>
            </div>
        </div>

        <ol class="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($steps as $i => [$title, $hint, $isDone, $url])
                <li>
                    <a href="{{ $url }}" class="group flex items-start gap-3 rounded-xl p-3 transition hover:bg-gray-50 dark:hover:bg-white/5">
                        @if ($isDone)
                            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-primary-500 text-white"><x-filament::icon icon="heroicon-m-check" class="size-4" /></span>
                        @else
                            <span class="cb-num mt-0.5 grid size-6 shrink-0 place-items-center rounded-full border-2 border-gray-300 text-xs font-bold text-gray-500 dark:border-white/20">{{ $i + 1 }}</span>
                        @endif
                        <span>
                            <span class="block text-sm font-semibold {{ $isDone ? 'text-gray-400 line-through decoration-gray-300 dark:text-gray-500' : 'text-gray-900 group-hover:text-primary-700 dark:text-white' }}">{{ $title }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ol>
    </div>
</x-filament-widgets::widget>
