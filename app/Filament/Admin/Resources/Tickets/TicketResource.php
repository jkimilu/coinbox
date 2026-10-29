<?php

namespace App\Filament\Admin\Resources\Tickets;

use App\Filament\Admin\Resources\Tickets\Pages\ListTickets;
use App\Filament\Admin\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $slug = 'tickets';

    protected static ?string $modelLabel = 'ticket';

    protected static ?string $navigationLabel = 'Inbox';

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 0;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    /** Open tickets where the customer spoke last. */
    public static function needsReplyQuery(): Builder
    {
        return Ticket::query()->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('last_staff_reply_at')->orWhereColumn('last_customer_reply_at', '>', 'last_staff_reply_at'));
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::needsReplyQuery()->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Waiting for our reply';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Ticket $r) => static::getUrl('view', ['record' => $r]))
            ->columns([
                IconColumn::make('needs_reply')->label('')->state(fn (Ticket $r) => $r->needsReply())
                    ->icon(fn ($state) => $state ? 'heroicon-s-chat-bubble-left-ellipsis' : null)->color('warning')
                    ->tooltip(fn ($state) => $state ? 'Waiting for our reply' : null),
                TextColumn::make('number')->label('Ref')->formatStateUsing(fn ($state) => '#'.$state)->fontFamily('mono')->searchable(),
                TextColumn::make('subject')->weight('semibold')->limit(50)->searchable()
                    ->tooltip(fn (Ticket $r) => str($r->messages()->where('author_type', '!=', 'note')->latest('created_at')->value('body'))->limit(240)),
                TextColumn::make('requester_name')->label('From')->searchable(['requester_name', 'requester_email', 'company'])
                    ->formatStateUsing(fn ($state, Ticket $r) => $state.($r->company ? ' · '.$r->company : ''))->limit(34)
                    ->tooltip(fn (Ticket $r) => $r->requester_email.($r->requester_phone ? ' · '.$r->requester_phone : '').($r->merchant ? "\nMerchant no. ".$r->merchant->account_number : '')),
                TextColumn::make('source')->badge()->color('gray')
                    ->formatStateUsing(fn ($state, Ticket $r) => ($state === 'contact_form' ? 'Website' : 'Merchant').' → '.$r->mailbox.'@'),
                TextColumn::make('priority')->badge()->formatStateUsing(fn ($state) => ucfirst($state))->color(fn ($state) => Ticket::priorityColor($state)),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Ticket::STATUSES[$state] ?? $state)->color(fn ($state) => Ticket::statusColor($state)),
                TextColumn::make('assignee.name')->label('Owner')->placeholder('Unassigned')->toggleable(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable()
                    ->tooltip(fn (Ticket $r) => $r->updated_at->setTimezone('Africa/Nairobi')->format('j M Y, H:i')),
            ])
            ->filters([
                Filter::make('needs_reply')->label('Waiting for our reply')->query(fn (Builder $q) => $q->whereIn('id', static::needsReplyQuery()->select('id')))->default(),
                SelectFilter::make('status')->options(Ticket::STATUSES)->multiple(),
                SelectFilter::make('mailbox')->options(['support' => 'support@', 'sales' => 'sales@']),
                SelectFilter::make('source')->options(['merchant' => 'Merchant panel', 'contact_form' => 'Website form']),
                SelectFilter::make('priority')->options(Ticket::PRIORITIES),
            ])
            ->emptyStateIcon('heroicon-o-inbox')
            ->emptyStateHeading('Inbox zero')
            ->emptyStateDescription('Nothing waiting for a reply. Clear the "Waiting for our reply" filter to see every ticket.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }
}
