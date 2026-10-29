<?php

namespace App\Filament\Admin\Resources\PaymentAccounts;

use App\Filament\Admin\Resources\PaymentAccounts\Pages\ListPaymentAccounts;
use App\Filament\Merchant\Resources\PaymentAccounts\PaymentAccountResource as MerchantAccounts;
use App\Models\PaymentAccount;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentAccountResource extends Resource
{
    protected static ?string $model = PaymentAccount::class;

    protected static ?string $modelLabel = 'account';

    protected static ?string $navigationLabel = 'Accounts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return MerchantAccounts::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('merchant.name')->searchable(),
                TextColumn::make('display_name')->searchable(),
                TextColumn::make('provider.name')->label('Provider'),
                TextColumn::make('account_type')->badge(),
                TextColumn::make('external_account_reference')->label('Shortcode')->searchable(),
                TextColumn::make('credentials.environment')->label('Env')->badge(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'active' => 'success', 'pending' => 'warning', default => 'gray' }),
                TextColumn::make('urls_registered_at')->since()->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'active' => 'Active', 'disabled' => 'Disabled'])])
            ->recordActions([
                ViewAction::make(),
                MerchantAccounts::registerUrlsAction(),
                Action::make('toggle')
                    ->label(fn ($record) => $record->status === 'disabled' ? 'Enable' : 'Disable')
                    ->requiresConfirmation()
                    ->action(fn (PaymentAccount $record) => $record->update(['status' => $record->status === 'disabled' ? 'active' : 'disabled'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPaymentAccounts::route('/')];
    }
}
