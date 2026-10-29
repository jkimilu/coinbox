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

/** Staff reply to the customer (CCs are added by the caller). */
class TicketReply extends Mailable
{
    use FromMailbox, Queueable, SerializesModels;

    public function __construct(public Ticket $ticket, public TicketMessage $reply) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->teamFrom($this->ticket),
            replyTo: [new Address($this->ticket->mailboxAddress(), 'CoinBox '.($this->ticket->mailbox === 'sales' ? 'Sales' : 'Support'))],
            subject: 'Re: '.$this->subjectLine($this->ticket),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support.reply', with: [
            'portalUrl' => $this->ticket->merchant_id ? url('/merchant/support/'.$this->ticket->id) : null,
        ]);
    }
}
