<?php

namespace App\Support;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Platform-wide numbers for the admin dashboard (recorded payments; CoinBox holds no funds). */
class PlatformMetrics
{
    public const TZ = 'Africa/Nairobi';

    protected static function base()
    {
        return Transaction::query()->where('type', 'payment')->whereIn('status', ['succeeded', 'reversed']);
    }

    public static function between(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $r = static::base()->whereBetween('paid_at', [$from->utc(), $to->utc()])->toBase()
            ->selectRaw('coalesce(sum(amount),0)::text a, count(*) c, count(distinct merchant_id) m')->first();

        return ['amount' => (float) $r->a, 'count' => (int) $r->c, 'merchants' => (int) $r->m];
    }

    public static function daily(int $days): array
    {
        $end = CarbonImmutable::now(self::TZ)->endOfDay();
        $start = $end->subDays($days - 1)->startOfDay();
        $rows = static::base()->whereBetween('paid_at', [$start->utc(), $end->utc()])->toBase()
            ->selectRaw("to_char((paid_at at time zone 'UTC') at time zone 'Africa/Nairobi','YYYY-MM-DD') d, sum(amount)::text a")
            ->groupBy(DB::raw('1'))->pluck('a', 'd');
        $out = [];
        for ($d = $start; $d <= $end; $d = $d->addDay()) {
            $out[$d->toDateString()] = (float) ($rows[$d->toDateString()] ?? 0);
        }

        return $out;
    }
}
