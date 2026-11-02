@php
    $ink = '#141b16'; $text = '#333e37'; $muted = '#7c8b81'; $line = '#e7ebe8'; $soft = '#f6f8f5';
    $green = '#227f15'; $greenBright = '#2ca01c'; $greenSoft = '#eef8ec'; $red = '#c62828'; $redSoft = '#fdf0ef';
    $font = "'Inter',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $kes = fn ($v) => 'KES '.number_format((float) $v, 2);
    $kesShort = fn ($v) => 'KES '.number_format((float) $v, 0);
    $t = $report['totals'];
    $maxHour = max(1, max($report['hours']));
    $peak = $t['received'] > 0 ? array_search(max($report['hours']), $report['hours']) : null;
    $hourLabel = fn ($h) => \Carbon\Carbon::createFromTime($h)->format('ga');
    $change = $report['change_percent'];
    $base = rtrim(config('app.url'), '/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light only">
<title>Daily collections · {{ $report['date']->format('j M Y') }}</title>
<style>
@media only screen and (max-width: 600px) {
  .card { width: 100% !important; border-radius: 0 !important; }
  .pad { padding-left: 20px !important; padding-right: 20px !important; }
  .tile { display: block !important; width: 100% !important; margin-bottom: 10px !important; }
  .hide-sm { display: none !important; }
  .hero-amount { font-size: 32px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background:#f4f2ea;font-family:{{ $font }};color:{{ $text }};-webkit-text-size-adjust:none;">

{{-- Inbox preview text --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    {{ $kes($t['net']) }} collected on {{ $report['date']->format('l j M') }} · {{ number_format($t['received_count']) }} payments{{ $t['reversed_count'] ? ' · '.$t['reversed_count'].' reversed' : '' }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f2ea;">
<tr><td align="center" style="padding:28px 12px 40px;">

    {{-- Logo --}}
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;"><tr>
        <td style="padding-right:10px;vertical-align:middle;"><img src="{{ $base }}/icon-192.png" width="34" height="34" alt="" style="display:block;width:34px;height:34px;border-radius:999px;"></td>
        <td style="vertical-align:middle;font-size:20px;font-weight:800;letter-spacing:-0.02em;color:{{ $ink }};">CoinBox</td>
    </tr></table>

    <table role="presentation" class="card" width="600" cellpadding="0" cellspacing="0" style="width:600px;background:#ffffff;border:1px solid #e3e7e2;border-radius:16px;overflow:hidden;">
        <tr><td height="5" style="background:{{ $greenBright }};font-size:0;line-height:0;">&nbsp;</td></tr>

        {{-- Hero --}}
        <tr><td class="pad" style="padding:30px 36px 8px;">
            <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:{{ $green }};">Daily collections</div>
            <div style="font-size:15px;color:{{ $muted }};margin-top:4px;">{{ $report['date']->format('l, j F Y') }} · {{ $report['merchant']->name }}</div>

            <div style="margin-top:22px;font-size:13px;color:{{ $muted }};">Net collected</div>
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                <td class="hero-amount" style="font-size:38px;font-weight:800;letter-spacing:-0.03em;color:{{ $ink }};line-height:1.15;">{{ $kes($t['net']) }}</td>
                @if ($change !== null)
                    <td style="padding-left:12px;vertical-align:middle;">
                        <span style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:12.5px;font-weight:700;background:{{ $change >= 0 ? $greenSoft : $redSoft }};color:{{ $change >= 0 ? $green : $red }};">
                            {{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}%
                        </span>
                    </td>
                @endif
            </tr></table>
            <div style="font-size:13px;color:{{ $muted }};margin-top:4px;">
                @if ($change !== null)
                    vs {{ $kes($report['previous_net']) }} the day before
                @elseif ($t['received_count'] === 0)
                    No payments were recorded on this day.
                @else
                    No payments the day before to compare with.
                @endif
            </div>
        </td></tr>

        {{-- Stat tiles --}}
        <tr><td class="pad" style="padding:20px 36px 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                <td class="tile" width="33%" valign="top" style="padding:14px 16px;background:{{ $greenSoft }};border-radius:12px;">
                    <div style="font-size:12px;color:{{ $green }};font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Received</div>
                    <div style="font-size:18px;font-weight:800;color:{{ $ink }};margin-top:6px;">{{ $kesShort($t['received']) }}</div>
                    <div style="font-size:12.5px;color:{{ $muted }};">{{ number_format($t['received_count']) }} {{ \Illuminate\Support\Str::plural('payment', $t['received_count']) }}</div>
                </td>
                <td class="hide-sm" width="10" style="font-size:0;">&nbsp;</td>
                <td class="tile" width="33%" valign="top" style="padding:14px 16px;background:{{ $t['reversed_count'] ? $redSoft : $soft }};border-radius:12px;">
                    <div style="font-size:12px;color:{{ $t['reversed_count'] ? $red : $muted }};font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Reversed</div>
                    <div style="font-size:18px;font-weight:800;color:{{ $ink }};margin-top:6px;">{{ $kesShort($t['reversed']) }}</div>
                    <div style="font-size:12.5px;color:{{ $muted }};">{{ number_format($t['reversed_count']) }} {{ \Illuminate\Support\Str::plural('reversal', $t['reversed_count']) }}</div>
                </td>
                <td class="hide-sm" width="10" style="font-size:0;">&nbsp;</td>
                <td class="tile" width="33%" valign="top" style="padding:14px 16px;background:{{ $soft }};border-radius:12px;">
                    <div style="font-size:12px;color:{{ $muted }};font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Largest payment</div>
                    <div style="font-size:18px;font-weight:800;color:{{ $ink }};margin-top:6px;">{{ $report['largest'] ? $kesShort($report['largest']->amount) : '—' }}</div>
                    <div style="font-size:12.5px;color:{{ $muted }};">{{ $report['largest'] ? $report['largest']->paid_at->setTimezone('Africa/Nairobi')->format('g:i a') : 'None' }}</div>
                </td>
            </tr></table>
        </td></tr>

        {{-- Hourly chart --}}
        @if ($t['received'] > 0)
        <tr><td class="pad" style="padding:26px 36px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                <td style="font-size:15px;font-weight:700;color:{{ $ink }};">Through the day</td>
                <td align="right" style="font-size:12.5px;color:{{ $muted }};">Busiest: <strong style="color:{{ $ink }};">{{ $hourLabel($peak) }}–{{ $hourLabel(($peak + 1) % 24) }}</strong></td>
            </tr></table>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;border-bottom:1px solid {{ $line }};">
                <tr>
                    @foreach ($report['hours'] as $h => $amount)
                        @php $height = $amount > 0 ? max(4, (int) round($amount / $maxHour * 72)) : 2; @endphp
                        <td valign="bottom" align="center" height="76" style="height:76px;padding:0 1px;" title="{{ $hourLabel($h) }}: {{ $kes($amount) }}">
                            <div style="height:{{ $height }}px;line-height:{{ $height }}px;font-size:0;background:{{ $h === $peak ? $green : ($amount > 0 ? '#9fd492' : $line) }};border-radius:3px 3px 0 0;">&nbsp;</div>
                        </td>
                    @endforeach
                </tr>
            </table>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:11px;color:{{ $muted }};margin-top:6px;"><tr>
                <td width="25%">12am</td><td width="25%">6am</td><td width="25%">12pm</td><td width="25%">6pm</td>
            </tr></table>
        </td></tr>
        @endif

        {{-- Per shortcode → account --}}
        <tr><td class="pad" style="padding:28px 36px 0;">
            <div style="font-size:15px;font-weight:700;color:{{ $ink }};">By short code and account</div>
        </td></tr>
        @forelse ($report['shortcodes'] as $sc)
        <tr><td class="pad" style="padding:12px 36px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $line }};border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:12px 16px;background:{{ $soft }};border-bottom:1px solid {{ $line }};">
                        <div style="font-size:14px;font-weight:800;color:{{ $ink }};">{{ $sc['label'] }}</div>
                        <div style="font-size:12.5px;color:{{ $muted }};">{{ $sc['name'] }}</div>
                    </td>
                    <td align="right" style="padding:12px 16px;background:{{ $soft }};border-bottom:1px solid {{ $line }};white-space:nowrap;">
                        <div style="font-size:15px;font-weight:800;color:{{ $ink }};">{{ $kes($sc['totals']['net']) }}</div>
                        <div style="font-size:12px;color:{{ $muted }};">{{ number_format($sc['totals']['received_count']) }} paid{{ $sc['totals']['reversed_count'] ? ' · '.$sc['totals']['reversed_count'].' reversed' : '' }}</div>
                    </td>
                </tr>
                <tr><td colspan="2" style="padding:4px 16px 8px;">
                    @if (count($sc['lines']))
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13.5px;">
                        <tr>
                            <td style="padding:8px 0 6px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:{{ $muted }};">Account</td>
                            <td align="right" style="padding:8px 0 6px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:{{ $muted }};">Paid</td>
                            <td align="right" class="hide-sm" style="padding:8px 0 6px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:{{ $muted }};">Reversed</td>
                            <td align="right" style="padding:8px 0 6px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:{{ $muted }};">Net</td>
                        </tr>
                        @foreach ($sc['lines'] as $l)
                            @php $quiet = $l['received_count'] === 0 && $l['reversed_count'] === 0; @endphp
                            <tr style="{{ $quiet ? 'color:'.$muted.';' : '' }}">
                                <td style="padding:8px 8px 8px 0;border-top:1px solid #f0f2ef;">{{ $l['label'] }}</td>
                                <td align="right" style="padding:8px 0;border-top:1px solid #f0f2ef;white-space:nowrap;">{{ $l['received_count'] }} · {{ $kesShort($l['received']) }}</td>
                                <td align="right" class="hide-sm" style="padding:8px 0;border-top:1px solid #f0f2ef;white-space:nowrap;color:{{ $l['reversed_count'] ? $red : 'inherit' }};">{{ $l['reversed_count'] ? '−'.$kesShort($l['reversed']) : '—' }}</td>
                                <td align="right" style="padding:8px 0;border-top:1px solid #f0f2ef;white-space:nowrap;font-weight:{{ $quiet ? 400 : 700 }};color:{{ $quiet ? $muted : $ink }};">{{ $kesShort($l['net']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                    @else
                        <div style="padding:10px 0;font-size:13.5px;color:{{ $muted }};">No payments.</div>
                    @endif
                </td></tr>
            </table>
        </td></tr>
        @empty
        <tr><td class="pad" style="padding:12px 36px 0;font-size:14px;color:{{ $muted }};">No payment accounts connected yet.</td></tr>
        @endforelse

        {{-- Reversals --}}
        @if ($report['reversals']->isNotEmpty())
        <tr><td class="pad" style="padding:28px 36px 0;">
            <div style="font-size:15px;font-weight:700;color:{{ $ink }};">Reversals</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;font-size:13px;border-left:4px solid {{ $red }};background:{{ $redSoft }};border-radius:0 12px 12px 0;">
                @foreach ($report['reversals'] as $r)
                <tr><td style="padding:12px 16px;{{ $loop->first ? '' : 'border-top:1px solid #f6dcd9;' }}">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                        <td style="color:{{ $ink }};">
                            <strong>{{ $r->reverses?->provider_transaction_id ?? 'Payment' }}</strong>
                            <span style="color:{{ $muted }};"> reversed {{ $r->paid_at->setTimezone('Africa/Nairobi')->format('g:i a') }}</span>
                            <div style="font-size:12.5px;color:{{ $muted }};margin-top:2px;">
                                {{ $r->paymentAccount?->external_account_reference }}@if ($r->reverses?->accountRoute) · {{ $r->reverses->accountRoute->code }} {{ $r->reverses->accountRoute->name }}@endif
                                · receipt {{ $r->provider_transaction_id }}
                                @if (! empty($r->metadata['reason'])) · “{{ $r->metadata['reason'] }}”@endif
                            </div>
                        </td>
                        <td align="right" valign="top" style="white-space:nowrap;font-weight:800;color:{{ $red }};">−{{ $kes($r->amount) }}</td>
                    </tr></table>
                </td></tr>
                @endforeach
            </table>
        </td></tr>
        @endif

        @if ($report['pending_count'])
        <tr><td class="pad" style="padding:20px 36px 0;font-size:13.5px;color:{{ $text }};">
            ⏳ {{ $report['pending_count'] }} {{ \Illuminate\Support\Str::plural('payment', $report['pending_count']) }} from this day {{ $report['pending_count'] === 1 ? 'is' : 'are' }} still waiting for confirmation and not included above.
        </td></tr>
        @endif

        {{-- Button --}}
        <tr><td align="center" class="pad" style="padding:30px 36px 34px;">
            <a href="{{ $url }}" style="display:inline-block;background:{{ $green }};color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 28px;border-radius:999px;">View transactions</a>
            <div style="font-size:12px;color:{{ $muted }};margin-top:14px;">Totals are payments CoinBox recorded, not your M-Pesa balance. Times are Nairobi time.</div>
        </td></tr>
    </table>

    {{-- Footer --}}
    <table role="presentation" width="600" class="card" cellpadding="0" cellspacing="0" style="width:600px;">
        <tr><td align="center" style="padding:24px 24px 0;font-size:12px;line-height:1.7;color:{{ $muted }};">
            <strong style="color:#45524a;">CoinBox</strong>: M-Pesa payment notifications for Kenyan businesses.<br>
            Questions? <a href="mailto:{{ config('coinbox.contact.support') }}" style="color:#5c6b61;">{{ config('coinbox.contact.support') }}</a><br>
            You get this every morning as a user of {{ $report['merchant']->name }}. <a href="{{ $unsubscribeUrl }}" style="color:#5c6b61;">Stop daily reports</a><br>
            © {{ date('Y') }} CoinBox
        </td></tr>
    </table>

</td></tr>
</table>
</body>
</html>
