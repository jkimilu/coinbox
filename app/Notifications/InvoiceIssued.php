<?php

namespace App\Notifications;

use App\Models\BillingInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public BillingInvoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->invoice->exists ? $this->invoice->fresh(['merchant']) : $this->invoice;

        return (new MailMessage)
            ->subject("CoinBox invoice {$i->number} for {$i->periodLabel()}".($i->status === 'paid' ? ' (paid from float)' : ''))
            ->markdown('mail.billing.invoice', [
                'i' => $i,
                'first' => str($notifiable->name)->before(' ')->toString(),
                'rate' => rtrim(rtrim(number_format((float) $i->fee_percent, 3), '0'), '.'),
                'balance' => $i->merchant->floatBalance(),
                'url' => url('/merchant/billing'),
            ]);
    }
}
