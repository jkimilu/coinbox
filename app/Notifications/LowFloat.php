<?php

namespace App\Notifications;

use App\Models\Merchant;
use App\Support\Billing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowFloat extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Merchant $merchant, public array $advice) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kes = fn ($v) => 'KES '.number_format((float) $v, 2);

        return (new MailMessage)
            ->subject('Top up your CoinBox float')
            ->greeting('Hello '.$notifiable->name)
            ->line('Your CoinBox float is '.$kes($this->advice['balance']).'. To cover your fees we recommend keeping at least '.$kes($this->advice['recommended_float']).($this->advice['outstanding'] > 0 ? ' plus '.$kes($this->advice['outstanding']).' currently owed' : '').'.')
            ->line('Top up **'.$kes($this->advice['top_up']).'** via M-Pesa: **PayBill '.Billing::payBill().'**, Account **'.$this->merchant->account_number.'**.')
            ->line('Your customers’ payments are not affected: they always go straight to your own PayBill or Till.')
            ->line('Faster: open Billing and tap **Top up with M-Pesa** to get a PIN prompt on your phone.')
            ->action('Top up now', url('/merchant/billing'))
            ->salutation('The CoinBox team');
    }
}
