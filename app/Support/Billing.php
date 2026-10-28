<?php

namespace App\Support;

use App\Models\Setting;

/** Where and how merchants pay CoinBox (all editable in admin → Pricing). */
class Billing
{
    public static function payBill(): string
    {
        return (string) Setting::get('billing.paybill', '4072129');
    }

    /** CoinBox's own connected PaymentAccount that receives merchant payments, if connected. */
    public static function platformAccountId(): ?string
    {
        return Setting::get('billing.platform_account_id');
    }

    public static function dueDays(): int
    {
        return (int) Setting::get('billing.due_days', 14);
    }
}
