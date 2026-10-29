<?php

namespace App\Filament\Merchant\Widgets;

use App\Filament\Merchant\Resources\ApiKeys\ApiKeyResource;
use App\Filament\Merchant\Resources\CallbackEndpoints\CallbackEndpointResource;
use App\Filament\Merchant\Resources\PaymentAccounts\PaymentAccountResource;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Filament\Widgets\Widget;

class WelcomeBanner extends Widget
{
    protected string $view = 'filament.merchant.widgets.welcome-banner';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -20;

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        $user = auth()->user();
        $merchant = $user->merchant;
        $hour = (int) now('Africa/Nairobi')->format('G');
        $last = Transaction::query()->where('merchant_id', $merchant->id)->where('type', 'payment')->latest('paid_at')->first();
        $accounts = PaymentAccount::query()->where('merchant_id', $merchant->id)->get();

        return [
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'),
            'firstName' => str($user->name)->before(' ')->toString(),
            'merchant' => $merchant,
            'live' => $accounts->where('status', 'active')->count(),
            'accounts' => $accounts->count(),
            'lastPayment' => $last,
            'urls' => [
                'accounts' => PaymentAccountResource::getUrl(),
                'webhooks' => CallbackEndpointResource::getUrl(),
                'keys' => ApiKeyResource::getUrl(),
            ],
        ];
    }
}
