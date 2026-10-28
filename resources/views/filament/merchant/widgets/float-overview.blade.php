@php
    $kes = fn ($v) => 'KES '.number_format((float) $v, 2);
    $needsTopUp = $advice['top_up'] > 0;
    $muted = 'color:var(--gray-500);font-size:.875rem;';
    $big = 'margin-top:.25rem;font-size:1.875rem;font-weight:700;letter-spacing:-.02em;';
    $mono = 'font-family:ui-monospace,Menlo,Consolas,monospace;font-weight:700;';
@endphp
<x-filament-widgets::widget>
    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))">
        <x-filament::section>
            <p style="{{ $muted }}font-weight:500">Float balance</p>
            <p style="{{ $big }}{{ $needsTopUp ? 'color:var(--warning-600)' : '' }}">{{ $kes($advice['balance']) }}</p>
            <p style="{{ $muted }}margin-top:.5rem">Prepaid with CoinBox. Invoices are paid from it automatically.</p>
            @if ($advice['outstanding'] > 0)
                <p style="margin-top:.5rem;font-size:.875rem;font-weight:600;color:var(--danger-600)">{{ $kes($advice['outstanding']) }} owed on open invoices</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <p style="{{ $muted }}font-weight:500">This month so far</p>
            <p style="{{ $big }}">{{ $kes($advice['month_fee_so_far']) }}</p>
            <p style="{{ $muted }}margin-top:.5rem">
                Fee on {{ $kes($advice['month_volume']) }} processed: {{ $fee }} of the month’s volume once it reaches {{ $threshold }}.
                Projected for the month: <strong>{{ $kes($advice['projected_fee']) }}</strong>.
            </p>
        </x-filament::section>

        <x-filament::section>
            <p style="{{ $muted }}font-weight:500">Top up your float</p>
            <table style="margin-top:.5rem;font-size:.875rem;border-collapse:collapse">
                <tr><td style="color:var(--gray-500);padding:.2rem 1.25rem .2rem 0">PayBill</td><td style="{{ $mono }}font-size:1.1rem">{{ $paybill }}</td></tr>
                <tr><td style="color:var(--gray-500);padding:.2rem 1.25rem .2rem 0">Account</td><td style="{{ $mono }}font-size:1.1rem">{{ $merchant->account_number }}</td></tr>
            </table>
            <p style="{{ $muted }}margin-top:.75rem;font-size:.8rem">To pay a specific invoice, use Account <span style="{{ $mono }}">{{ $merchant->account_number }}#INV001</span> with your invoice number.</p>
        </x-filament::section>
    </div>

    @if ($needsTopUp)
        <div style="margin-top:1rem;border-radius:.75rem;border:1px solid var(--warning-300);background:color-mix(in oklab,var(--warning-50) 85%,transparent);padding:1rem;font-size:.875rem;color:var(--warning-800)">
            <strong>We recommend topping up {{ $kes($advice['top_up']) }}.</strong>
            Keep at least {{ $kes($advice['recommended_float']) }} in your float{{ $advice['outstanding'] > 0 ? ', plus what’s owed,' : '' }} so invoices are paid automatically.
            Pay via M-Pesa to PayBill <span style="{{ $mono }}">{{ $paybill }}</span>, Account <span style="{{ $mono }}">{{ $merchant->account_number }}</span>.
            Your customers’ payments are not affected.
        </div>
    @else
        <div style="margin-top:1rem;border-radius:.75rem;border:1px solid var(--success-300);background:color-mix(in oklab,var(--success-50) 85%,transparent);padding:1rem;font-size:.875rem;color:var(--success-800)">
            Your float covers your expected fees. Nothing to do.
        </div>
    @endif
</x-filament-widgets::widget>
