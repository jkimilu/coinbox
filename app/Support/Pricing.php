<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Processing-fee rules: free while a merchant's monthly processed volume is below the
 * threshold; from the threshold, the fee is a percentage of that month's total volume.
 */
class Pricing
{
    public const DEFAULT_FEE_PERCENT = 1.0;
    public const DEFAULT_FREE_THRESHOLD = 10000;

    public static function feePercent(): float
    {
        return (float) Setting::get('pricing.fee_percent', self::DEFAULT_FEE_PERCENT);
    }

    public static function freeThreshold(): int
    {
        return (int) Setting::get('pricing.free_threshold', self::DEFAULT_FREE_THRESHOLD);
    }

    /** Fee in KES for a month's processed volume. */
    public static function feeFor(float $monthlyVolume): float
    {
        return $monthlyVolume >= self::freeThreshold()
            ? round($monthlyVolume * self::feePercent() / 100, 2)
            : 0.0;
    }

    /** "1%", "0.05%", "1.5%" */
    public static function feeLabel(): string
    {
        return rtrim(rtrim(number_format(self::feePercent(), 2, '.', ''), '0'), '.').'%';
    }

    /** "KES 10,000" */
    public static function thresholdLabel(): string
    {
        return 'KES '.number_format(self::freeThreshold());
    }
}
