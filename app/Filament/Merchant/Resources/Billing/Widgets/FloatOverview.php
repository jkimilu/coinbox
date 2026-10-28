<?php

namespace App\Filament\Merchant\Resources\Billing\Widgets;

use App\Billing\BillingService;
use App\Support\Billing;
use App\Support\Pricing;
use Filament\Widgets\Widget;

class FloatOverview extends Widget
{
    protected string $view = 'filament.merchant.widgets.float-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        $merchant = auth()->user()->merchant;

        return [
            'merchant' => $merchant,
            'advice' => app(BillingService::class)->advice($merchant),
            'paybill' => Billing::payBill(),
            'fee' => Pricing::feeLabel(),
            'threshold' => Pricing::thresholdLabel(),
        ];
    }
}
