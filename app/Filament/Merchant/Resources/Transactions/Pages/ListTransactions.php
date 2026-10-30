<?php

namespace App\Filament\Merchant\Resources\Transactions\Pages;

use App\Exports\TransactionsExport;
use App\Filament\Merchant\Resources\Transactions\TransactionResource;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->tooltip('Downloads the transactions currently shown: your filters, search and sort are applied')
                ->action(fn () => $this->exportToExcel()),
        ];
    }

    public function exportToExcel(): ?BinaryFileResponse
    {
        $query = $this->getFilteredSortedTableQuery(); // already limited to this merchant

        if (! (clone $query)->exists()) {
            Notification::make()->warning()->title('Nothing to export')->body('No transactions match the current filters.')->send();

            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'cbx-export-').'.xlsx';
        $stats = TransactionsExport::write($query, $path);

        AuditLog::record('transactions.exported', null, [
            'rows' => $stats['rows'],
            'filters' => array_filter($this->tableFilters ?? []),
            'search' => $this->tableSearch ?: null,
        ]);

        if ($stats['truncated']) {
            Notification::make()->warning()->title('Export limited to '.number_format(TransactionsExport::MAX_ROWS).' rows')
                ->body('Narrow the date range to export the rest.')->send();
        }

        $name = 'coinbox-transactions-'.now('Africa/Nairobi')->format('Y-m-d-His').'.xlsx';

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
