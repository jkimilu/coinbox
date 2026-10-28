<?php

namespace App\Filament\Admin\Resources\Invoices;

use App\Billing\BillingService;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Merchant\Resources\Billing\BillingResource as MerchantBilling;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\Merchant;
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
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = BillingInvoice::class;

    protected static ?string $slug = 'invoices';

    protected static ?string $modelLabel = 'invoice';

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return MerchantBilling::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('merchant.account_number')->label('No.')->fontFamily('mono'),
                TextColumn::make('merchant.name')->searchable(),
                TextColumn::make('number')->fontFamily('mono')->searchable(),
                TextColumn::make('period_start')->label('Period')->formatStateUsing(fn ($record) => $record->periodLabel())->sortable(),
                TextColumn::make('processed_volume')->label('Processed')->money('KES')->alignEnd(),
                TextColumn::make('amount')->label('Fee')->money('KES')->alignEnd()->sortable(),
                TextColumn::make('outstanding')->state(fn (BillingInvoice $r) => $r->outstanding())->money('KES')->alignEnd(),
                TextColumn::make('status')->state(fn (BillingInvoice $r) => $r->displayStatus())->badge()->color(fn ($state) => BillingInvoice::statusColor($state)),
                TextColumn::make('due_at')->date('j M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => 'Open', 'paid' => 'Paid', 'void' => 'Void']),
                SelectFilter::make('merchant_id')->label('Merchant')->options(fn () => Merchant::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('recordPayment')->label('Record payment')->icon(Heroicon::OutlinedBanknotes)->color('success')
                    ->visible(fn (BillingInvoice $r) => $r->status === 'open')
                    ->fillForm(fn (BillingInvoice $r) => ['amount' => $r->outstanding()])
                    ->schema([
                        TextInput::make('amount')->numeric()->required()->minValue(1)->prefix('KES'),
                        TextInput::make('receipt')->label('M-Pesa receipt')->required()->maxLength(30),
                    ])
                    ->modalDescription('For payments that were not matched automatically. The money is credited to the merchant’s float and applied to this invoice; any excess stays in the float.')
                    ->action(function (BillingInvoice $record, array $data) {
                        $entry = app(BillingService::class)->recordPayment($record->merchant, (float) $data['amount'], $data['receipt'], $record->number, recordedBy: auth()->id());
                        $entry
                            ? Notification::make()->success()->title('Payment recorded')->send()
                            : Notification::make()->warning()->title('That receipt has already been recorded')->send();
                    }),
                Action::make('void')->color('danger')->icon(Heroicon::OutlinedXCircle)->requiresConfirmation()
                    ->visible(fn (BillingInvoice $r) => $r->status === 'open' && (float) $r->amount_paid == 0.0)
                    ->action(function (BillingInvoice $record) {
                        $record->update(['status' => 'void']);
                        AuditLog::record('billing.invoice_voided', $record, [], $record->merchant_id);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInvoices::route('/')];
    }
}
