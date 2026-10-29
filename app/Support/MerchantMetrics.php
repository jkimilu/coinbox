<?php

namespace App\Support;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Dashboard numbers for one merchant. Days are Nairobi calendar days; DB timestamps are UTC. */
class MerchantMetrics
{
    public const TZ = 'Africa/Nairobi';

    public function __construct(public string $merchantId) {}

    protected function base()
    {
        return Transaction::query()
            ->where('merchant_id', $this->merchantId)
            ->where('type', Transaction::TYPE_PAYMENT)
            ->whereIn('status', [Transaction::STATUS_SUCCEEDED, Transaction::STATUS_REVERSED]);
    }

    /** @return array{amount: float, count: int} */
    public function between(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->base()->whereBetween('paid_at', [$from->utc(), $to->utc()])
            ->toBase()->selectRaw('coalesce(sum(amount),0)::text as a, count(*) as c')->first();

        return ['amount' => (float) $row->a, 'count' => (int) $row->c];
    }

    /** Daily received totals for the last $days days, oldest first. @return array<string, float> Y-m-d => amount */
    public function daily(int $days): array
    {
        $end = CarbonImmutable::now(self::TZ)->endOfDay();
        $start = $end->subDays($days - 1)->startOfDay();

        $rows = $this->base()->whereBetween('paid_at', [$start->utc(), $end->utc()])
            ->toBase()
            ->selectRaw("to_char((paid_at at time zone 'UTC') at time zone 'Africa/Nairobi', 'YYYY-MM-DD') as d, sum(amount)::text as a")
            ->groupBy(DB::raw('1'))->pluck('a', 'd');

        $out = [];
        for ($d = $start; $d <= $end; $d = $d->addDay()) {
            $out[$d->toDateString()] = (float) ($rows[$d->toDateString()] ?? 0);
        }

        return $out;
    }

    /** Percentage change, or null when there is no baseline. */
    public static function change(float $now, float $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }

    public static function kes(float $v, int $decimals = 0): string
    {
        return 'KES '.number_format($v, $decimals);
    }
}
