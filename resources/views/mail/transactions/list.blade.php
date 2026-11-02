@php
    $ink = '#141b16'; $text = '#333e37'; $muted = '#7c8b81'; $line = '#e7ebe8'; $soft = '#f6f8f5';
    $green = '#227f15'; $greenBright = '#2ca01c'; $greenSoft = '#eef8ec'; $red = '#c62828'; $redSoft = '#fdf0ef';
    $font = "'Inter',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $kes = fn ($v) => 'KES '.number_format((float) $v, 2);
    $t = $totals;
    $base = rtrim(config('app.url'), '/');
    $th = "padding:10px 8px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:{$muted};border-bottom:1px solid {$line};";
    $td = "padding:9px 8px;border-bottom:1px solid #f0f2ef;font-size:13px;vertical-align:top;";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light only">
<title>Transactions · CoinBox</title>
<style>
@media only screen and (max-width: 640px) {
  .card { width: 100% !important; border-radius: 0 !important; }
  .pad { padding-left: 16px !important; padding-right: 16px !important; }
  .tile { display: block !important; width: 100% !important; margin-bottom: 10px !important; }
  .hide-sm { display: none !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background:#f4f2ea;font-family:{{ $font }};color:{{ $text }};-webkit-text-size-adjust:none;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ number_format($count) }} transactions · net {{ $kes($t['net']) }}</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f2ea;">
<tr><td align="center" style="padding:28px 12px 40px;">

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;"><tr>
        <td style="padding-right:10px;vertical-align:middle;"><img src="{{ $base }}/icon-192.png" width="34" height="34" alt="" style="display:block;width:34px;height:34px;border-radius:999px;"></td>
        <td style="vertical-align:middle;font-size:20px;font-weight:800;letter-spacing:-0.02em;color:{{ $ink }};">CoinBox</td>
    </tr></table>

    <table role="presentation" class="card" width="640" cellpadding="0" cellspacing="0" style="width:640px;background:#ffffff;border:1px solid #e3e7e2;border-radius:16px;overflow:hidden;">
        <tr><td height="5" style="background:{{ $greenBright }};font-size:0;line-height:0;">&nbsp;</td></tr>

        <tr><td class="pad" style="padding:28px 32px 6px;">
            <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:{{ $green }};">Transactions</div>
            <div style="font-size:22px;font-weight:800;letter-spacing:-0.02em;color:{{ $ink }};margin-top:4px;">{{ $merchantName }}</div>
            <div style="font-size:13px;color:{{ $muted }};margin-top:4px;">
                Sent {{ now('Africa/Nairobi')->format('l j F Y, g:i a') }}
                @if ($filters) · {{ implode(' · ', $filters) }} @else · All transactions @endif
            </div>
        </td></tr>

        <tr><td class="pad" style="padding:18px 32px 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                <td class="tile" width="33%" valign="top" style="padding:14px 16px;background:{{ $greenSoft }};border-radius:12px;">
                    <div style="font-size:12px;color:{{ $green }};font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Received</div>
                    <div style="font-size:18px;font-weight:800;color:{{ $ink }};margin-top:6px;">{{ $kes($t['received']) }}</div>
                    <div style="font-size:12.5px;color:{{ $muted }};">{{ number_format($t['received_count']) }} {{ str('payment')->plural($t['received_count']) }}</div>
                </td>
                <td class="hide-sm" width="10" style="font-size:0;">&nbsp;</td>
                <td class="tile" width="33%" valign="top" style="padding:14px 16px;background:{{ $t['reversed_count'] ? $redSoft : $soft }};border-radius:12px;">
                    <div style="font-size:12px;color:{{ $t['reversed_count'] ? $red : $muted }};font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Reversed</div>
                    <div style="font-size:18px;font-weight:800;color:{{ $ink }};margin-top:6px;">{{ $kes($t['reversed']) }}</div>
                    <div style="font-size:12.5px;color:{{ $muted }};">{{ number_format($t['reversed_count']) }} {{ str('reversal')->plural($t['reversed_count']) }}</div>
                </td>
                <td class="hide-sm" width="10" style="font-size:0;">&nbsp;</td>
                <td class="tile" width="33%" valign="top" style="padding:14px 16px;background:{{ $soft }};border-radius:12px;">
                    <div style="font-size:12px;color:{{ $muted }};font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Net</div>
                    <div style="font-size:18px;font-weight:800;color:{{ $ink }};margin-top:6px;">{{ $kes($t['net']) }}</div>
                    <div style="font-size:12.5px;color:{{ $muted }};">{{ number_format($count) }} {{ str('row')->plural($count) }}</div>
                </td>
            </tr></table>
        </td></tr>

        <tr><td class="pad" style="padding:22px 32px 0;">
            @if ($rows->isEmpty())
                <div style="font-size:14px;color:{{ $muted }};">No transactions match.</div>
            @else
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $line }};border-radius:12px;border-collapse:separate;overflow:hidden;">
                <tr style="background:{{ $soft }};">
                    <td style="{{ $th }}">Date</td>
                    <td style="{{ $th }}">Receipt</td>
                    <td class="hide-sm" style="{{ $th }}">Account</td>
                    <td class="hide-sm" style="{{ $th }}">Customer</td>
                    <td align="right" style="{{ $th }}">Amount</td>
                </tr>
                @foreach ($rows as $r)
                    @php $rev = $r->type === 'reversal'; $wasReversed = $r->status === 'reversed'; @endphp
                    <tr style="{{ $loop->even ? 'background:#fbfcfa;' : '' }}">
                        <td style="{{ $td }}white-space:nowrap;color:{{ $muted }};">{{ $r->paid_at->setTimezone('Africa/Nairobi')->format('j M, g:i a') }}</td>
                        <td style="{{ $td }}">
                            <span style="font-family:Menlo,Consolas,monospace;font-size:12.5px;color:{{ $ink }};">{{ $r->provider_transaction_id }}</span>
                            @if ($rev)<div style="font-size:11.5px;color:{{ $red }};font-weight:700;">Reversal</div>
                            @elseif ($wasReversed)<div style="font-size:11.5px;color:{{ $red }};">Reversed</div>
                            @elseif ($r->status === 'pending')<div style="font-size:11.5px;color:#b26a00;">Pending</div>@endif
                        </td>
                        <td class="hide-sm" style="{{ $td }}">
                            {{ $r->paymentAccount?->external_account_reference }}
                            @php $route = $r->accountRoute ?? $r->reverses?->accountRoute; @endphp
                            @if ($route)<span style="color:{{ $muted }};"> · {{ $route->code }} {{ $route->name }}</span>@endif
                            @if ($r->merchant_reference)<div style="font-size:11.5px;color:{{ $muted }};">Ref {{ $r->merchant_reference }}</div>@endif
                        </td>
                        <td class="hide-sm" style="{{ $td }}color:{{ $muted }};">{{ $r->payer_name ?: '—' }}</td>
                        <td align="right" style="{{ $td }}white-space:nowrap;font-weight:700;color:{{ $rev ? $red : ($wasReversed ? $muted : $ink) }};{{ $wasReversed ? 'text-decoration:line-through;' : '' }}">{{ $rev ? '−' : '' }}{{ $kes($r->amount) }}</td>
                    </tr>
                @endforeach
            </table>
            @if ($count > $rows->count())
                <div style="font-size:12.5px;color:{{ $muted }};margin-top:10px;">
                    Showing the latest {{ number_format($rows->count()) }} of {{ number_format($count) }}.
                    {{ $hasAttachment ? 'All of them are in the attached Excel file.' : 'Tick “Attach Excel file” next time to get all of them.' }}
                </div>
            @endif
            @endif
        </td></tr>

        @if ($hasAttachment)
        <tr><td class="pad" style="padding:18px 32px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-left:4px solid {{ $greenBright }};background:{{ $greenSoft }};border-radius:0 10px 10px 0;"><tr>
                <td style="padding:12px 16px;font-size:13.5px;color:#1d3a19;">📎 <strong>Excel file attached</strong>: every row, with real dates and amounts ready for sorting and totals.</td>
            </tr></table>
        </td></tr>
        @endif

        <tr><td align="center" class="pad" style="padding:28px 32px 32px;">
            <a href="{{ $url }}" style="display:inline-block;background:{{ $green }};color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 28px;border-radius:999px;">Open in CoinBox</a>
            <div style="font-size:12px;color:{{ $muted }};margin-top:14px;">Times are Nairobi time. You asked for this email from the Transactions page.</div>
        </td></tr>
    </table>

    <table role="presentation" class="card" width="640" cellpadding="0" cellspacing="0" style="width:640px;">
        <tr><td align="center" style="padding:24px 24px 0;font-size:12px;line-height:1.7;color:{{ $muted }};">
            <strong style="color:#45524a;">CoinBox</strong>: M-Pesa payment notifications for Kenyan businesses.<br>
            Questions? <a href="mailto:{{ config('coinbox.contact.support') }}" style="color:#5c6b61;">{{ config('coinbox.contact.support') }}</a><br>
            © {{ date('Y') }} CoinBox
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
