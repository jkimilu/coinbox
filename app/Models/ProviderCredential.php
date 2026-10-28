<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encrypted provider secrets. Never serialised to the merchant API.
 */
class ProviderCredential extends Model
{
    use HasUlids;

    protected $fillable = ['merchant_id', 'provider_id', 'environment', 'secrets'];

    protected $hidden = ['secrets'];

    protected function casts(): array
    {
        return ['secrets' => 'encrypted:array'];
    }

    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }
    public function provider(): BelongsTo { return $this->belongsTo(PaymentProvider::class); }

    public function secret(string $key, mixed $default = null): mixed
    {
        return data_get($this->secrets, $key, $default);
    }
}
