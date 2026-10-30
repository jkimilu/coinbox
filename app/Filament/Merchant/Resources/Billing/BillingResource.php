<?php

namespace App\Filament\Merchant\Resources\Billing;

use App\Filament\Merchant\Concerns\ScopedToMerchant;
use App\Filament\Merchant\Resources\Billing\Pages\ListBilling;
use App\Filament\Merchant\Concerns\PaysWithMpesa;
use App\Models\BillingInvoice;
use BackedEnum;
use UnitEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BillingResource extends Resource
{
    use ScopedToMerchant;

    protected static ?string $model = BillingInvoice::class;

    protected static ?string $slug = 'billing';

    protected static ?string $modelLabel = 'invoice';

    protected static ?string $navigationLabel = 'Billing';

    protected static ?string $breadcrumb = 'Billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function getNavigationBadge(): ?string
    {
        $open = BillingInvoice::query()->where('merchant_id', auth()->user()?->merchant_id)->where('status', 'open')->count();

        return $open ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (BillingInvoice $r) => "Invoice {$r->number}: {$r->periodLabel()}")
                ->columns(3)
                ->schema([
                    TextEntry::make('status')->state(fn (BillingInvoice $r) => $r->displayStatus())->badge()->color(fn ($state) => BillingInvoice::statusColor($state)),
                    TextEntry::make('issued_at')->date('j M Y'),
                    TextEntry::make('due_at')->label('Due')->date('j M Y'),
                    TextEntry::make('processed_volume')->label('Processed')->money('KES'),
                    TextEntry::make('payment_count')->label('Payments'),
                    TextEntry::make('fee_percent')->label('Fee rate')->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.').'%'),
                    TextEntry::make('amount')->money('KES')->weight('bold'),
                    TextEntry::make('amount_paid')->label('Paid')->money('KES'),
                    TextEntry::make('outstanding')->state(fn (BillingInvoice $r) => $r->outstanding())->money('KES')->weight('bold'),
                ]),
            Section::make('How to pay')
                ->visible(fn (BillingInvoice $r) => $r->status === 'open')
                ->description('Pay from any M-Pesa line. We match the payment to this invoice automatically.')
                ->columns(3)
                ->schema([
                    TextEntry::make('paybill')->label('PayBill (business number)')->state(fn (BillingInvoice $r) => $r->payBill())->copyable()->size('lg')->weight('bold')->fontFamily('mono'),
                    TextEntry::make('reference')->label('Account number')->state(fn (BillingInvoice $r) => $r->paymentReference())->copyable()->size('lg')->weight('bold')->fontFamily('mono'),
                    TextEntry::make('due')->label('Amount')->state(fn (BillingInvoice $r) => $r->outstanding())->money('KES')->size('lg')->weight('bold'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Invoices')
            ->defaultSort('period_start', 'desc')
            ->columns([
                TextColumn::make('number')->weight('bold')->fontFamily('mono'),
                TextColumn::make('period_start')->label('Period')->formatStateUsing(fn ($record) => $record->periodLabel()),
                TextColumn::make('processed_volume')->label('Processed')->money('KES')->alignEnd()
                    ->tooltip(fn (BillingInvoice $r) => number_format($r->payment_count).' payments × '.rtrim(rtrim((string) $r->fee_percent, '0'), '.').'% fee'),
                TextColumn::make('amount')->label('Fee')->money('KES')->alignEnd(),
                TextColumn::make('outstanding')->label('Outstanding')->state(fn (BillingInvoice $r) => $r->outstanding())->money('KES')->alignEnd(),
                TextColumn::make('status')->state(fn (BillingInvoice $r) => $r->displayStatus())->badge()->color(fn ($state) => BillingInvoice::statusColor($state))
                    ->tooltip(fn (BillingInvoice $r) => $r->status === 'paid'
                        ? 'Paid '.$r->paid_at?->setTimezone('Africa/Nairobi')->format('j M Y')
                        : 'KES '.number_format($r->outstanding(), 2).' due '.$r->due_at->format('j M Y')."\nPayBill ".$r->payBill().', Account '.$r->paymentReference()),
                TextColumn::make('due_at')->label('Due')->date('j M Y'),
                TextColumn::make('pay_with')->label('Pay with')
                    ->state(fn (BillingInvoice $r) => $r->status === 'open' ? "PayBill {$r->payBill()} · Acc {$r->paymentReference()}" : '—')
                    ->fontFamily('mono')->copyable()->copyableState(fn (BillingInvoice $r) => $r->paymentReference()),
            ])
            ->emptyStateHeading('No invoices yet')
            ->emptyStateDescription('Invoices are issued on the 1st of each month once you process more than the free allowance.')
            ->recordActions([
                PaysWithMpesa::makePayFromFloatAction('payFromFloat'),
                PaysWithMpesa::makeMpesaAction('payInvoiceMpesa'),
                ViewAction::make()->label('Details'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBilling::route('/')];
    }
}
