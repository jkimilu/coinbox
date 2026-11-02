<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/** The 3am email: yesterday's collections and reversals per short code and account. */
class DailyCollectionReportMail extends Mailable
{
    public function __construct(public array $report, public User $user) {}

    public function envelope(): Envelope
    {
        $t = $this->report['totals'];

        return new Envelope(
            subject: 'KES '.number_format($t['net'], 2).' collected on '.$this->report['date']->format('D j M')
                .' · '.$this->report['merchant']->name,
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $data = [
            'report' => $this->report,
            'url' => url('/merchant/transactions'),
            'unsubscribeUrl' => $this->unsubscribeUrl(),
        ];

        return new Content(view: 'mail.reports.daily', text: 'mail.reports.daily-text', with: $data);
    }

    protected function unsubscribeUrl(): string
    {
        return URL::signedRoute('reports.daily.unsubscribe', ['user' => $this->user->id]);
    }
}
