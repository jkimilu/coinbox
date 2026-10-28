<?php

namespace App\Filament\Admin\Resources\ProviderEvents\Pages;

use App\Filament\Admin\Resources\ProviderEvents\ProviderEventResource;
use Filament\Resources\Pages\ListRecords;

class ListProviderEvents extends ListRecords
{
    protected static string $resource = ProviderEventResource::class;
}
