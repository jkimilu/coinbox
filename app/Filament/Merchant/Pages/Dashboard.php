<?php

namespace App\Filament\Merchant\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 12];
    }

    // The welcome banner is the page heading.
    public function getHeading(): string
    {
        return '';
    }
}
