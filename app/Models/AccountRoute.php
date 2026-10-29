<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An account number inside a PayBill/Till. Customers pay Account "<code>" or "<code>#<reference>",
 * e.g. "100#INV001"; the payment is tagged with this route and sent to its webhook.
 */
class AccountRoute extends Model
{
    use HasUlids;

    public const SEPARATOR = '#';

    protected $fillable = ['merchant_id', 'payment_account_id', 'code', 'name', 'callback_endpoint_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $route) {
            $route->code = strtoupper(trim($route->code));
            $route->merchant_id ??= $route->paymentAccount?->merchant_id;
        });
    }

    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }
    public function paymentAccount(): BelongsTo { return $this->belongsTo(PaymentAccount::class); }
    public function callbackEndpoint(): BelongsTo { return $this->belongsTo(CallbackEndpoint::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }

    /** Example account number customers enter, e.g. "100#INV001". */
    public function example(): string
    {
        return $this->code.self::SEPARATOR.'INV001';
    }
}
