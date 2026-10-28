<?php

namespace App\Console\Commands;

use App\Models\PaymentAccount;
use App\Payments\Reconciler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Reconcile an M-Pesa Business portal statement export (CSV) against recorded transactions.
 */
class ReconcileStatement extends Command
{
    protected $signature = 'coinbox:reconcile {shortcode} {csv} {--from=} {--to=}';

    protected $description = 'Reconcile an M-Pesa org-portal statement CSV against gateway transactions';

    public function handle(Reconciler $reconciler): int
    {
        $account = PaymentAccount::query()->where('external_account_reference', $this->argument('shortcode'))->firstOrFail();
        $rows = array_map('str_getcsv', file($this->argument('csv'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));

        // The export has a preamble; find the header row.
        $headerIndex = collect($rows)->search(fn ($r) => collect($r)->contains(fn ($c) => str_starts_with(trim((string) $c), 'Receipt No')));
        if ($headerIndex === false) {
            $this->error('Could not find a "Receipt No." header row.');

            return self::FAILURE;
        }

        $header = array_map(fn ($h) => strtolower(trim($h, " .\t")), $rows[$headerIndex]);
        $col = fn (string $name) => array_search($name, $header, true);
        [$cReceipt, $cTime, $cPaidIn, $cStatus] = [$col('receipt no'), $col('completion time'), $col('paid in'), $col('transaction status')];

        $records = [];
        $times = [];
        foreach (array_slice($rows, $headerIndex + 1) as $r) {
            $paidIn = (float) str_replace(',', '', $r[$cPaidIn] ?? '0');
            if (! isset($r[$cReceipt]) || $paidIn <= 0 || ($cStatus !== false && strcasecmp(trim($r[$cStatus] ?? ''), 'Completed') !== 0)) {
                continue;
            }
            $records[] = ['receipt' => $r[$cReceipt], 'amount' => (string) $paidIn];
            if ($cTime !== false && ! empty($r[$cTime])) {
                $times[] = CarbonImmutable::parse($r[$cTime], 'Africa/Nairobi');
            }
        }

        $from = $this->option('from') ? CarbonImmutable::parse($this->option('from'), 'Africa/Nairobi')->startOfDay() : collect($times)->min();
        $to = $this->option('to') ? CarbonImmutable::parse($this->option('to'), 'Africa/Nairobi')->endOfDay() : collect($times)->max();

        if (! $from || ! $to) {
            $this->error('Pass --from and --to, or include a Completion Time column.');

            return self::FAILURE;
        }

        $run = $reconciler->run($account, $from->utc(), $to->utc(), $records);

        $this->table(['Matched', 'Missing in gateway', 'Missing at provider', 'Amount mismatch'], [[
            $run->matched_count, $run->missing_in_gateway_count, $run->missing_at_provider_count, $run->amount_mismatch_count,
        ]]);

        return self::SUCCESS;
    }
}
