<?php

namespace App\Filament\Merchant\Concerns;

use Illuminate\Database\Eloquent\Builder;

/** Restricts a merchant-panel resource to the signed-in merchant's records. */
trait ScopedToMerchant
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(
            static::getModel()::make()->qualifyColumn('merchant_id'),
            auth()->user()?->merchant_id
        );
    }

    protected static function merchantId(): ?string
    {
        return auth()->user()?->merchant_id;
    }
}
