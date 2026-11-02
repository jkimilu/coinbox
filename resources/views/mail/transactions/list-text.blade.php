@php $kes = fn ($v) => 'KES '.number_format((float) $v, 2); @endphp
CoinBox transactions: {{ $merchantName }}
{{ $filters ? implode(' · ', $filters) : 'All transactions' }}

Received: {{ $kes($totals['received']) }} ({{ $totals['received_count'] }})
Reversed: {{ $kes($totals['reversed']) }} ({{ $totals['reversed_count'] }})
Net: {{ $kes($totals['net']) }}

@foreach ($rows as $r)
{{ $r->paid_at->setTimezone('Africa/Nairobi')->format('Y-m-d H:i') }}  {{ $r->provider_transaction_id }}  {{ $r->type === 'reversal' ? '-' : '' }}{{ $kes($r->amount) }}  {{ $r->accountRoute?->code }} {{ $r->merchant_reference }}
@endforeach
@if ($count > $rows->count())

Showing {{ $rows->count() }} of {{ $count }}.{{ $hasAttachment ? ' All rows are in the attached Excel file.' : '' }}
@endif

{{ $url }}
