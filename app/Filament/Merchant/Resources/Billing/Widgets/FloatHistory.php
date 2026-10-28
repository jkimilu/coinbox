<?php

namespace App\Filament\Merchant\Resources\Billing\Widgets;

use App\Models\FloatEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FloatHistory extends TableWidget
{
    protected static ?string $heading = 'Float history';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => FloatEntry::query()->where('merchant_id', auth()->user()->merchant_id))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('j M Y H:i', 'Africa/Nairobi'),
                TextColumn::make('type')->formatStateUsing(fn ($state) => FloatEntry::TYPES[$state] ?? $state)->badge()
                    ->color(fn ($state) => $state === 'topup' ? 'success' : 'gray'),
                TextColumn::make('description')->wrap(),
                TextColumn::make('reference')->label('M-Pesa receipt')->fontFamily('mono')->placeholder('—'),
                TextColumn::make('amount')->money('KES')->alignEnd()
                    ->color(fn ($state) => (float) $state < 0 ? 'danger' : 'success'),
            ])
            ->emptyStateHeading('No float activity yet');
    }
}
