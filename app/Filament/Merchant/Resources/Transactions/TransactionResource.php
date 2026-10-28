<?php

namespace App\Filament\Merchant\Resources\Transactions;

use App\Filament\Merchant\Concerns\ScopedToMerchant;
use App\Filament\Merchant\Resources\Transactions\Pages\ListTransactions;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;

class TransactionResource extends Resource
{
    use ScopedToMerchant;

    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 0;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function statusColor(?string $state): string
    {
        return match ($state) { 'succeeded' => 'success', 'pending' => 'warning', 'reversed' => 'danger', default => 'gray' };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('provider_transaction_id')->label('M-Pesa receipt')->copyable(),
            TextEntry::make('id')->label('CoinBox ID')->formatStateUsing(fn ($state) => 'txn_'.$state)->copyable(),
            TextEntry::make('amount')->money('KES'),
            TextEntry::make('status')->badge()->color(fn ($state) => self::statusColor($state)),
            TextEntry::make('type')->badge(),
            TextEntry::make('payment_method')->label('Method'),
            TextEntry::make('merchant_reference')->label('Account / reference')->placeholder('—'),
            TextEntry::make('paymentAccount.display_name')->label('Account'),
            TextEntry::make('payer_name')->placeholder('—'),
            TextEntry::make('payer_identifier')->label('Payer phone (as sent by Safaricom)')->placeholder('—'),
            TextEntry::make('paid_at')->dateTime(timezone: 'Africa/Nairobi'),
            TextEntry::make('created_at')->label('Recorded at')->dateTime(timezone: 'Africa/Nairobi'),
            KeyValueEntry::make('metadata')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('paid_at')->dateTime('d M Y H:i', 'Africa/Nairobi')->sortable(),
                TextColumn::make('provider_transaction_id')->label('Receipt')->searchable()->copyable(),
                TextColumn::make('merchant_reference')->label('Reference')->searchable()->placeholder('—'),
                TextColumn::make('payer_name')->searchable()->toggleable(),
                TextColumn::make('amount')->money('KES')->sortable()->alignEnd(),
                TextColumn::make('type')->badge()->toggleable(),
                TextColumn::make('status')->badge()->color(fn ($state) => self::statusColor($state)),
                TextColumn::make('paymentAccount.display_name')->label('Account')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('payment_account_id')->label('Account')
                    ->options(fn () => PaymentAccount::query()->where('merchant_id', auth()->user()->merchant_id)->pluck('display_name', 'id')),
                SelectFilter::make('status')->options(['succeeded' => 'Succeeded', 'pending' => 'Pending', 'reversed' => 'Reversed', 'failed' => 'Failed']),
                SelectFilter::make('type')->options(['payment' => 'Payment', 'reversal' => 'Reversal']),
                Filter::make('paid_at')
                    ->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->where('paid_at', '>=', Carbon::parse($v, 'Africa/Nairobi')->startOfDay()->utc()))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->where('paid_at', '<=', Carbon::parse($v, 'Africa/Nairobi')->endOfDay()->utc()))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTransactions::route('/')];
    }
}
