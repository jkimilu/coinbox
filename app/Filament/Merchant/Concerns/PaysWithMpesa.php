<?php

namespace App\Filament\Merchant\Concerns;

use App\Billing\BillingService;
use App\Billing\StkTopUpService;
use App\Models\BillingInvoice;
use App\Models\StkRequest;
use App\Payments\Exceptions\ProviderException;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/** "Pay with M-Pesa" (STK prompt) and "Pay from float" actions for merchant billing screens. */
trait PaysWithMpesa
{
    public ?string $pendingStkId = null;

    public function payWithMpesaAction(): Action
    {
        return self::makeMpesaAction('payWithMpesa');
    }

    /**
     * "Pay with M-Pesa": sends an STK prompt. On an invoice row Filament injects the invoice
     * as $record (amount = outstanding); elsewhere it's a float top-up with preset amounts.
     */
    public static function makeMpesaAction(string $name): Action
    {
        return Action::make($name)
            ->label(fn (?BillingInvoice $record = null) => $record ? 'Pay with M-Pesa' : 'Top up with M-Pesa')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->visible(fn (?BillingInvoice $record = null) => app(StkTopUpService::class)->account() !== null && (! $record || $record->status === 'open'))
            ->modalHeading(fn (?BillingInvoice $record = null) => $record ? 'Pay invoice '.$record->number.' with M-Pesa' : 'Top up your float with M-Pesa')
            ->modalDescription('We’ll send an M-Pesa prompt to this phone. Enter your PIN to pay.')
            ->modalIcon(Heroicon::OutlinedDevicePhoneMobile)
            ->modalWidth('md')
            ->modalSubmitActionLabel('Send prompt')
            ->fillForm(function (?BillingInvoice $record = null) {
                $merchant = auth()->user()->merchant;
                // The merchant's registered business phone (editable in the form).
                $phone = StkTopUpService::normalizePhone($merchant->phone);
                $suggested = $record ? (int) ceil($record->outstanding()) : (int) app(BillingService::class)->advice($merchant)['top_up'];

                return [
                    'preset' => $record ? 'custom' : (in_array($suggested, StkTopUpService::PRESETS, true) ? (string) $suggested : ($suggested > 0 ? 'custom' : '1000')),
                    'amount' => max($suggested, StkTopUpService::MIN),
                    'phone' => $phone ? '0'.substr($phone, -9) : null,
                ];
            })
            ->schema(fn (?BillingInvoice $record = null) => [
                ToggleButtons::make('preset')->label('Amount (KES)')
                    ->options(collect(StkTopUpService::PRESETS)->mapWithKeys(fn ($v) => [(string) $v => number_format($v)])->put('custom', 'Custom')->all())
                    ->inline()->required()->live()
                    ->hidden((bool) $record),
                TextInput::make('amount')->label($record ? 'Amount (KES)' : 'Custom amount (KES)')
                    ->numeric()->integer()->prefix('KES')->minValue(StkTopUpService::MIN)->maxValue(StkTopUpService::MAX)
                    ->required(fn (Get $get) => $record || $get('preset') === 'custom')
                    ->visible(fn (Get $get) => $record || $get('preset') === 'custom')
                    ->helperText($record ? 'Outstanding: KES '.number_format($record->outstanding(), 2) : null),
                TextInput::make('phone')->label('M-Pesa number')->tel()->required()
                    ->placeholder('0712 345 678')
                    ->helperText('Your registered business number. Change it to send the prompt to another Safaricom line.')
                    ->rule(fn () => function (string $attr, $value, \Closure $fail) {
                        if (! StkTopUpService::normalizePhone($value)) {
                            $fail('Enter a valid Safaricom number, e.g. 0712 345 678.');
                        }
                    }),
                Text::make('Paid to CoinBox PayBill '.\App\Support\Billing::payBill().', account '.auth()->user()->merchant->account_number.($record ? '#'.$record->number : '').'. Your customers’ payments are not affected.')->color('gray'),
            ])
            ->action(function (array $data, $livewire, ?BillingInvoice $record = null) {
                $amount = (int) (($record || ($data['preset'] ?? null) === 'custom') ? $data['amount'] : $data['preset']);

                try {
                    $stk = app(StkTopUpService::class)->initiate(auth()->user()->merchant, auth()->user(), $data['phone'], $amount, $record?->number);
                } catch (ProviderException $e) {
                    Notification::make()->danger()->title('Prompt not sent')->body($e->getMessage())->send();

                    return;
                }

                $livewire->dispatch('coinbox-stk-sent', id: $stk->id);
                if (property_exists($livewire, 'pendingStkId')) {
                    $livewire->pendingStkId = $stk->id;
                }

                Notification::make()->success()->title('Check your phone')
                    ->body('Enter your M-Pesa PIN on '.$stk->maskedPhone().' to pay KES '.number_format($amount).'.')->send();
            });
    }

    /** "Pay from float" on an invoice row: uses the positive float balance (fully or partly). */
    public static function makePayFromFloatAction(string $name): Action
    {
        return Action::make($name)
            ->label('Pay from float')
            ->icon(Heroicon::OutlinedWallet)
            ->color('success')
            ->visible(fn (BillingInvoice $record) => $record->status === 'open' && $record->merchant->floatBalance() > 0)
            ->requiresConfirmation()
            ->modalHeading(fn (BillingInvoice $record) => 'Pay '.$record->number.' from your float')
            ->modalDescription(function (BillingInvoice $record) {
                $balance = $record->merchant->floatBalance();
                $take = min($balance, $record->outstanding());

                return 'KES '.number_format($take, 2).' will be taken from your float (balance KES '.number_format($balance, 2).').'
                    .($take < $record->outstanding() ? ' KES '.number_format($record->outstanding() - $take, 2).' will still be owed.' : ' The invoice will be fully paid.');
            })
            ->modalSubmitActionLabel('Pay now')
            ->action(function (BillingInvoice $record, $livewire) {
                app(BillingService::class)->settleFromFloat($record);
                $record->refresh();
                Notification::make()->success()
                    ->title($record->status === 'paid' ? 'Invoice '.$record->number.' paid' : 'Part payment made')
                    ->body($record->status === 'paid' ? 'Paid from your float.' : 'KES '.number_format($record->outstanding(), 2).' still owed.')->send();
                $livewire->dispatch('coinbox-float-updated');
            });
    }

    /** Polled while a prompt is pending. */
    public function checkPendingStk(): void
    {
        if (! $this->pendingStkId) {
            return;
        }

        $stk = StkRequest::query()->where('merchant_id', auth()->user()->merchant_id)->find($this->pendingStkId);
        if (! $stk) {
            $this->pendingStkId = null;

            return;
        }

        $stk = app(StkTopUpService::class)->refresh($stk);

        if (! $stk->isPending()) {
            $this->pendingStkId = null;
            Notification::make()->title($stk->status === 'succeeded' ? 'Payment received' : 'Payment not completed')
                ->body(StkTopUpService::message($stk))
                ->status($stk->status === 'succeeded' ? 'success' : 'warning')->send();
            $this->dispatch('coinbox-float-updated');
        }
    }

    public function pendingStk(): ?StkRequest
    {
        return $this->pendingStkId ? StkRequest::query()->where('merchant_id', auth()->user()->merchant_id)->find($this->pendingStkId) : null;
    }
}
