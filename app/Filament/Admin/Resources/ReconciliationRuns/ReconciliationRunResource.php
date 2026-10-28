<?php

namespace App\Filament\Admin\Resources\ReconciliationRuns;

use App\Filament\Admin\Resources\ReconciliationRuns\Pages\ManageReconciliationRuns;
use App\Models\ReconciliationRun;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReconciliationRunResource extends Resource
{
    protected static ?string $model = ReconciliationRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Reconciliation';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('paymentAccount.display_name'),
            TextEntry::make('period_start')->dateTime(),
            TextEntry::make('period_end')->dateTime(),
            TextEntry::make('matched_count'),
            TextEntry::make('missing_in_gateway_count'),
            TextEntry::make('missing_at_provider_count'),
            TextEntry::make('amount_mismatch_count'),
            TextEntry::make('discrepancies')->formatStateUsing(fn ($record) => json_encode($record->discrepancies, JSON_PRETTY_PRINT))->fontFamily('mono')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime(),
                TextColumn::make('paymentAccount.display_name')->label('Account'),
                TextColumn::make('source')->badge(),
                TextColumn::make('period_start')->date(),
                TextColumn::make('period_end')->date(),
                TextColumn::make('matched_count')->label('Matched'),
                TextColumn::make('missing_in_gateway_count')->label('Missing here')->color(fn ($state) => $state ? 'danger' : null),
                TextColumn::make('missing_at_provider_count')->label('Missing at provider')->color(fn ($state) => $state ? 'danger' : null),
                TextColumn::make('amount_mismatch_count')->label('Mismatched')->color(fn ($state) => $state ? 'danger' : null),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReconciliationRuns::route('/')];
    }
}
