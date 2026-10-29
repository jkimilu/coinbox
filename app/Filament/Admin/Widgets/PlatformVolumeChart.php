<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Merchant\Widgets\PaymentsChart;
use App\Support\MerchantMetrics as M;
use App\Support\PlatformMetrics;

/** Same chart design as the merchant panel, across all merchants. */
class PlatformVolumeChart extends PaymentsChart
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Payments processed (all merchants)';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 8];

    public function getDescription(): ?string
    {
        return M::kes(array_sum(PlatformMetrics::daily((int) $this->filter)), 2).' recorded. Funds settle in merchants’ own accounts.';
    }

    protected function dailyTotals(): array
    {
        return PlatformMetrics::daily((int) $this->filter);
    }
}
