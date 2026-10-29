<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Operations';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 12];
    }

    public function getSubheading(): ?string
    {
        return now('Africa/Nairobi')->format('l j F Y, H:i').' EAT';
    }
}
