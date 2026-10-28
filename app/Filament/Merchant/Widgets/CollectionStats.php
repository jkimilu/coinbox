<?php

namespace App\Filament\Merchant\Widgets;

use App\Models\Transaction;
use App\Payments\CollectionSummary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CollectionStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected ?string $description = 'Recorded by CoinBox from Safaricom notifications. Not your M-Pesa balance.';

    protected function getStats(): array
    {
        $merchantId = auth()->user()->merchant_id;
        $summary = app(CollectionSummary::class);
        $q = fn () => Transaction::query()->where('merchant_id', $merchantId);

        $today = $summary->totals($q()->where('paid_at', '>=', now('Africa/Nairobi')->startOfDay()->utc()));
        $month = $summary->totals($q()->where('paid_at', '>=', now('Africa/Nairobi')->startOfMonth()->utc()));
        $all = $summary->totals($q());

        $kes = fn ($v) => 'KES '.number_format((float) $v, 2);

        return [
            Stat::make("Today's collections", $kes($today['net_recorded_collections']))
                ->description($today['successful_payments']['count'].' payments'),
            Stat::make('This month', $kes($month['net_recorded_collections']))
                ->description($month['successful_payments']['count'].' payments · '.$month['reversals']['count'].' reversals'),
            Stat::make('All time (net recorded)', $kes($all['net_recorded_collections']))
                ->description($kes($all['reversals']['amount']).' reversed'),
        ];
    }
}
