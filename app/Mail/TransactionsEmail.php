<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** "Email me" on the Transactions page: the rows on screen as an HTML email, optionally with the Excel file. */
class TransactionsEmail extends Mailable
{
    public const MAX_ROWS_IN_BODY = 200;

    public function __construct(
        public string $merchantName,
        public Collection $rows,
        public int $count,
        public array $totals,
        public array $filters,
        public ?string $xlsxPath = null,
        public ?string $xlsxName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: number_format($this->count).' '.str('transaction')->plural($this->count).' from CoinBox · '.now('Africa/Nairobi')->format('j M Y, g:i a'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.transactions.list', text: 'mail.transactions.list-text', with: [
            'url' => url('/merchant/transactions'),
            'hasAttachment' => (bool) $this->xlsxPath,
        ]);
    }

    public function attachments(): array
    {
        return $this->xlsxPath
            ? [Attachment::fromPath($this->xlsxPath)->as($this->xlsxName)->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]
            : [];
    }
}
