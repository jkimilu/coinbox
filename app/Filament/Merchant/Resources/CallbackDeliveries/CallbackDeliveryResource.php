<?php

namespace App\Filament\Merchant\Resources\CallbackDeliveries;

use App\Filament\Merchant\Resources\CallbackDeliveries\Pages\ListCallbackDeliveries;
use App\Jobs\DeliverCallback;
use App\Models\CallbackDelivery;
use BackedEnum;
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

    protected static ?string $navigationLabel = 'Webhook deliveries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

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
                TextColumn::make('created_at')->dateTime('d M H:i:s', 'Africa/Nairobi'),
                TextColumn::make('event')->badge(),
                TextColumn::make('endpoint.url')->limit(40),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'delivered' => 'success', 'failed' => 'danger', default => 'warning' }),
                TextColumn::make('attempts'),
                TextColumn::make('last_response_code')->label('HTTP'),
                TextColumn::make('next_attempt_at')->label('Next retry')->since()->placeholder('—'),
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
