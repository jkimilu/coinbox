<?php

namespace App\Filament\Merchant\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Renders docs/mpesa-merchant-setup.md so the in-app guide and the docs never drift. */
class SetupGuide extends Page
{
    protected string $view = 'filament.merchant.pages.setup-guide';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'M-Pesa setup guide';

    protected static ?string $title = 'M-Pesa setup guide';

    protected static ?int $navigationSort = 10;

    public function getGuideHtml(): HtmlString
    {
        $markdown = file_get_contents(base_path('docs/mpesa-merchant-setup.md'));
        $markdown = str_replace('YOUR-COINBOX-DOMAIN', parse_url(config('app.url'), PHP_URL_HOST) ?: 'YOUR-COINBOX-DOMAIN', $markdown);
        // Drop the H1; the page already has a title.
        $markdown = preg_replace('/^# .*\n/', '', $markdown, 1);

        return new HtmlString(Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }
}
