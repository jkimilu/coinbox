<x-mail::message>
# We’ve got your message

Hi {{ str($ticket->requester_name)->before(' ') }},

Thanks for contacting CoinBox. Your reference is **{{ $ticket->reference() }}**. A member of our {{ $ticket->mailbox === 'sales' ? 'sales' : 'support' }} team will reply by email, usually within one business day.

**Subject:** {{ $ticket->subject }}

If you need to add anything, just reply to this email or write to {{ $ticket->mailboxAddress() }} and mention {{ $ticket->reference() }}.

Thanks,<br>
The CoinBox team
</x-mail::message>
