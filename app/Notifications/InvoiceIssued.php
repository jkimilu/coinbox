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
        $i = $this->invoice->fresh(['merchant']);
        $mail = (new MailMessage)
            ->subject("CoinBox invoice {$i->number} for {$i->periodLabel()}")
            ->greeting('Hello '.$notifiable->name)
            ->line("Your CoinBox fee for {$i->periodLabel()} is **KES ".number_format((float) $i->amount, 2).'**, '
                .rtrim(rtrim(number_format((float) $i->fee_percent, 3), '0'), '.')."% of KES ".number_format((float) $i->processed_volume, 2).' processed.');

        if ($i->status === 'paid') {
            return $mail->line('It has been paid in full from your float. No action is needed.')
                ->action('View billing', url('/merchant/billing'));
        }

        return $mail
            ->line('Amount due: **KES '.number_format($i->outstanding(), 2).'** by '.$i->due_at->format('j M Y').'.')
            ->line("Pay via M-Pesa: **PayBill {$i->payBill()}**, Account **{$i->paymentReference()}**.")
            ->line('Tip: keep a float with us (PayBill '.$i->payBill().', Account '.$i->merchant->account_number.') and future invoices are paid automatically.')
            ->action('View billing', url('/merchant/billing'));
    }
}
