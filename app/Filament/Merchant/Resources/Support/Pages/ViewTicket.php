<?php

namespace App\Filament\Merchant\Resources\Support\Pages;

use App\Filament\Merchant\Resources\Support\SupportResource;
use App\Models\Ticket;
use App\Support\Tickets\SupportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ViewTicket extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SupportResource::class;

    protected string $view = 'filament.merchant.support.view-ticket';

    public string $reply = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->subject;
    }

    public function getSubheading(): ?string
    {
        return $this->record->reference().' · '.(Ticket::CATEGORIES[$this->record->category] ?? 'Support').' · opened '.$this->record->created_at->diffForHumans();
    }

    public function sendReply(): void
    {
        $this->validate(['reply' => 'required|string|min:2|max:5000']);
        app(SupportService::class)->customerReply($this->record, auth()->user(), $this->reply);
        $this->reply = '';
        $this->record->refresh();
        Notification::make()->success()->title('Reply sent to CoinBox support')->send();
    }
}
