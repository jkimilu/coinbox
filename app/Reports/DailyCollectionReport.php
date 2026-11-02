<?php

namespace App\Reports;

use App\Models\AccountRoute;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * One merchant's collections for one Nairobi calendar day: totals, every shortcode and its
 * account numbers, hourly activity and the reversals made that day.
 */
class DailyCollectionReport
{
    public const TZ = 'Africa/Nairobi';

    public function build(Merchant $merchant, CarbonImmutable $day): array
    {
        $from = $day->setTimezone(self::TZ)->startOfDay();
        $to = $from->endOfDay();
        [$fromUtc, $toUtc] = [$from->utc(), $to->utc()];

        $accounts = PaymentAccount::query()->where('merchant_id', $merchant->id)
            ->where('status', '!=', 'disabled')->orderBy('display_name')->get();
        $routes = AccountRoute::query()->where('merchant_id', $merchant->id)
            ->where('is_active', true)->orderBy('code')->get()->groupBy('payment_account_id');

        // Reversal rows take the account number of the payment they reverse.
        $rows = DB::table('transactions as t')
            ->leftJoin('transactions as o', 'o.id', '=', 't.reverses_transaction_id')
            ->where('t.merchant_id', $merchant->id)
            ->whereBetween('t.paid_at', [$fromUtc, $toUtc])
            ->groupBy('t.payment_account_id', DB::raw('coalesce(t.account_route_id, o.account_route_id)'))
            ->selectRaw("
                t.payment_account_id as account_id,
                coalesce(t.account_route_id, o.account_route_id) as route_id,
                coalesce(sum(t.amount) filter (where t.type = 'payment' and t.status in ('succeeded','reversed')), 0)::text as received,
                count(*) filter (where t.type = 'payment' and t.status in ('succeeded','reversed')) as received_count,
                coalesce(sum(t.amount) filter (where t.type = 'reversal'), 0)::text as reversed,
                count(*) filter (where t.type = 'reversal') as reversed_count
            ")
            ->get();

        $shortcodes = $accounts->map(function (PaymentAccount $account) use ($rows, $routes) {
            $mine = $rows->where('account_id', $account->id);
            $lines = ($routes[$account->id] ?? collect())->map(fn (AccountRoute $r) => $this->line(
                $r->code.' · '.$r->name, $mine->firstWhere('route_id', $r->id)
            ));

            // Payments with no matching account number (or one since deleted).
            $other = $mine->filter(fn ($row) => ! $row->route_id || ! ($routes[$account->id] ?? collect())->contains('id', $row->route_id));
            if ($other->isNotEmpty()) {
                $lines->push($this->line($lines->isEmpty() ? 'All payments' : 'No account number / other', (object) [
                    'received' => (string) $other->sum(fn ($r) => (float) $r->received),
                    'received_count' => $other->sum('received_count'),
                    'reversed' => (string) $other->sum(fn ($r) => (float) $r->reversed),
                    'reversed_count' => $other->sum('reversed_count'),
                ]));
            }

            return [
                'label' => $account->account_type === 'till'
                    ? 'Till '.($account->config('till_number') ?: $account->external_account_reference)
                    : 'PayBill '.$account->external_account_reference,
                'name' => $account->display_name,
                'totals' => $this->sumLines($lines),
                'lines' => $lines->sortByDesc('received')->values()->all(),
            ];
        })->values();

        $totals = $this->sumLines($shortcodes->pluck('totals'));
        $previous = $this->dayTotals($merchant, $fromUtc->subDay(), $toUtc->subDay());

        return [
            'merchant' => $merchant,
            'date' => $from,
            'totals' => $totals,
            'previous_net' => $previous,
            'change_percent' => $previous > 0 ? (int) round(($totals['net'] - $previous) / $previous * 100) : null,
            'shortcodes' => $shortcodes->all(),
            'hours' => $this->hourly($merchant, $fromUtc, $toUtc),
            'largest' => Transaction::query()->where('merchant_id', $merchant->id)->where('type', Transaction::TYPE_PAYMENT)
                ->whereIn('status', [Transaction::STATUS_SUCCEEDED, Transaction::STATUS_REVERSED])
                ->whereBetween('paid_at', [$fromUtc, $toUtc])->orderByDesc('amount')->first(),
            'reversals' => Transaction::query()->with(['reverses.accountRoute', 'paymentAccount'])
                ->where('merchant_id', $merchant->id)->where('type', Transaction::TYPE_REVERSAL)
                ->whereBetween('paid_at', [$fromUtc, $toUtc])->orderBy('paid_at')->limit(25)->get(),
            'pending_count' => Transaction::query()->where('merchant_id', $merchant->id)
                ->where('status', Transaction::STATUS_PENDING)->whereBetween('paid_at', [$fromUtc, $toUtc])->count(),
        ];
    }

    protected function line(string $label, ?object $row): array
    {
        $received = (float) ($row->received ?? 0);
        $reversed = (float) ($row->reversed ?? 0);

        return [
            'label' => $label,
            'received' => $received,
            'received_count' => (int) ($row->received_count ?? 0),
            'reversed' => $reversed,
            'reversed_count' => (int) ($row->reversed_count ?? 0),
            'net' => $received - $reversed,
        ];
    }

    protected function sumLines($lines): array
    {
        $lines = collect($lines);

        return [
            'received' => $lines->sum('received'),
            'received_count' => $lines->sum('received_count'),
            'reversed' => $lines->sum('reversed'),
            'reversed_count' => $lines->sum('reversed_count'),
            'net' => $lines->sum('net'),
        ];
    }

    protected function dayTotals(Merchant $merchant, $fromUtc, $toUtc): float
    {
        $row = Transaction::query()->where('merchant_id', $merchant->id)->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->toBase()->selectRaw("
                coalesce(sum(amount) filter (where type = 'payment' and status in ('succeeded','reversed')), 0)
                - coalesce(sum(amount) filter (where type = 'reversal'), 0) as net
            ")->first();

        return (float) $row->net;
    }

    /** Collections per Nairobi hour (0–23), for the bar chart. */
    protected function hourly(Merchant $merchant, $fromUtc, $toUtc): array
    {
        $byHour = Transaction::query()->where('merchant_id', $merchant->id)
            ->where('type', Transaction::TYPE_PAYMENT)->whereIn('status', [Transaction::STATUS_SUCCEEDED, Transaction::STATUS_REVERSED])
            ->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->toBase()
            ->selectRaw("extract(hour from (paid_at at time zone 'UTC') at time zone '".self::TZ."')::int as h, sum(amount)::float as total")
            ->groupBy('h')->pluck('total', 'h');

        return collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => (float) ($byHour[$h] ?? 0)])->all();
    }
}
