<?php

namespace App\Mail;

use App\Mail\Concerns\FromMailbox;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Acknowledgement to the person who wrote in. */
class TicketReceived extends Mailable
{
    use FromMailbox, Queueable, SerializesModels;

    public function __construct(public Ticket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->teamFrom($this->ticket),
            replyTo: [new Address($this->ticket->mailboxAddress(), 'CoinBox')],
            subject: $this->subjectLine($this->ticket),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support.received');
    }
}
