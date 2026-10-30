<?php

namespace App\Filament\Merchant\Widgets;

use App\Billing\BillingService;
use App\Billing\StkTopUpService;
use App\Filament\Merchant\Concerns\PaysWithMpesa;
use App\Support\Billing;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

/** Dashboard banner shown only when the merchant should top up their float. */
class TopUpReminder extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use PaysWithMpesa;

    protected string $view = 'filament.merchant.widgets.top-up-reminder';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $merchant = auth()->user()?->merchant;

        return $merchant && app(BillingService::class)->advice($merchant)['top_up'] > 0;
    }

    protected function getViewData(): array
    {
        $merchant = auth()->user()->merchant;
        $pending = $this->pendingStk();

        return [
            'merchant' => $merchant,
            'advice' => app(BillingService::class)->advice($merchant),
            'paybill' => Billing::payBill(),
            'pending' => $pending,
            'pendingMessage' => $pending ? StkTopUpService::message($pending) : null,
        ];
    }
}
