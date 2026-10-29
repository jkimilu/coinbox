<?php

namespace App\Filament\Merchant\Resources\CallbackDeliveries;

use App\Filament\Merchant\Resources\CallbackDeliveries\Pages\ListCallbackDeliveries;
use App\Jobs\DeliverCallback;
use App\Models\CallbackDelivery;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CallbackDeliveryResource extends Resource
{
    protected static ?string $model = CallbackDelivery::class;

    protected static ?string $modelLabel = 'log';

    protected static ?string $navigationLabel = 'Logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Developers';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('endpoint', fn ($q) => $q->where('merchant_id', auth()->user()?->merchant_id));
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('event'),
            TextEntry::make('status')->badge(),
            TextEntry::make('endpoint.url')->columnSpanFull(),
            TextEntry::make('attempts'),
            TextEntry::make('last_response_code')->placeholder('—'),
            TextEntry::make('last_error')->placeholder('—')->columnSpanFull(),
            TextEntry::make('last_response_body')->placeholder('—')->columnSpanFull(),
            TextEntry::make('payload')->formatStateUsing(fn ($record) => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))->fontFamily('mono')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Sent')->dateTime('j M, H:i:s', 'Africa/Nairobi')
                    ->tooltip(fn ($record) => $record->created_at->diffForHumans()),
                TextColumn::make('event')->badge()->color('gray')
                    ->tooltip(fn ($record) => ($record->payload['data']['provider_transaction_id'] ?? null) ? 'Payment '.$record->payload['data']['provider_transaction_id'].' · KES '.($record->payload['data']['amount'] ?? '') : null),
                TextColumn::make('endpoint.url')->label('Endpoint')->limit(36)->tooltip(fn ($record) => $record->endpoint?->url),
                TextColumn::make('status')->badge()
                    ->icon(fn ($state) => match ($state) { 'delivered' => 'heroicon-m-check-circle', 'failed' => 'heroicon-m-x-circle', default => 'heroicon-m-arrow-path' })
                    ->color(fn ($state) => match ($state) { 'delivered' => 'success', 'failed' => 'danger', default => 'warning' })
                    ->formatStateUsing(fn ($state, $record) => ucfirst($state).($record->last_response_code ? ' · '.$record->last_response_code : ''))
                    ->tooltip(fn ($record) => collect([
                        $record->attempts.' '.str('attempt')->plural($record->attempts),
                        $record->last_error ? 'Error: '.str($record->last_error)->limit(160) : null,
                        $record->last_response_body && ! $record->last_error ? 'Response: '.str($record->last_response_body)->limit(160) : null,
                        $record->next_attempt_at ? 'Next retry '.$record->next_attempt_at->diffForHumans() : null,
                        $record->delivered_at ? 'Delivered '.$record->delivered_at->setTimezone('Africa/Nairobi')->format('j M H:i:s') : null,
                    ])->filter()->implode("\n")),
                TextColumn::make('attempts')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('next_attempt_at')->label('Next retry')->since()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'delivered' => 'Delivered', 'failed' => 'Failed'])])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn ($record) => $record->status !== 'delivered')
                    ->action(function (CallbackDelivery $record) {
                        $record->update(['status' => 'pending', 'next_attempt_at' => now()]);
                        DeliverCallback::dispatch($record->id);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCallbackDeliveries::route('/')];
    }
}
