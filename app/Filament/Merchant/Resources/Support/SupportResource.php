<?php

namespace App\Filament\Merchant\Resources\Support;

use App\Filament\Merchant\Concerns\ScopedToMerchant;
use App\Filament\Merchant\Resources\Support\Pages\ListTickets;
use App\Filament\Merchant\Resources\Support\Pages\ViewTicket;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupportResource extends Resource
{
    use ScopedToMerchant;

    protected static ?string $model = Ticket::class;

    protected static ?string $slug = 'support';

    protected static ?string $modelLabel = 'ticket';

    protected static ?string $navigationLabel = 'Support';

    protected static ?string $breadcrumb = 'Support';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool { return false; } // created via the "New ticket" action
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function getNavigationBadge(): ?string
    {
        $n = Ticket::query()->where('merchant_id', auth()->user()?->merchant_id)->where('status', 'awaiting_customer')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Replies waiting for you';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Ticket $r) => static::getUrl('view', ['record' => $r]))
            ->columns([
                TextColumn::make('number')->label('Ref')->formatStateUsing(fn ($state) => '#'.$state)->fontFamily('mono'),
                TextColumn::make('subject')->weight('semibold')->limit(60)->searchable()
                    ->tooltip(fn (Ticket $r) => str($r->messages()->latest('created_at')->value('body'))->limit(200)),
                TextColumn::make('category')->formatStateUsing(fn ($state) => Ticket::CATEGORIES[$state] ?? $state)->color('gray'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'awaiting_customer' ? 'Reply received' : Ticket::STATUSES[$state])
                    ->color(fn ($state) => $state === 'awaiting_customer' ? 'success' : Ticket::statusColor($state))
                    ->tooltip(fn ($state) => match ($state) { 'open' => 'With our team', 'awaiting_customer' => 'We replied. Check your email or open the ticket', 'resolved' => 'Marked resolved; reply to reopen', default => null }),
                TextColumn::make('updated_at')->label('Last update')->since()->sortable(),
            ])
            ->emptyStateIcon('heroicon-o-lifebuoy')
            ->emptyStateHeading('No support tickets')
            ->emptyStateDescription('Stuck on something? Open a ticket and our team will reply by email, usually within one business day.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }
}
