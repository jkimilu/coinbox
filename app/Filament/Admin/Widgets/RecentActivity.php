<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivity extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 5];

    protected static ?string $heading = 'Recent activity';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => AuditLog::query()->latest('id')->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('action')->formatStateUsing(fn ($state) => str($state)->replace(['.', '_'], ' ')->ucfirst())->weight('medium')
                    ->tooltip(fn ($record) => $record->data ? str(json_encode($record->data))->limit(200) : null),
                TextColumn::make('merchant_id')->label('Merchant')->formatStateUsing(fn ($state) => \App\Models\Merchant::find($state)?->name)->placeholder('—')->limit(18),
                TextColumn::make('created_at')->label('When')->since()->alignEnd(),
            ]);
    }
}
