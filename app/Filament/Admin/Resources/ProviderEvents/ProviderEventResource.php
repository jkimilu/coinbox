<?php

namespace App\Filament\Admin\Resources\ProviderEvents;

use App\Filament\Admin\Resources\ProviderEvents\Pages\ListProviderEvents;
use App\Jobs\ProcessProviderEvent;
use App\Models\ProviderEvent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProviderEventResource extends Resource
{
    protected static ?string $model = ProviderEvent::class;

    protected static ?string $modelLabel = 'event';

    protected static ?string $navigationLabel = 'Events';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('event_type'),
            TextEntry::make('processing_status')->badge(),
            TextEntry::make('paymentAccount.display_name')->placeholder('—'),
            TextEntry::make('source_ip'),
            TextEntry::make('idempotency_key')->placeholder('—'),
            TextEntry::make('processed_at')->dateTime()->placeholder('—'),
            TextEntry::make('processing_error')->placeholder('—')->columnSpanFull(),
            TextEntry::make('payload')->formatStateUsing(fn ($record) => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))->fontFamily('mono')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime('d M H:i:s', 'Africa/Nairobi'),
                TextColumn::make('provider.code')->label('Provider')->badge(),
                TextColumn::make('event_type'),
                TextColumn::make('paymentAccount.external_account_reference')->label('Shortcode'),
                TextColumn::make('idempotency_key')->label('Key')->searchable(),
                TextColumn::make('processing_status')->badge()->color(fn ($state) => match ($state) { 'processed' => 'success', 'duplicate' => 'gray', 'received' => 'warning', default => 'danger' }),
                TextColumn::make('source_ip'),
            ])
            ->filters([SelectFilter::make('processing_status')->options(array_combine($s = ['received', 'processed', 'duplicate', 'failed', 'rejected'], array_map('ucfirst', $s)))])
            ->recordActions([
                ViewAction::make(),
                Action::make('reprocess')->icon(Heroicon::OutlinedArrowPath)->requiresConfirmation()
                    ->visible(fn (ProviderEvent $r) => in_array($r->processing_status, ['failed', 'received', 'rejected']) && $r->event_type === 'c2b.confirmation')
                    ->action(function (ProviderEvent $record) {
                        $record->update(['processing_status' => 'received', 'processing_error' => null]);
                        ProcessProviderEvent::dispatch($record->id);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListProviderEvents::route('/')];
    }
}
