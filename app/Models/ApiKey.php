<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasUlids;

    public const ABILITIES = [
        'accounts:read' => 'Read payment accounts and collection summaries',
        'transactions:read' => 'Read transactions',
        'reports:read' => 'Read collection reports',
    ];

    protected $fillable = ['merchant_id', 'name', 'abilities', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }

    /**
     * Create a key and return [model, plainTextToken]. The token is shown once and only its hash is stored.
     * Token format: cbx_{prefix}_{secret}
     */
    public static function issue(Merchant $merchant, string $name, ?array $abilities = null, $expiresAt = null): array
    {
        $prefix = Str::lower(Str::random(12));
        $secret = Str::random(40);

        $key = new static(['merchant_id' => $merchant->id, 'name' => $name, 'abilities' => $abilities, 'expires_at' => $expiresAt]);
        $key->prefix = $prefix;
        $key->token_hash = hash('sha256', $secret);
        $key->save();

        return [$key, "cbx_{$prefix}_{$secret}"];
    }

    public static function findByToken(string $token): ?self
    {
        if (! preg_match('/^cbx_([a-z0-9]{12})_([A-Za-z0-9]{40})$/', $token, $m)) {
            return null;
        }

        $key = static::query()->where('prefix', $m[1])->first();

        if (! $key || ! hash_equals($key->token_hash, hash('sha256', $m[2]))) {
            return null;
        }

        return $key;
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function allows(string $ability): bool
    {
        return empty($this->abilities) || in_array($ability, $this->abilities, true);
    }
}
