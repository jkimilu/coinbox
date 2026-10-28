<x-filament-widgets::widget>
    <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;border-radius:.75rem;border:1px solid var(--warning-300);background:color-mix(in oklab,var(--warning-50) 85%,transparent);padding:1rem;font-size:.875rem;color:var(--warning-800)">
        <p style="flex:1 1 20rem">
            <strong>Top up your CoinBox float: KES {{ number_format($advice['top_up'], 2) }} recommended.</strong>
            Balance KES {{ number_format($advice['balance'], 2) }}{{ $advice['outstanding'] > 0 ? ', KES '.number_format($advice['outstanding'], 2).' owed' : '' }}.
            Pay PayBill <strong style="font-family:ui-monospace,monospace">{{ $paybill }}</strong>, Account <strong style="font-family:ui-monospace,monospace">{{ $merchant->account_number }}</strong>.
        </p>
        <x-filament::button tag="a" href="{{ \App\Filament\Merchant\Resources\Billing\BillingResource::getUrl() }}" color="warning" size="sm">View billing</x-filament::button>
    </div>
</x-filament-widgets::widget>
