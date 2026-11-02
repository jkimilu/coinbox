<?php

namespace App\Filament\Merchant\Concerns;

use App\Billing\StkTopUpService;
use App\Models\AccountRoute;
use App\Models\PaymentAccount;
use App\Models\PaymentProvider;
use App\Models\StkRequest;
use App\Payments\Exceptions\ProviderException;
use App\Payments\Mpesa\MerchantStkService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

/** "Request payment": sends an M-Pesa PIN prompt to a customer, paid into one of the merchant's shortcodes. */
trait RequestsPayments
{
    public ?string $pendingCollectionId = null;

    public function requestPaymentAction(): Action
    {
        return Action::make('requestPayment')
            ->label('Request payment')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->modalHeading('Request a payment')
            ->modalDescription('The customer gets an M-Pesa prompt on their phone and enters their PIN. The money goes straight into your account.')
            ->modalIcon(Heroicon::OutlinedDevicePhoneMobile)
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Send prompt')
            ->fillForm(fn () => [
                'provider' => PaymentProvider::MPESA,
                'payment_account_id' => self::promptAccounts()->count() === 1 ? self::promptAccounts()->keys()->first() : null,
            ])
            ->schema([
                Select::make('provider')->label('Provider')
                    ->options([PaymentProvider::MPESA => 'M-Pesa'])
                    ->required()->selectablePlaceholder(false),
                Select::make('payment_account_id')->label('Short code')
                    ->options(fn () => self::promptAccounts())
                    ->required()->live()
                    ->afterStateUpdated(fn (Set $set) => $set('account_route_id', null))
                    ->helperText(fn () => self::promptAccounts()->isEmpty()
                        ? 'None of your accounts can send prompts yet. Add the Lipa na M-Pesa Online passkey under Payment accounts → Update Daraja credentials.'
                        : null),
                Select::make('account_route_id')->label('Account')
                    ->placeholder('None')
                    ->options(fn (Get $get) => AccountRoute::query()
                        ->where('merchant_id', auth()->user()->merchant_id)
                        ->where('payment_account_id', $get('payment_account_id'))
                        ->where('is_active', true)->orderBy('code')->get()
                        ->mapWithKeys(fn (AccountRoute $r) => [$r->id => $r->code.' · '.$r->name]))
                    ->visible(fn (Get $get) => self::isPaybill($get('payment_account_id'))
                        && AccountRoute::query()->where('payment_account_id', $get('payment_account_id'))->where('is_active', true)->exists())
                    ->live(),
                TextInput::make('reference')->label('Reference')
                    ->placeholder('e.g. INV001')
                    ->visible(fn (Get $get) => self::isPaybill($get('payment_account_id')))
                    ->required(fn (Get $get) => self::isPaybill($get('payment_account_id')) && ! $get('account_route_id'))
                    ->live(onBlur: true)
                    ->rule(fn (Get $get) => function (string $attr, $value, \Closure $fail) use ($get) {
                        if (mb_strlen(self::accountReference($get('account_route_id'), $value)) > 12) {
                            $fail('M-Pesa allows at most 12 characters for the account number, including the account code and "#".');
                        }
                    }),
                TextInput::make('amount')->label('Amount (KES)')
                    ->numeric()->integer()->prefix('KES')->required()
                    ->minValue(MerchantStkService::MIN)->maxValue(MerchantStkService::MAX),
                TextInput::make('phone')->label('Customer’s M-Pesa number')->tel()->required()
                    ->placeholder('0712 345 678')
                    ->rule(fn () => function (string $attr, $value, \Closure $fail) {
                        if (! StkTopUpService::normalizePhone($value)) {
                            $fail('Enter a valid Safaricom number, e.g. 0712 345 678.');
                        }
                    }),
                Text::make(fn (Get $get) => self::summary($get))->color('gray'),
            ])
            ->action(function (array $data) {
                $account = PaymentAccount::query()->with('credentials')
                    ->where('merchant_id', auth()->user()->merchant_id)
                    ->findOrFail($data['payment_account_id']);

                try {
                    $stk = app(MerchantStkService::class)->initiate(
                        $account, null, $data['phone'], (int) $data['amount'],
                        $account->account_type === 'till' ? null : self::accountReference($data['account_route_id'] ?? null, $data['reference'] ?? null),
                        null,
                    );
                } catch (ProviderException $e) {
                    Notification::make()->danger()->title('Prompt not sent')->body($e->getMessage())->persistent()->send();

                    return;
                }

                $stk->update(['user_id' => auth()->id()]);
                $this->pendingCollectionId = $stk->id;

                Notification::make()->success()->title('Prompt sent')
                    ->body('Waiting for the customer to enter their PIN on '.$stk->maskedPhone().'.')->send();
            });
    }

    /** Polled by the banner while a prompt is pending. */
    public function checkPendingCollection(): void
    {
        $stk = $this->pendingCollection();
        if (! $stk) {
            $this->pendingCollectionId = null;

            return;
        }

        $stk = app(MerchantStkService::class)->refresh($stk);

        if (! $stk->isPending()) {
            $this->pendingCollectionId = null;
            Notification::make()
                ->title($stk->status === 'succeeded' ? 'Payment received' : 'Payment not completed')
                ->body(MerchantStkService::message($stk))
                ->status($stk->status === 'succeeded' ? 'success' : 'warning')
                ->send();
        }
    }

    public function pendingCollection(): ?StkRequest
    {
        return $this->pendingCollectionId
            ? StkRequest::query()->where('merchant_id', auth()->user()->merchant_id)
                ->where('purpose', StkRequest::PURPOSE_COLLECTION)->find($this->pendingCollectionId)
            : null;
    }

    public function getFooter(): ?View
    {
        return $this->pendingCollectionId ? view('filament.merchant.partials.pending-collection', ['stk' => $this->pendingCollection()]) : null;
    }

    /** The merchant's M-Pesa accounts that can send prompts (have a passkey). */
    protected static function promptAccounts()
    {
        return PaymentAccount::query()->with('credentials')
            ->where('merchant_id', auth()->user()->merchant_id)
            ->where('status', '!=', 'disabled')
            ->orderBy('display_name')->get()
            ->filter(fn (PaymentAccount $a) => filled($a->credentials?->secret('passkey')))
            ->mapWithKeys(fn (PaymentAccount $a) => [$a->id => ($a->account_type === 'till'
                ? 'Till '.($a->config('till_number') ?: $a->external_account_reference)
                : 'PayBill '.$a->external_account_reference).' · '.$a->display_name]);
    }

    protected static function isPaybill(?string $accountId): bool
    {
        return $accountId && PaymentAccount::query()->whereKey($accountId)->value('account_type') === 'paybill';
    }

    /** "100#INV001", "100", or "INV001". */
    protected static function accountReference(?string $routeId, ?string $reference): string
    {
        $code = $routeId ? AccountRoute::query()->where('merchant_id', auth()->user()->merchant_id)->whereKey($routeId)->value('code') : null;
        $reference = trim((string) $reference);

        return $code ? $code.($reference !== '' ? AccountRoute::SEPARATOR.$reference : '') : $reference;
    }

    protected static function summary(Get $get): string
    {
        if (! $get('payment_account_id')) {
            return 'Choose the short code the customer pays into.';
        }
        if (! self::isPaybill($get('payment_account_id'))) {
            return 'Till payments have no account number. The payment shows in your transactions with its M-Pesa receipt.';
        }

        $ref = self::accountReference($get('account_route_id'), $get('reference'));

        return $ref !== '' ? 'Account number on the prompt: '.$ref : 'Pick an account or enter a reference.';
    }
}
