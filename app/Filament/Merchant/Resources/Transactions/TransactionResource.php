<?php

namespace App\Filament\Merchant\Resources\Transactions;

use App\Filament\Merchant\Concerns\ScopedToMerchant;
use App\Filament\Merchant\Resources\Transactions\Pages\ListTransactions;
use App\Models\AccountRoute;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use BackedEnum;
use UnitEnum;
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

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

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
            TextEntry::make('metadata.bill_ref_number')->label('Account number entered')->placeholder('—')->fontFamily('mono'),
            TextEntry::make('accountRoute.name')->label('Account no.')->formatStateUsing(fn ($state, $record) => $record->accountRoute->code.' · '.$state)->placeholder('—'),
            TextEntry::make('merchant_reference')->label('Reference')->placeholder('—'),
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
            ->recordAction('view')
            ->columns(self::compactColumns())
            ->filters([
                SelectFilter::make('payment_account_id')->label('Account')
                    ->options(fn () => PaymentAccount::query()->where('merchant_id', auth()->user()->merchant_id)->pluck('display_name', 'id')),
                SelectFilter::make('account_route_id')->label('Account no.')
                    ->options(fn () => AccountRoute::query()->where('merchant_id', auth()->user()->merchant_id)->with('paymentAccount')->get()
                        ->mapWithKeys(fn ($r) => [$r->id => "{$r->paymentAccount->external_account_reference} · {$r->code} {$r->name}"])),
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

    /** One-line hover summary of a payment, reused on several columns. */
    public static function hoverSummary(Transaction $r): string
    {
        $into = ($r->payment_method === 'till' ? 'Till ' : 'PayBill ').$r->paymentAccount?->external_account_reference;

        return collect([
            $r->payer_name ? 'From '.$r->payer_name.($r->payer_identifier ? ' ('.$r->payer_identifier.')' : '') : null,
            'Into '.$into.($r->accountRoute ? ', account '.$r->accountRoute->code.' ('.$r->accountRoute->name.')' : ''),
            ($r->metadata['bill_ref_number'] ?? null) ? 'Customer entered "'.$r->metadata['bill_ref_number'].'"' : null,
            'Paid '.$r->paid_at->setTimezone('Africa/Nairobi')->format('D j M Y, H:i:s').' ('.$r->paid_at->diffForHumans().')',
        ])->filter()->implode("\n");
    }

    public static function statusHint(Transaction $r): string
    {
        return match (true) {
            $r->type === 'reversal' => 'A reversal of an earlier payment. The original stays in your history.',
            $r->status === 'reversed' => 'This payment was later reversed. See the matching reversal row.',
            $r->status === 'succeeded' => 'Confirmed by Safaricom. The money is in your M-Pesa account.',
            $r->status === 'pending' => 'Waiting for confirmation from the provider.',
            default => ucfirst($r->status),
        };
    }

    /** Dense columns: secondary details live in tooltips; extra columns are toggleable. */
    public static function compactColumns(bool $summary = false): array
    {
        $columns = [
            TextColumn::make('paid_at')->label('Date')->sortable()
                ->formatStateUsing(fn ($state) => $state->setTimezone('Africa/Nairobi')->isToday() ? 'Today '.$state->setTimezone('Africa/Nairobi')->format('H:i') : $state->setTimezone('Africa/Nairobi')->format('j M, H:i'))
                ->tooltip(fn (Transaction $r) => $r->paid_at->setTimezone('Africa/Nairobi')->format('l j F Y, H:i:s').' EAT · '.$r->paid_at->diffForHumans()),
            TextColumn::make('payer_name')->label('Customer')->searchable()->placeholder('Unknown')->limit(22)
                ->tooltip(fn (Transaction $r) => self::hoverSummary($r)),
            TextColumn::make('provider_transaction_id')->label('Receipt')->searchable()->copyable()->copyMessage('Receipt copied')->fontFamily('mono')
                ->tooltip('Click to copy the M-Pesa receipt'),
            TextColumn::make('merchant_reference')->label('Reference')->searchable()->placeholder('—')->limit(18)
                ->formatStateUsing(fn ($state, Transaction $r) => $r->accountRoute ? $r->accountRoute->code.' · '.$state : $state)
                ->tooltip(fn (Transaction $r) => collect([
                    $r->accountRoute ? 'Account '.$r->accountRoute->code.': '.$r->accountRoute->name : null,
                    ($r->metadata['bill_ref_number'] ?? null) ? 'Customer typed "'.$r->metadata['bill_ref_number'].'"' : 'No account number entered',
                ])->filter()->implode("\n")),
            TextColumn::make('status')->badge()
                ->formatStateUsing(fn ($state, Transaction $r) => $r->type === 'reversal' ? 'Reversal' : ucfirst($state))
                ->icon(fn ($state, Transaction $r) => $r->type === 'reversal' ? 'heroicon-m-arrow-uturn-left' : match ($state) { 'succeeded' => 'heroicon-m-check-circle', 'reversed' => 'heroicon-m-arrow-uturn-left', default => 'heroicon-m-clock' })
                ->color(fn ($state, Transaction $r) => $r->type === 'reversal' ? 'danger' : self::statusColor($state))
                ->tooltip(fn (Transaction $r) => self::statusHint($r)),
            TextColumn::make('amount')->sortable()->alignEnd()->weight('bold')
                ->formatStateUsing(fn ($state, Transaction $r) => ($r->type === 'reversal' ? '−' : '').'KES '.number_format((float) $state, 2))
                ->tooltip(fn (Transaction $r) => self::hoverSummary($r)),
            TextColumn::make('paymentAccount.display_name')->label('Account')->toggleable(isToggledHiddenByDefault: true)
                ->tooltip(fn (Transaction $r) => ($r->payment_method === 'till' ? 'Till ' : 'PayBill ').$r->paymentAccount?->external_account_reference),
            TextColumn::make('payer_identifier')->label('Phone')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('type')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
        ];

        // Dashboard summary: no extra columns, no search or sorting.
        return $summary
            ? array_map(fn (TextColumn $c) => $c->searchable(false)->sortable(false), array_slice($columns, 0, 6))
            : $columns;
    }

    public static function getPages(): array
    {
        return ['index' => ListTransactions::route('/')];
    }
}
