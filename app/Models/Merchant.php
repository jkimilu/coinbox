<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'email', 'phone', 'kra_pin', 'status'];

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function paymentAccounts(): HasMany { return $this->hasMany(PaymentAccount::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }
    public function callbackEndpoints(): HasMany { return $this->hasMany(CallbackEndpoint::class); }
    public function apiKeys(): HasMany { return $this->hasMany(ApiKey::class); }
    public function providerCredentials(): HasMany { return $this->hasMany(ProviderCredential::class); }

    public function invoices(): HasMany { return $this->hasMany(BillingInvoice::class); }
    public function floatEntries(): HasMany { return $this->hasMany(FloatEntry::class); }

    public function isActive(): bool { return $this->status === 'active'; }

    /** Prepaid float balance in KES. */
    public function floatBalance(): float
    {
        return round((float) $this->floatEntries()->sum('amount'), 2);
    }

    protected function casts(): array
    {
        return ['account_number' => 'integer', 'low_float_notified_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // account_number comes from a DB sequence; load it straight after insert.
        static::created(fn (self $m) => $m->account_number ??= static::query()->whereKey($m->id)->value('account_number'));
    }
}
