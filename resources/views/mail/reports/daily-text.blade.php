@php $kes = fn ($v) => 'KES '.number_format((float) $v, 2); $t = $report['totals']; @endphp
CoinBox daily collections: {{ $report['date']->format('l, j F Y') }}
{{ $report['merchant']->name }}

Net collected: {{ $kes($t['net']) }}@if ($report['change_percent'] !== null) ({{ $report['change_percent'] >= 0 ? '+' : '' }}{{ $report['change_percent'] }}% vs the day before)@endif

Received: {{ $kes($t['received']) }} ({{ $t['received_count'] }} payments)
Reversed: {{ $kes($t['reversed']) }} ({{ $t['reversed_count'] }})

@foreach ($report['shortcodes'] as $sc)
{{ $sc['label'] }} · {{ $sc['name'] }}: {{ $kes($sc['totals']['net']) }}
@foreach ($sc['lines'] as $l)
  - {{ $l['label'] }}: {{ $l['received_count'] }} paid, {{ $kes($l['received']) }}@if ($l['reversed_count']), reversed {{ $kes($l['reversed']) }}@endif, net {{ $kes($l['net']) }}
@endforeach

@endforeach
@if ($report['reversals']->isNotEmpty())
Reversals:
@foreach ($report['reversals'] as $r)
  - {{ $r->reverses?->provider_transaction_id }} reversed {{ $r->paid_at->setTimezone('Africa/Nairobi')->format('g:i a') }}: -{{ $kes($r->amount) }}@if (! empty($r->metadata['reason'])) ({{ $r->metadata['reason'] }})@endif

@endforeach
@endif

View transactions: {{ $url }}
Stop daily reports: {{ $unsubscribeUrl }}
