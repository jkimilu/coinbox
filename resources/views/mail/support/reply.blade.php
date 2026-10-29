<x-mail::message>
Hi {{ str($ticket->requester_name)->before(' ') }},

{!! nl2br(e($reply->body)) !!}

{{ $reply->author_name }}<br>
CoinBox {{ $ticket->mailbox === 'sales' ? 'Sales' : 'Support' }}

<x-mail::subcopy>
Reference {{ $ticket->reference() }}: {{ $ticket->subject }}.
@if ($portalUrl)
You can also reply from the CoinBox merchant portal: {{ $portalUrl }}
@else
Reply to this email to continue the conversation.
@endif
</x-mail::subcopy>
</x-mail::message>
