<?php

namespace App\Filament\Merchant\Widgets;

use App\Billing\BillingService;
use App\Support\Billing;
use Filament\Widgets\Widget;

/** Dashboard banner shown only when the merchant should top up their float. */
class TopUpReminder extends Widget
{
    protected string $view = 'filament.merchant.widgets.top-up-reminder';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        $merchant = auth()->user()?->merchant;

        return $merchant && app(BillingService::class)->advice($merchant)['top_up'] > 0;
    }

    protected function getViewData(): array
    {
        $merchant = auth()->user()->merchant;

        return ['merchant' => $merchant, 'advice' => app(BillingService::class)->advice($merchant), 'paybill' => Billing::payBill()];
    }
}
