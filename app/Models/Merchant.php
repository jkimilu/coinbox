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

    public function isActive(): bool { return $this->status === 'active'; }
}
