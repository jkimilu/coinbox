<?php

namespace App\Filament\Merchant\Resources\Transactions\Pages;

use App\Filament\Merchant\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;
}
