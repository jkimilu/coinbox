<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentAccount extends Model
{
    use HasUlids;

    protected $fillable = [
        'merchant_id', 'provider_id', 'account_type', 'display_name', 'external_account_reference',
        'currency', 'credentials_id', 'callback_endpoint_id', 'configuration', 'status',
        'urls_registered_at', 'last_registration_response',
    ];

    protected $hidden = ['route_key', 'last_registration_response'];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'urls_registered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $account) {
            $account->route_key ??= Str::lower(Str::random(40));
            $account->currency ??= 'KES';
        });

        // A new shortcode needs its own URL registration with Safaricom.
        static::updating(function (self $account) {
            if ($account->isDirty('external_account_reference')) {
                AuditLog::record('payment_account.shortcode_changed', $account, [
                    'from' => $account->getOriginal('external_account_reference'),
                    'to' => $account->external_account_reference,
                ], $account->merchant_id);

                $account->urls_registered_at = null;
                $account->configuration = array_merge($account->configuration ?? [], ['registered' => null]);
                $account->last_registration_response = null;
                if ($account->status !== 'disabled') {
                    $account->status = 'pending';
                }
            }
        });
    }

    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }
    public function provider(): BelongsTo { return $this->belongsTo(PaymentProvider::class); }
    public function credentials(): BelongsTo { return $this->belongsTo(ProviderCredential::class, 'credentials_id'); }
    public function callbackEndpoint(): BelongsTo { return $this->belongsTo(CallbackEndpoint::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }
    public function routes(): HasMany { return $this->hasMany(AccountRoute::class)->orderBy('code'); }

    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->configuration, $key, $default);
    }

    /** URL Safaricom posts the C2B confirmation to. */
    public function confirmationUrl(): string
    {
        return self::publicUrl(route('provider.c2b.confirmation', $this->route_key, false));
    }

    /** URL Safaricom posts the C2B validation request to (only used if validation is enabled on the shortcode). */
    public function validationUrl(): string
    {
        return self::publicUrl(route('provider.c2b.validation', $this->route_key, false));
    }

    /**
     * Callback URLs always use MPESA_CALLBACK_BASE_URL (falling back to APP_URL),
     * never whatever host the admin happens to be browsing on (such as localhost).
     */
    protected static function publicUrl(string $path): string
    {
        return rtrim(trim((string) config('coinbox.mpesa.callback_base_url')), '/').$path;
    }
}
