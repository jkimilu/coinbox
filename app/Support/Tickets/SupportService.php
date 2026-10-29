<?php

namespace App\Support\Tickets;

use App\Mail\TicketReceived;
use App\Mail\TicketReply;
use App\Mail\TicketTeamAlert;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Merchant tickets and website enquiries share one inbox; every reply goes out by email. */
class SupportService
{
    /** Website contact form → sales@ (or support@ when the visitor picks "support"). */
    public function fromContactForm(array $data): Ticket
    {
        return $this->open([
            'source' => 'contact_form',
            'mailbox' => ($data['topic'] ?? 'sales') === 'support' ? 'support' : 'sales',
            'requester_name' => $data['name'],
            'requester_email' => strtolower($data['email']),
            'requester_phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'subject' => $data['subject'] ?? (($data['topic'] ?? 'sales') === 'support' ? 'Support enquiry' : 'Sales enquiry'),
            'category' => ($data['topic'] ?? 'sales') === 'support' ? 'other' : 'sales',
        ], $data['message'], $data['name']);
    }

    /** Merchant panel → support@. */
    public function fromMerchant(User $user, array $data): Ticket
    {
        return $this->open([
            'source' => 'merchant',
            'mailbox' => ($data['category'] ?? null) === 'sales' ? 'sales' : 'support',
            'merchant_id' => $user->merchant_id,
            'user_id' => $user->id,
            'requester_name' => $user->name,
            'requester_email' => $user->email,
            'company' => $user->merchant?->name,
            'subject' => $data['subject'],
            'category' => $data['category'] ?? 'other',
            'priority' => $data['priority'] ?? 'normal',
        ], $data['message'], $user->name, $user);
    }

    protected function open(array $attrs, string $body, string $author, ?User $user = null): Ticket
    {
        [$ticket, $message] = DB::transaction(function () use ($attrs, $body, $author, $user) {
            $ticket = Ticket::create($attrs + ['status' => 'open', 'last_customer_reply_at' => now()]);
            $message = $ticket->messages()->create(['author_type' => 'customer', 'user_id' => $user?->id, 'author_name' => $author, 'body' => $body]);

            return [$ticket->fresh(), $message];
        });

        Mail::to($ticket->mailboxAddress())->queue(new TicketTeamAlert($ticket, $message));
        Mail::to($ticket->requester_email, $ticket->requester_name)->queue(new TicketReceived($ticket));
        AuditLog::record('support.ticket_opened', $ticket, ['source' => $ticket->source], $ticket->merchant_id);

        return $ticket;
    }

    /** Customer follow-up from the merchant panel. */
    public function customerReply(Ticket $ticket, User $user, string $body): TicketMessage
    {
        $message = $ticket->messages()->create(['author_type' => 'customer', 'user_id' => $user->id, 'author_name' => $user->name, 'body' => $body]);
        $ticket->update(['status' => 'open', 'last_customer_reply_at' => now()]);

        Mail::to($ticket->mailboxAddress())->queue(new TicketTeamAlert($ticket->fresh(), $message));

        return $message;
    }

    /**
     * Staff reply: emailed to the requester with optional CCs, then the status is updated.
     *
     * @param  list<string>  $cc
     */
    public function staffReply(Ticket $ticket, User $staff, string $body, array $cc = [], string $status = 'awaiting_customer'): TicketMessage
    {
        $cc = array_values(array_unique(array_filter(array_map(fn ($e) => strtolower(trim($e)), $cc), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) && $e !== strtolower($ticket->requester_email))));

        $message = $ticket->messages()->create([
            'author_type' => 'staff', 'user_id' => $staff->id, 'author_name' => $staff->name,
            'body' => $body, 'sent_to' => $ticket->requester_email, 'cc' => $cc ?: null, 'emailed_at' => now(),
        ]);

        $ticket->update([
            'status' => $status,
            'last_staff_reply_at' => now(),
            'assigned_to' => $ticket->assigned_to ?? $staff->id,
            'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? now() : null,
        ]);

        Mail::to($ticket->requester_email, $ticket->requester_name)->cc($cc)->queue(new TicketReply($ticket->fresh(), $message));
        AuditLog::record('support.staff_replied', $ticket, ['cc' => $cc, 'status' => $status], $ticket->merchant_id);

        return $message;
    }

    /** Internal note: visible to staff only, never emailed. */
    public function note(Ticket $ticket, User $staff, string $body): TicketMessage
    {
        return $ticket->messages()->create(['author_type' => 'note', 'user_id' => $staff->id, 'author_name' => $staff->name, 'body' => $body]);
    }
}
