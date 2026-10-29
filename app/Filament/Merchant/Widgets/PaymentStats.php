<?php

namespace App\Filament\Merchant\Widgets;

use App\Support\MerchantMetrics as M;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected static bool $isLazy = false;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $m = new M(auth()->user()->merchant_id);
        $now = CarbonImmutable::now(M::TZ);

        $today = $m->between($now->startOfDay(), $now);
        $yesterdaySoFar = $m->between($now->subDay()->startOfDay(), $now->subDay());
        $week = $m->between($now->subDays(6)->startOfDay(), $now);
        $prevWeek = $m->between($now->subDays(13)->startOfDay(), $now->subDays(7)->endOfDay());
        $month = $m->between($now->startOfMonth(), $now);
        $prevMonth = $m->between($now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow());
        $daily = $m->daily(14);
        $spark = array_values($daily);

        $avg = $month['count'] ? $month['amount'] / $month['count'] : 0;

        return [
            $this->stat('Received today', $today['amount'], M::change($today['amount'], $yesterdaySoFar['amount']), 'vs this time yesterday', $today['count'].' '.str('payment')->plural($today['count']))
                ->chart(array_slice($spark, -7)),
            $this->stat('Last 7 days', $week['amount'], M::change($week['amount'], $prevWeek['amount']), 'vs previous 7 days', $week['count'].' payments')
                ->chart(array_slice($spark, -7)),
            $this->stat('This month', $month['amount'], M::change($month['amount'], $prevMonth['amount']), 'vs same point last month', $month['count'].' payments')
                ->chart($spark),
            Stat::make('Average payment', M::kes($avg))
                ->description('This month, across all accounts')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
        ];
    }

    protected function stat(string $label, float $amount, ?float $change, string $period, string $fallback): Stat
    {
        $stat = Stat::make($label, M::kes($amount))->color('primary');

        // No trend when there's no baseline, or nothing yet in this period (avoid an alarming "-100%").
        if ($change === null || $amount <= 0) {
            return $stat->description($amount <= 0 ? 'No payments yet' : $fallback)->descriptionColor('gray');
        }

        $up = $change >= 0;

        return $stat
            ->description(($up ? '+' : '').$change.'% '.$period)
            ->descriptionIcon($up ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->descriptionColor($up ? 'success' : 'danger');
    }
}
