<?php

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/** Green circle with the user's initials (e.g. "JK"), rendered locally as an SVG data URI. */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg((string) ($record->name ?? $record->email ?? '?')));
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr(end($words), 0, 1) : mb_substr($words[0], 1, 1);

        return mb_strtoupper($first.$last);
    }

    public static function svg(string $name): string
    {
        $text = htmlspecialchars(self::initials($name), ENT_XML1);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="32" fill="#2CA01C"/><text x="32" y="33" dy=".35em" text-anchor="middle" font-family="Inter,Segoe UI,Helvetica,Arial,sans-serif" font-size="25" font-weight="700" fill="#fff" letter-spacing="-.5">{$text}</text></svg>
        SVG;
    }
}
