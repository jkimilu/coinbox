<?php

namespace App\Filament\Admin\Resources\Transactions;

use App\Filament\Admin\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Merchant\Resources\Transactions\TransactionResource as MerchantTransactions;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Payments\ReversalService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return MerchantTransactions::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('paid_at')->dateTime('d M Y H:i', 'Africa/Nairobi')->sortable(),
                TextColumn::make('merchant.name')->searchable(),
                TextColumn::make('provider_transaction_id')->label('Receipt')->searchable()->copyable(),
                TextColumn::make('accountRoute.code')->label('Acc no.')->fontFamily('mono')->placeholder('—'),
                TextColumn::make('merchant_reference')->label('Reference')->searchable(),
                TextColumn::make('amount')->money('KES')->sortable()->alignEnd(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge()->color(fn ($state) => MerchantTransactions::statusColor($state)),
                TextColumn::make('paymentAccount.external_account_reference')->label('Shortcode'),
            ])
            ->filters([
                SelectFilter::make('merchant_id')->label('Merchant')->options(fn () => Merchant::query()->pluck('name', 'id'))->searchable(),
                SelectFilter::make('payment_account_id')->label('Shortcode')
                    ->options(fn () => PaymentAccount::query()->with('merchant')->orderBy('external_account_reference')->get()
                        ->mapWithKeys(fn (PaymentAccount $a) => [$a->id => "{$a->external_account_reference} · {$a->display_name} ({$a->merchant?->name})"]))
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('status')->options(['succeeded' => 'Succeeded', 'pending' => 'Pending', 'reversed' => 'Reversed', 'failed' => 'Failed']),
                SelectFilter::make('type')->options(['payment' => 'Payment', 'reversal' => 'Reversal']),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('reversal')->label('Record reversal')->icon(Heroicon::OutlinedArrowUturnLeft)->color('danger')
                    ->visible(fn (Transaction $r) => $r->type === 'payment' && $r->status === 'succeeded')
                    ->modalDescription('Record a reversal that has already been completed at Safaricom. The original payment stays in the history.')
                    ->schema([
                        TextInput::make('receipt')->label('Reversal receipt / reference')->required(),
                        TextInput::make('reason')->maxLength(255),
                    ])
                    ->action(function (Transaction $record, array $data) {
                        try {
                            app(ReversalService::class)->record($record, strtoupper(trim($data['receipt'])), $data['reason'] ?? null);
                            Notification::make()->success()->title('Reversal recorded')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Could not record reversal')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTransactions::route('/')];
    }
}
