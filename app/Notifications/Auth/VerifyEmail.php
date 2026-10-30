<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\VerifyEmail as FilamentVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/** CoinBox-worded email verification (same Filament link and logic). */
class VerifyEmail extends FilamentVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $first = str($notifiable->name ?? '')->before(' ')->toString();

        return (new MailMessage)
            ->subject('Confirm your email for CoinBox')
            ->greeting('Welcome to CoinBox'.($first ? ', '.$first : '').'!')
            ->line('Confirm your email address to finish setting up your account. Then you can connect your M-Pesa PayBill or Till and start receiving payment notifications.')
            ->action('Confirm my email', $this->url)
            ->line('This link expires in 60 minutes.')
            ->line('Didn’t sign up for CoinBox? You can safely ignore this email.')
            ->salutation('The CoinBox team');
    }
}
