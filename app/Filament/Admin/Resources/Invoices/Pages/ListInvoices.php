<?php

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Billing\BillingService;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label('Generate invoices')->icon(Heroicon::OutlinedPlay)
                ->modalDescription('Issues invoices for every active merchant above the free allowance for the chosen month, then pays them from floats where possible. Months already invoiced are skipped. This also runs automatically on the 1st of each month.')
                ->schema([
                    DatePicker::make('month')->label('Month')->displayFormat('F Y')->native(false)
                        ->default(now('Africa/Nairobi')->subMonthNoOverflow()->startOfMonth())->required(),
                ])
                ->action(function (array $data) {
                    $count = app(BillingService::class)->generateForMonth(CarbonImmutable::parse($data['month'], 'Africa/Nairobi'));
                    Notification::make()->success()->title("Issued {$count} invoice(s)")->send();
                }),
        ];
    }
}
