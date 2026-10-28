<?php

namespace App\Filament\Admin\Resources\FloatEntries\Pages;

use App\Billing\BillingService;
use App\Filament\Admin\Resources\FloatEntries\FloatEntryResource;
use App\Models\AuditLog;
use App\Models\FloatEntry;
use App\Models\Merchant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListFloatEntries extends ListRecords
{
    protected static string $resource = FloatEntryResource::class;

    protected function merchantField(): Select
    {
        return Select::make('merchant_id')->label('Merchant')->required()->searchable()
            ->options(fn () => Merchant::query()->orderBy('name')->get()->mapWithKeys(fn ($m) => [$m->id => "{$m->account_number} · {$m->name}"]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('topup')->label('Record top-up')->icon(Heroicon::OutlinedPlus)->color('success')
                ->modalDescription('For M-Pesa payments to the CoinBox PayBill that were not matched automatically. Open invoices are paid from the new balance.')
                ->schema([
                    $this->merchantField(),
                    TextInput::make('amount')->numeric()->required()->minValue(1)->prefix('KES'),
                    TextInput::make('receipt')->label('M-Pesa receipt')->required()->maxLength(30),
                ])
                ->action(function (array $data) {
                    $entry = app(BillingService::class)->recordPayment(Merchant::findOrFail($data['merchant_id']), (float) $data['amount'], $data['receipt'], recordedBy: auth()->id());
                    $entry
                        ? Notification::make()->success()->title('Top-up recorded')->send()
                        : Notification::make()->warning()->title('That receipt has already been recorded')->send();
                }),
            Action::make('adjust')->label('Adjustment')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
                ->modalDescription('Credit (positive) or debit (negative) a merchant’s float, e.g. a goodwill credit or a correction. Always logged.')
                ->schema([
                    $this->merchantField(),
                    TextInput::make('amount')->numeric()->required()->prefix('KES')->helperText('Use a negative number to debit.'),
                    TextInput::make('description')->label('Reason')->required()->maxLength(200),
                ])
                ->action(function (array $data) {
                    $entry = FloatEntry::create([
                        'merchant_id' => $data['merchant_id'], 'type' => 'adjustment', 'amount' => round((float) $data['amount'], 2),
                        'description' => $data['description'], 'recorded_by' => auth()->id(),
                    ]);
                    AuditLog::record('billing.float_adjusted', $entry, ['amount' => $data['amount'], 'reason' => $data['description']], $data['merchant_id']);
                    if ((float) $data['amount'] > 0) {
                        $merchant = Merchant::find($data['merchant_id']);
                        $merchant->invoices()->where('status', 'open')->orderBy('period_start')->each(fn ($i) => app(BillingService::class)->settleFromFloat($i));
                    }
                    Notification::make()->success()->title('Adjustment recorded')->send();
                }),
        ];
    }
}
