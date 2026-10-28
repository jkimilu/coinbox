<?php

namespace App\Filament\Merchant\Resources\Billing\Pages;

use App\Filament\Merchant\Resources\Billing\BillingResource;
use App\Filament\Merchant\Resources\Billing\Widgets\FloatHistory;
use App\Filament\Merchant\Resources\Billing\Widgets\FloatOverview;
use Filament\Resources\Pages\ListRecords;

class ListBilling extends ListRecords
{
    protected static string $resource = BillingResource::class;

    protected static ?string $title = 'Billing';

    protected function getHeaderWidgets(): array
    {
        return [FloatOverview::class];
    }

    protected function getFooterWidgets(): array
    {
        return [FloatHistory::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
