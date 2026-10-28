<?php

namespace App\Filament\Admin\Resources\FloatEntries;

use App\Filament\Admin\Resources\FloatEntries\Pages\ListFloatEntries;
use App\Models\FloatEntry;
use App\Models\Merchant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FloatEntryResource extends Resource
{
    protected static ?string $model = FloatEntry::class;

    protected static ?string $slug = 'float';

    protected static ?string $modelLabel = 'float entry';

    protected static ?string $pluralModelLabel = 'float';

    protected static ?string $navigationLabel = 'Float';

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('j M Y H:i', 'Africa/Nairobi'),
                TextColumn::make('merchant.account_number')->label('No.')->fontFamily('mono'),
                TextColumn::make('merchant.name')->searchable(),
                TextColumn::make('type')->formatStateUsing(fn ($state) => FloatEntry::TYPES[$state] ?? $state)->badge()
                    ->color(fn ($state) => match ($state) { 'topup' => 'success', 'adjustment' => 'warning', default => 'gray' }),
                TextColumn::make('description')->wrap(),
                TextColumn::make('reference')->label('Receipt')->fontFamily('mono')->searchable()->placeholder('—'),
                TextColumn::make('amount')->money('KES')->alignEnd()->color(fn ($state) => (float) $state < 0 ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('merchant_id')->label('Merchant')->options(fn () => Merchant::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
                SelectFilter::make('type')->options(FloatEntry::TYPES),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFloatEntries::route('/')];
    }
}
