<?php

namespace App\Filament\Merchant\Widgets;

use App\Filament\Merchant\Pages\SetupGuide;
use App\Filament\Merchant\Resources\ApiKeys\ApiKeyResource;
use App\Filament\Merchant\Resources\Billing\BillingResource;
use App\Filament\Merchant\Resources\CallbackEndpoints\CallbackEndpointResource;
use App\Filament\Merchant\Resources\PaymentAccounts\PaymentAccountResource;
use App\Filament\Merchant\Resources\Transactions\TransactionResource;
use App\Models\ApiKey;
use App\Models\CallbackEndpoint;
use App\Models\FloatEntry;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Filament\Widgets\Widget;

/** Onboarding steps; hidden once everything is done. */
class SetupChecklist extends Widget
{
    protected string $view = 'filament.merchant.widgets.setup-checklist';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -5;

    protected static bool $isLazy = false;

    public static function steps(): array
    {
        $m = auth()->user()?->merchant_id;
        $accounts = PaymentAccount::query()->where('merchant_id', $m);

        return [
            ['Connect your PayBill or Till', 'Add your Daraja app keys.', (clone $accounts)->exists(), PaymentAccountResource::getUrl()],
            ['Register with Safaricom', 'So payment notifications reach CoinBox.', (clone $accounts)->whereNotNull('urls_registered_at')->exists(), PaymentAccountResource::getUrl()],
            ['Add a webhook', 'Where we send each payment.', CallbackEndpoint::query()->where('merchant_id', $m)->exists(), CallbackEndpointResource::getUrl()],
            ['Receive a first payment', 'Pay a small amount to test.', Transaction::query()->where('merchant_id', $m)->exists(), TransactionResource::getUrl()],
            ['Create an API key', 'Optional: pull payments from your system.', ApiKey::query()->where('merchant_id', $m)->exists(), ApiKeyResource::getUrl()],
            ['Top up your float', 'Covers CoinBox fees automatically.', FloatEntry::query()->where('merchant_id', $m)->where('type', 'topup')->exists(), BillingResource::getUrl()],
        ];
    }

    public static function canView(): bool
    {
        return collect(static::steps())->contains(fn ($s) => ! $s[2]);
    }

    protected function getViewData(): array
    {
        $steps = static::steps();

        return ['steps' => $steps, 'done' => collect($steps)->where(2, true)->count(), 'guide' => SetupGuide::getUrl()];
    }
}
