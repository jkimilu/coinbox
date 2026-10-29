<?php

namespace App\Filament\Admin\Resources\Tickets\Pages;

use App\Filament\Admin\Resources\Merchants\MerchantResource;
use App\Filament\Admin\Resources\Tickets\TicketResource;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Tickets\SupportService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewTicket extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TicketResource::class;

    protected string $view = 'filament.admin.support.view-ticket';

    public ?array $data = [];

    public ?array $meta = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill(['status_after' => 'awaiting_customer', 'cc' => []]);
        $this->metaForm->fill($this->record->only(['status', 'priority', 'assigned_to']));
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->subject;
    }

    public function getSubheading(): ?string
    {
        $t = $this->record;

        return $t->reference().' · '.($t->source === 'contact_form' ? 'Website enquiry' : 'Merchant ticket').' to '.$t->mailboxAddress().' · opened '.$t->created_at->diffForHumans();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Grid::make(2)->schema([
                TextInput::make('to')->label('To')->disabled()->dehydrated(false)
                    ->default(fn () => $this->record->requester_name.' <'.$this->record->requester_email.'>')
                    ->placeholder($this->record->requester_name.' <'.$this->record->requester_email.'>'),
                TagsInput::make('cc')->label('CC')->placeholder('Add email and press Enter')
                    ->nestedRecursiveRules(['email'])
                    ->suggestions(fn () => User::query()->where('merchant_id', $this->record->merchant_id)->whereNotNull('merchant_id')->pluck('email')->all()),
            ]),
            Textarea::make('body')->label('Reply')->rows(7)->required()->minLength(2)->maxLength(10000)
                ->placeholder('Write your reply. It is emailed to the customer and saved on this ticket.'),
            Select::make('status_after')->label('Then set status to')->options([
                'awaiting_customer' => 'Awaiting customer', 'open' => 'Open', 'resolved' => 'Resolved', 'closed' => 'Closed',
            ])->native(false)->required(),
        ]);
    }

    public function metaForm(Schema $schema): Schema
    {
        return $schema->statePath('meta')->components([
            Select::make('status')->options(Ticket::STATUSES)->native(false)->live()->afterStateUpdated(fn () => $this->saveMeta()),
            Select::make('priority')->options(Ticket::PRIORITIES)->native(false)->live()->afterStateUpdated(fn () => $this->saveMeta()),
            Select::make('assigned_to')->label('Owner')->placeholder('Unassigned')
                ->options(fn () => User::query()->where('is_admin', true)->orderBy('name')->pluck('name', 'id'))
                ->native(false)->live()->afterStateUpdated(fn () => $this->saveMeta()),
        ]);
    }

    public function saveMeta(): void
    {
        $data = $this->metaForm->getState();
        $this->record->update($data + ['resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? ($this->record->resolved_at ?? now()) : null]);
        AuditLog::record('support.ticket_updated', $this->record, $data, $this->record->merchant_id);
        Notification::make()->success()->title('Ticket updated')->send();
    }

    public function sendReply(): void
    {
        $data = $this->form->getState();
        app(SupportService::class)->staffReply($this->record, auth()->user(), $data['body'], $data['cc'] ?? [], $data['status_after']);
        $this->afterPost('Reply emailed to '.$this->record->requester_email.(count($data['cc'] ?? []) ? ' (+'.count($data['cc']).' cc)' : ''));
    }

    public function addNote(): void
    {
        $this->validate(['data.body' => 'required|string|min:2|max:10000']);
        app(SupportService::class)->note($this->record, auth()->user(), $this->data['body']);
        $this->afterPost('Internal note added (not emailed)');
    }

    protected function afterPost(string $message): void
    {
        $this->record->refresh();
        $this->form->fill(['status_after' => 'awaiting_customer', 'cc' => $this->data['cc'] ?? []]);
        $this->metaForm->fill($this->record->only(['status', 'priority', 'assigned_to']));
        Notification::make()->success()->title($message)->send();
    }

    public function merchantUrl(): ?string
    {
        return $this->record->merchant_id ? MerchantResource::getUrl() : null;
    }
}
