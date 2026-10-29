<?php

namespace App\Filament\Merchant\Widgets;

use App\Filament\Merchant\Resources\CallbackDeliveries\CallbackDeliveryResource;
use App\Filament\Merchant\Resources\PaymentAccounts\PaymentAccountResource;
use App\Models\CallbackDelivery;
use App\Models\CallbackEndpoint;
use App\Models\PaymentAccount;
use App\Payments\Mpesa\MpesaAccountConnector;
use Filament\Widgets\Widget;

/** Is money information flowing? Accounts registered, webhooks delivering. */
class IntegrationHealth extends Widget
{
    protected string $view = 'filament.merchant.widgets.integration-health';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        $m = auth()->user()->merchant_id;
        $connector = app(MpesaAccountConnector::class);

        $accounts = PaymentAccount::query()->where('merchant_id', $m)->with('credentials')->get()->map(fn (PaymentAccount $a) => [
            'name' => $a->display_name,
            'code' => $a->external_account_reference,
            'type' => $a->account_type === 'till' ? 'Till' : 'PayBill',
            'sandbox' => $a->credentials?->environment === 'sandbox',
            'state' => match (true) {
                $a->status === 'disabled' => ['Disabled', 'gray', 'heroicon-m-pause-circle'],
                $a->urls_registered_at === null => ['Not registered', 'warning', 'heroicon-m-exclamation-triangle'],
                ! $connector->registeredUrlsAreCurrent($a) => ['Re-register', 'warning', 'heroicon-m-arrow-path'],
                default => ['Live', 'success', 'heroicon-m-check-circle'],
            },
        ]);

        $since = now()->subDays(7);
        $deliveries = CallbackDelivery::query()->whereHas('endpoint', fn ($q) => $q->where('merchant_id', $m))->where('created_at', '>=', $since);
        $total = (clone $deliveries)->count();
        $ok = (clone $deliveries)->where('status', 'delivered')->count();
        $failing = (clone $deliveries)->where('status', 'failed')->count();
        $retrying = (clone $deliveries)->where('status', 'pending')->where('attempts', '>', 0)->count();

        return [
            'accounts' => $accounts,
            'endpoints' => CallbackEndpoint::query()->where('merchant_id', $m)->where('is_active', true)->count(),
            'rate' => $total ? round($ok / $total * 100, 1) : null,
            'total' => $total,
            'failing' => $failing,
            'retrying' => $retrying,
            'accountsUrl' => PaymentAccountResource::getUrl(),
            'deliveriesUrl' => CallbackDeliveryResource::getUrl(),
        ];
    }
}
