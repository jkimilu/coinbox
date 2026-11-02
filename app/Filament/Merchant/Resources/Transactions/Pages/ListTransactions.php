<?php

namespace App\Filament\Merchant\Resources\Transactions\Pages;

use App\Exports\TransactionsExport;
use App\Filament\Merchant\Concerns\RequestsPayments;
use App\Filament\Merchant\Resources\Transactions\TransactionResource;
use App\Mail\TransactionsEmail;
use App\Models\AccountRoute;
use App\Models\AuditLog;
use App\Models\PaymentAccount;
use App\Payments\CollectionSummary;
use Filament\Forms\Components\Checkbox;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListTransactions extends ListRecords
{
    use RequestsPayments;

    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->requestPaymentAction(),
            Action::make('emailMe')
                ->label('Email me')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->tooltip('Emails the transactions currently shown: your filters, search and sort are applied')
                ->modalHeading('Email these transactions')
                ->modalDescription(fn () => 'Sends the transactions shown on this page to '.auth()->user()->email.'.')
                ->modalIcon(Heroicon::OutlinedEnvelope)
                ->modalWidth('md')
                ->modalSubmitActionLabel('Send email')
                ->schema([
                    Checkbox::make('attach_excel')->label('Attach Excel file')
                        ->helperText('The email shows up to '.TransactionsEmail::MAX_ROWS_IN_BODY.' rows. The Excel file has all of them.'),
                ])
                ->action(fn (array $data) => $this->emailTransactions((bool) ($data['attach_excel'] ?? false))),
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

    public function emailTransactions(bool $attachExcel): void
    {
        $user = auth()->user();
        $query = $this->getFilteredSortedTableQuery(); // already limited to this merchant
        $count = (clone $query)->count();

        $t = app(CollectionSummary::class)->totals((clone $query)->reorder());
        $totals = [
            'received' => (float) $t['successful_payments']['amount'], 'received_count' => $t['successful_payments']['count'],
            'reversed' => (float) $t['reversals']['amount'], 'reversed_count' => $t['reversals']['count'],
            'net' => (float) $t['net_recorded_collections'],
        ];
        $rows = (clone $query)->with(['paymentAccount', 'accountRoute', 'reverses.accountRoute'])->limit(TransactionsEmail::MAX_ROWS_IN_BODY)->get();

        $path = null;
        try {
            if ($attachExcel && $count > 0) {
                $path = tempnam(sys_get_temp_dir(), 'cbx-mail-').'.xlsx';
                TransactionsExport::write(clone $query, $path);
            }

            Mail::to($user)->send(new TransactionsEmail(
                $user->merchant->name, $rows, $count, $totals, $this->describeFilters(), $path,
                'coinbox-transactions-'.now('Africa/Nairobi')->format('Y-m-d-His').'.xlsx',
            ));
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title('Email not sent')->body('We couldn’t send the email. Please try again in a moment.')->send();

            return;
        } finally {
            $path && @unlink($path);
        }

        AuditLog::record('transactions.emailed', null, ['rows' => $count, 'excel' => $attachExcel, 'filters' => array_filter($this->tableFilters ?? [])]);

        Notification::make()->success()->title('Email sent')
            ->body(number_format($count).' '.str('transaction')->plural($count).' sent to '.$user->email.($attachExcel ? ', with the Excel file.' : '.'))->send();
    }

    /** Plain-language list of the active filters, e.g. ["1 Nov – 2 Nov 2026", "Status: Succeeded"]. */
    protected function describeFilters(): array
    {
        $f = $this->tableFilters ?? [];
        $merchantId = auth()->user()->merchant_id;
        $date = fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('j M Y');
        $out = [];

        $from = $f['paid_at']['from'] ?? null;
        $until = $f['paid_at']['until'] ?? null;
        if ($from || $until) {
            $out[] = match (true) {
                $from && $until => $date($from).' – '.$date($until),
                (bool) $from => 'From '.$date($from),
                default => 'Until '.$date($until),
            };
        }
        if ($id = $f['payment_account_id']['value'] ?? null) {
            $a = PaymentAccount::query()->where('merchant_id', $merchantId)->find($id);
            $a && $out[] = 'Short code '.$a->external_account_reference;
        }
        if ($id = $f['account_route_id']['value'] ?? null) {
            $r = AccountRoute::query()->where('merchant_id', $merchantId)->find($id);
            $r && $out[] = 'Account '.$r->code.' '.$r->name;
        }
        foreach (['status' => 'Status', 'type' => 'Type'] as $key => $label) {
            if ($v = $f[$key]['value'] ?? null) {
                $out[] = $label.': '.ucfirst($v);
            }
        }
        if (filled($this->tableSearch)) {
            $out[] = 'Search “'.$this->tableSearch.'”';
        }

        return $out;
    }
}
