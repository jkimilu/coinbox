<?php

namespace App\Filament\Admin\Widgets;

use App\Models\BillingInvoice;
use App\Models\FloatEntry;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Support\MerchantMetrics as M;
use App\Support\PlatformMetrics as P;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $now = CarbonImmutable::now(P::TZ);
        $today = P::between($now->startOfDay(), $now);
        $yesterday = P::between($now->subDay()->startOfDay(), $now->subDay());
        $d30 = P::between($now->subDays(29)->startOfDay(), $now);
        $prev30 = P::between($now->subDays(59)->startOfDay(), $now->subDays(30)->endOfDay());
        $spark = array_values(P::daily(14));

        $merchants = Merchant::query()->where('status', 'active')->count();
        $newThisMonth = Merchant::query()->where('created_at', '>=', $now->startOfMonth()->utc())->count();
        $liveAccounts = PaymentAccount::query()->where('status', 'active')->count();

        $billedThisMonth = (float) BillingInvoice::query()->whereDate('period_start', $now->subMonthNoOverflow()->startOfMonth()->toDateString())->sum('amount');
        $outstanding = BillingInvoice::query()->where('status', 'open')->get()->sum(fn ($i) => $i->outstanding());
        $float = (float) FloatEntry::query()->sum('amount');

        $trend = function (Stat $s, ?float $c, string $period) {
            if ($c === null) {
                return $s->descriptionColor('gray');
            }

            return $s->description(($c >= 0 ? '+' : '').$c.'% '.$period)
                ->descriptionIcon($c >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($c >= 0 ? 'success' : 'danger');
        };

        return [
            $trend(Stat::make('Processed today', M::kes($today['amount']))->description($today['count'].' payments')->chart(array_slice($spark, -7))->color('primary'),
                $today['amount'] > 0 ? M::change($today['amount'], $yesterday['amount']) : null, 'vs this time yesterday'),
            $trend(Stat::make('Processed, 30 days', M::kes($d30['amount']))->description(number_format($d30['count']).' payments')->chart($spark)->color('primary'),
                M::change($d30['amount'], $prev30['amount']), 'vs previous 30 days'),
            Stat::make('Active merchants', number_format($merchants))
                ->description($d30['merchants'].' processed in 30d · '.$liveAccounts.' live accounts'.($newThisMonth ? ' · +'.$newThisMonth.' this month' : ''))
                ->descriptionIcon('heroicon-m-building-storefront')->color('gray'),
            Stat::make('Fees billed last month', M::kes($billedThisMonth))
                ->description(M::kes($outstanding).' outstanding · '.M::kes($float).' in floats')
                ->descriptionIcon('heroicon-m-banknotes')->descriptionColor($outstanding > 0 ? 'warning' : 'gray'),
        ];
    }
}
