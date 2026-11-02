@if ($stk)
    <div wire:poll.4s="checkPendingCollection" role="status" class="mt-4 flex items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm text-primary-900 dark:border-primary-400/30 dark:bg-primary-400/10 dark:text-primary-100">
        <x-filament::loading-indicator class="size-5 text-primary-600" />
        <span>
            @if ($stk->status === 'succeeded')
                <strong>Payment confirmed.</strong> Adding KES {{ number_format($stk->amount) }} from {{ $stk->maskedPhone() }} to your transactions…
            @else
                <strong>Waiting for the customer.</strong>
                KES {{ number_format($stk->amount) }} prompt sent to {{ $stk->maskedPhone() }}{{ $stk->account_reference && $stk->account_reference !== 'Payment' ? ', account '.$stk->account_reference : '' }}.
            @endif
        </span>
    </div>
@endif
