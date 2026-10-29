<?php

namespace App\Mail\Concerns;

use App\Models\Ticket;
use Illuminate\Mail\Mailables\Address;

trait FromMailbox
{
    protected function teamFrom(Ticket $ticket): Address
    {
        $name = 'CoinBox '.($ticket->mailbox === 'sales' ? 'Sales' : 'Support');

        return config('coinbox.contact.send_as_mailbox')
            ? new Address($ticket->mailboxAddress(), $name)
            : new Address(config('mail.from.address'), $name);
    }

    protected function subjectLine(Ticket $ticket): string
    {
        return "[CoinBox {$ticket->reference()}] {$ticket->subject}";
    }
}
