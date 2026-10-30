<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** CoinBox-worded password reset (same Filament link and logic). */
class ResetPassword extends FilamentResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $first = str($notifiable->name ?? '')->before(' ')->toString();
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Reset your CoinBox password')
            ->greeting('Hi'.($first ? ' '.$first : '').',')
            ->line('We received a request to reset the password for your CoinBox account.')
            ->action('Choose a new password', $this->url)
            ->line("This link expires in {$minutes} minutes.")
            ->line('Didn’t ask for this? Ignore this email and your password stays the same. If you keep getting these, contact '.config('coinbox.contact.support').'.')
            ->salutation('The CoinBox team');
    }
}
