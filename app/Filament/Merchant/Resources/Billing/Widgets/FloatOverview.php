<?php

namespace App\Filament\Merchant\Resources\Billing\Widgets;

use App\Billing\BillingService;
use App\Billing\StkTopUpService;
use App\Filament\Merchant\Concerns\PaysWithMpesa;
use App\Support\Billing;
use App\Support\Pricing;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class FloatOverview extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use PaysWithMpesa;

    protected string $view = 'filament.merchant.widgets.float-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    #[On('coinbox-stk-sent')]
    public function trackStk(string $id): void
    {
        $this->pendingStkId = $id;
    }

    #[On('coinbox-float-updated')]
    public function refreshFloat(): void
    {
        // re-render with fresh balances
    }

    protected function getViewData(): array
    {
        $merchant = auth()->user()->merchant->fresh();
        $pending = $this->pendingStk();

        return [
            'merchant' => $merchant,
            'advice' => app(BillingService::class)->advice($merchant),
            'paybill' => Billing::payBill(),
            'fee' => Pricing::feeLabel(),
            'threshold' => Pricing::thresholdLabel(),
            'stkAvailable' => app(StkTopUpService::class)->account() !== null,
            'pending' => $pending,
            'pendingMessage' => $pending ? StkTopUpService::message($pending) : null,
        ];
    }
}
