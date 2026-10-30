@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" target="_blank" style="display:inline-block;text-decoration:none;">
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;"><tr>
<td style="padding-right:10px;vertical-align:middle;"><img src="{{ rtrim(config('app.url'), '/') }}/icon-192.png" width="36" height="36" alt="" class="logo" style="display:block;width:36px;height:36px;border-radius:999px;"></td>
<td style="vertical-align:middle;"><span class="wordmark">CoinBox</span></td>
</tr></table>
</a>
</td>
</tr>
