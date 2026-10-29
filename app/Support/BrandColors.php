<?php

namespace App\Support;

use Filament\Support\Colors\Color;

/**
 * QuickBooks-style green for the Filament panels. The brand green (#2CA01C) is shade 400;
 * 500+ are deeper so Filament's buttons (bg 600, hover 500) keep white text at WCAG AA.
 */
class BrandColors
{
    public static function primary(): array
    {
        $hex = [
            50 => '#f0f9ee', 100 => '#d7efd1', 200 => '#a3d896', 300 => '#5cb948', 400 => '#2ca01c',
            500 => '#227f15', 600 => '#1d6f12', 700 => '#185c0f', 800 => '#144a0d', 900 => '#103b0b', 950 => '#0a2507',
        ];

        return array_map(fn ($c) => Color::convertToOklch($c), $hex);
    }
}
