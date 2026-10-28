<?php

namespace App\Payments;

use App\Models\PaymentAccount;
use App\Models\ReconciliationRun;
use App\Models\Transaction;
use Carbon\CarbonInterface;

/**
 * Matches provider records (from a statement export or provider API) against gateway transactions.
 */
class Reconciler
{
    /**
     * @param  iterable<array{receipt: string, amount: string}>  $providerRecords
     */
    public function run(PaymentAccount $account, CarbonInterface $from, CarbonInterface $to, iterable $providerRecords, string $source = 'statement_import'): ReconciliationRun
    {
        $run = ReconciliationRun::create([
            'payment_account_id' => $account->id,
            'period_start' => $from,
            'period_end' => $to,
            'source' => $source,
            'status' => 'running',
        ]);

        $gateway = Transaction::query()
            ->where('payment_account_id', $account->id)
            ->where('type', Transaction::TYPE_PAYMENT)
            ->whereBetween('paid_at', [$from, $to])
            ->get()
            ->keyBy('provider_transaction_id');

        $matched = 0;
        $discrepancies = [];
        $seen = [];

        foreach ($providerRecords as $rec) {
            $receipt = strtoupper(trim($rec['receipt']));
            $seen[$receipt] = true;
            $txn = $gateway->get($receipt);

            if (! $txn) {
                $discrepancies[] = ['type' => 'missing_in_gateway', 'receipt' => $receipt, 'provider_amount' => $rec['amount']];
            } elseif (self::compareAmounts((string) $txn->amount, (string) $rec['amount']) !== 0) {
                $discrepancies[] = ['type' => 'amount_mismatch', 'receipt' => $receipt, 'gateway_amount' => (string) $txn->amount, 'provider_amount' => $rec['amount']];
            } else {
                $matched++;
            }
        }

        foreach ($gateway as $receipt => $txn) {
            if (! isset($seen[$receipt])) {
                $discrepancies[] = ['type' => 'missing_at_provider', 'receipt' => $receipt, 'gateway_amount' => (string) $txn->amount];
            }
        }

        $count = fn ($t) => count(array_filter($discrepancies, fn ($d) => $d['type'] === $t));

        $run->update([
            'status' => 'completed',
            'matched_count' => $matched,
            'missing_in_gateway_count' => $count('missing_in_gateway'),
            'missing_at_provider_count' => $count('missing_at_provider'),
            'amount_mismatch_count' => $count('amount_mismatch'),
            'discrepancies' => $discrepancies,
            'completed_at' => now(),
        ]);

        return $run;
    }

    /** Compare two decimal strings at 2dp (bcmath is not required). */
    private static function compareAmounts(string $a, string $b): int
    {
        $toCents = fn (string $v) => (int) round(((float) str_replace(',', '', $v)) * 100);

        return $toCents($a) <=> $toCents($b);
    }
}
