<?php

namespace App\Models;

use App\Payments\Contracts\PaymentProviderAdapter;
use App\Payments\Unintegrated\UnintegratedAdapter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentProvider extends Model
{
    public const MPESA = 'mpesa';

    protected $fillable = ['code', 'name', 'kind', 'adapter', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public static function byCode(string $code): self
    {
        return static::query()->where('code', $code)->firstOrFail();
    }

    public function adapter(): PaymentProviderAdapter
    {
        $class = $this->adapter;

        return $class && class_exists($class) && is_subclass_of($class, PaymentProviderAdapter::class)
            ? app($class)
            : app(UnintegratedAdapter::class);
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class, 'provider_id');
    }
}
