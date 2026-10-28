<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Transaction;
use App\Payments\CollectionSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends ApiController
{
    public function collections(Request $request, CollectionSummary $summary)
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'account_id' => 'nullable|string',
            'group_by' => 'nullable|in:day,month',
        ]);

        $tz = 'Africa/Nairobi';
        // Dates are Nairobi calendar days; DB timestamps are UTC.
        $from = CarbonImmutable::parse($data['from'], $tz)->startOfDay()->utc();
        $to = CarbonImmutable::parse($data['to'], $tz)->endOfDay()->utc();

        if ($from->diffInDays($to) > 366) {
            return response()->json(['error' => ['code' => 'range_too_large', 'message' => 'Maximum range is 366 days.']], 422);
        }

        $base = fn () => Transaction::query()
            ->where('merchant_id', $this->merchant($request)->id)
            ->when($data['account_id'] ?? null, fn ($q, $v) => $q->where('payment_account_id', $this->stripPrefix($v, 'acct')));

        $unit = $data['group_by'] ?? 'day';
        $buckets = $base()->whereBetween('paid_at', [$from, $to])
            ->selectRaw("to_char(date_trunc(?, (paid_at at time zone 'UTC') at time zone 'Africa/Nairobi'), ?) as period", [$unit, $unit === 'day' ? 'YYYY-MM-DD' : 'YYYY-MM'])
            ->selectRaw("coalesce(sum(amount) filter (where type='payment' and status in ('succeeded','reversed')),0)::text as received")
            ->selectRaw("coalesce(sum(amount) filter (where type='reversal'),0)::text as reversed")
            ->selectRaw("count(*) filter (where type='payment') as payment_count")
            ->groupBy(DB::raw('1'))->orderBy(DB::raw('1'))
            ->toBase()->get();

        return response()->json([
            'object' => 'collection_report',
            'from' => $from->setTimezone($tz)->toIso8601String(),
            'to' => $to->setTimezone($tz)->toIso8601String(),
            'disclaimer' => 'Totals of payments recorded by the gateway; not an account balance.',
            'totals' => $summary->forPeriod($base(), $from, $to),
            'breakdown' => $buckets,
        ]);
    }
}
