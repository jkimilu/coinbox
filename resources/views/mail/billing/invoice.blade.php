<x-mail::message>
# Your {{ $i->periodLabel() }} invoice

Hi {{ $first }},

Here’s your CoinBox fee for **{{ $i->periodLabel() }}**: {{ $rate }}% of **KES {{ number_format((float) $i->processed_volume, 2) }}** processed across {{ number_format($i->payment_count) }} payments.

<x-mail::table>
| Invoice | Fee | Status |
|:--|--:|--:|
| {{ $i->number }} | **KES {{ number_format((float) $i->amount, 2) }}** | {{ $i->status === 'paid' ? 'Paid' : 'Due '.$i->due_at->format('j M Y') }} |
</x-mail::table>

@if ($i->status === 'paid')
<x-mail::panel>
**Paid from your float.** Nothing to do. Your float balance is now KES {{ number_format($balance, 2) }}.
</x-mail::panel>
@else
<x-mail::panel>
**Pay KES {{ number_format($i->outstanding(), 2) }} by M-Pesa**<br>
PayBill: **{{ $i->payBill() }}**<br>
Account: **{{ $i->paymentReference() }}**
</x-mail::panel>

You can also pay it in one tap from your float, or with an M-Pesa prompt, on the Billing page.
@endif

<x-mail::button :url="$url">View billing</x-mail::button>

Tip: keep a float with us (PayBill {{ $i->payBill() }}, Account {{ $i->merchant->account_number }}) and future invoices are paid automatically.

The CoinBox team
</x-mail::message>
