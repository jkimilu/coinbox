<?php

namespace App\Filament\Merchant\Resources\CallbackEndpoints\Pages;

use App\Filament\Merchant\Resources\CallbackEndpoints\CallbackEndpointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCallbackEndpoints extends ManageRecords
{
    protected static string $resource = CallbackEndpointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add endpoint')
                ->mutateDataUsing(fn (array $data) => [...$data, 'merchant_id' => auth()->user()->merchant_id]),
        ];
    }
}
