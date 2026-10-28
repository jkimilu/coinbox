<?php

namespace App\Filament\Admin\Resources\Merchants;

use App\Filament\Admin\Resources\Merchants\Pages\ManageMerchants;
use App\Models\Merchant;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MerchantResource extends Resource
{
    protected static ?string $model = Merchant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
            TextInput::make('phone'),
            TextInput::make('kra_pin')->label('KRA PIN'),
            Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('payment_accounts_count')->counts('paymentAccounts')->label('Accounts'),
                TextColumn::make('transactions_count')->counts('transactions')->label('Transactions'),
                TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('created_at')->date(),
            ])
            ->filters([SelectFilter::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended'])])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMerchants::route('/')];
    }
}
