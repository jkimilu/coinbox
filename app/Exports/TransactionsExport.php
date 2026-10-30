<?php

namespace App\Exports;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes transactions to .xlsx: real numbers for amounts, real dates (Nairobi time),
 * frozen bold header, and a totals row. Streams rows in chunks.
 */
class TransactionsExport
{
    public const MAX_ROWS = 100_000;

    protected const HEADINGS = [
        'Date (EAT)', 'M-Pesa receipt', 'Type', 'Status', 'Amount (KES)', 'Customer', 'Phone',
        'Reference', 'Account no.', 'Account no. name', 'Customer entered', 'Paid into', 'Short code', 'Account', 'CoinBox ID',
    ];

    /** @return array{rows: int, total: float, truncated: bool} */
    public static function write(Builder $query, string $path): array
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Transactions');
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));
        foreach ([18, 15, 10, 11, 14, 24, 15, 18, 12, 20, 20, 10, 12, 20, 30] as $i => $w) {
            $sheet->setColumnWidth($w, $i + 1);
        }

        $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('227F15');
        $date = (new Style)->setFormat('yyyy-mm-dd hh:mm');
        $money = (new Style)->setFormat('#,##0.00');
        $writer->addRow(Row::fromValues(self::HEADINGS, $header));

        $rows = 0;
        $total = 0.0;
        $truncated = false;

        $query->with(['paymentAccount', 'accountRoute'])->reorder()->orderBy('paid_at', 'desc')->orderBy('id', 'desc')
            ->chunk(1000, function ($chunk) use ($writer, $date, $money, &$rows, &$total, &$truncated) {
                foreach ($chunk as $t) {
                    /** @var Transaction $t */
                    if ($rows >= self::MAX_ROWS) {
                        $truncated = true;

                        return false;
                    }

                    $signed = ($t->type === 'reversal' ? -1 : 1) * (float) $t->amount;
                    $total += $signed;
                    $rows++;

                    $writer->addRow(Row::fromValuesWithStyles([
                        // Excel has no time zones: write the Nairobi wall-clock time.
                        new \DateTimeImmutable($t->paid_at->setTimezone('Africa/Nairobi')->format('Y-m-d H:i:s')),
                        $t->provider_transaction_id,
                        ucfirst($t->type),
                        ucfirst($t->status),
                        $signed,
                        $t->payer_name,
                        $t->payer_identifier,
                        $t->merchant_reference,
                        $t->accountRoute?->code,
                        $t->accountRoute?->name,
                        $t->metadata['bill_ref_number'] ?? null,
                        $t->payment_method === 'till' ? 'Till' : 'PayBill',
                        $t->paymentAccount?->external_account_reference,
                        $t->paymentAccount?->display_name,
                        'txn_'.$t->id,
                    ], null, [0 => $date, 4 => $money]));
                }
            });

        $bold = (new Style)->setFontBold();
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyles(['Total', $rows.' transactions', '', '', $total], $bold, [4 => (new Style)->setFontBold()->setFormat('#,##0.00')]));
        if ($truncated) {
            $writer->addRow(Row::fromValues(['Export limited to the first '.number_format(self::MAX_ROWS).' rows. Narrow the date range to export the rest.']));
        }

        $writer->close();

        return ['rows' => $rows, 'total' => $total, 'truncated' => $truncated];
    }
}
