<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Raw provider notification, stored before any processing. */
class ProviderEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo { return $this->belongsTo(PaymentProvider::class); }
    public function paymentAccount(): BelongsTo { return $this->belongsTo(PaymentAccount::class); }
}
