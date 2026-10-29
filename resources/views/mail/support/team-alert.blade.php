<x-mail::message>
# {{ $ticket->source === 'contact_form' ? 'New website enquiry' : 'Merchant support request' }} {{ $ticket->reference() }}

**From:** {{ $ticket->requester_name }} &lt;{{ $ticket->requester_email }}&gt;@if ($ticket->requester_phone) · {{ $ticket->requester_phone }}@endif
@if ($ticket->company)
**Company:** {{ $ticket->company }}@if ($ticket->merchant) (merchant no. {{ $ticket->merchant->account_number }})@endif

@endif
**Subject:** {{ $ticket->subject }}
@if ($ticket->category)
**Category:** {{ \App\Models\Ticket::CATEGORIES[$ticket->category] ?? $ticket->category }} · **Priority:** {{ ucfirst($ticket->priority) }}
@endif

<x-mail::panel>
{!! nl2br(e($entry->body)) !!}
</x-mail::panel>

<x-mail::button :url="$url">Open in CoinBox admin</x-mail::button>

Reply from the admin panel so the conversation is kept on the ticket.
</x-mail::message>
