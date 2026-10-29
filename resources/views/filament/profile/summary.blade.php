@php
    [$levelLabel, $levelColor] = $level;
    $bar = ['danger' => 'bg-danger-500', 'warning' => 'bg-warning-500', 'success' => 'bg-success-500'][$levelColor];
    $text = ['danger' => 'text-danger-600 dark:text-danger-400', 'warning' => 'text-warning-600 dark:text-warning-400', 'success' => 'text-success-600 dark:text-success-400'][$levelColor];
@endphp
<div class="space-y-6 lg:sticky lg:top-24">
    {{-- Identity --}}
    <div class="cb-card overflow-hidden">
        <div class="h-20 bg-gradient-to-br from-primary-500 to-primary-700"></div>
        <div class="-mt-10 px-6 pb-6">
            <img src="{{ $avatar }}" alt="" class="size-20 rounded-full ring-4 ring-white dark:ring-gray-900">
            <h2 class="cb-display mt-3 text-xl font-bold text-gray-950 dark:text-white">{{ $user->name }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>

            <dl class="mt-5 space-y-3 border-t border-gray-100 pt-4 text-sm dark:border-white/10">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500">{{ $roleLabel }}</dt>
                    <dd class="truncate font-medium text-gray-900 dark:text-white">{{ $role }}</dd>
                </div>
                @if ($merchantNo)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-gray-500">Merchant no.</dt>
                        <dd class="cb-num font-mono font-semibold text-gray-900 dark:text-white">{{ $merchantNo }}</dd>
                    </div>
                @endif
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500">Email</dt>
                    <dd>
                        @if ($user->hasVerifiedEmail())
                            <span class="inline-flex items-center gap-1 font-medium text-success-600 dark:text-success-400"><x-filament::icon icon="heroicon-m-check-badge" class="size-4" />Verified</span>
                        @else
                            <span class="inline-flex items-center gap-1 font-medium text-warning-600"><x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-4" />Not verified</span>
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500">Member since</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $user->created_at?->format('F Y') }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Security score --}}
    <div class="cb-card p-6">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-950 dark:text-white">Account security</h3>
            <span class="text-sm font-bold {{ $text }}">{{ $levelLabel }}</span>
        </div>
        <div class="mt-3 flex gap-1.5" role="img" aria-label="Security {{ $levelLabel }}, {{ $score }} of 3">
            @for ($i = 1; $i <= 3; $i++)
                <span class="h-1.5 flex-1 rounded-full {{ $i <= $score ? $bar : 'bg-gray-200 dark:bg-white/10' }}"></span>
            @endfor
        </div>
        <ul class="mt-5 space-y-3.5">
            @foreach ($checks as [$label, $ok, $hint])
                <li class="flex items-start gap-3">
                    @if ($ok)
                        <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-success-500 text-white"><x-filament::icon icon="heroicon-m-check" class="size-3.5" /></span>
                    @else
                        <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border-2 border-gray-300 dark:border-white/20"></span>
                    @endif
                    <span>
                        <span class="block text-sm font-medium {{ $ok ? 'text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-300' }}">{{ $label }}</span>
                        <span class="block text-xs text-gray-500">{{ $hint }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
        @if ($score < 3)
            <p class="mt-5 rounded-lg bg-gray-50 px-3 py-2.5 text-xs leading-relaxed text-gray-600 dark:bg-white/5 dark:text-gray-300">
                CoinBox shows your payment data and API keys. Turning on two-factor authentication and adding a passkey keeps them safe even if your password leaks.
            </p>
        @endif
    </div>
</div>
