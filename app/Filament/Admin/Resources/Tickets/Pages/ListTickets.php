<?php

namespace App\Filament\Admin\Resources\Tickets\Pages;

use App\Filament\Admin\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    public function getSubheading(): ?string
    {
        return 'Merchant tickets and website enquiries to '.config('coinbox.contact.support').' and '.config('coinbox.contact.sales').'.';
    }
}
