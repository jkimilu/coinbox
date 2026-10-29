<?php

namespace App\Mail;

use App\Mail\Concerns\FromMailbox;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to support@ / sales@ when a customer writes. Reply-To is the customer. */
class TicketTeamAlert extends Mailable
{
    use FromMailbox, Queueable, SerializesModels;

    public function __construct(public Ticket $ticket, public TicketMessage $entry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'CoinBox Website'),
            replyTo: [new Address($this->ticket->requester_email, $this->ticket->requester_name)],
            subject: $this->subjectLine($this->ticket).' ('.($this->ticket->source === 'contact_form' ? 'website' : 'merchant').')',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support.team-alert', with: [
            'url' => url('/admin/tickets/'.$this->ticket->id),
        ]);
    }
}
