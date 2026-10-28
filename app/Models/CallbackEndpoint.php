<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CallbackEndpoint extends Model
{
    use HasUlids;

    public const EVENTS = [
        'transaction.succeeded' => 'Payment received',
        'transaction.reversed' => 'Payment reversed',
    ];

    protected $fillable = ['merchant_id', 'url', 'secret', 'events', 'is_active'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $e) => $e->secret ??= 'whsec_'.Str::random(40));
    }

    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }
    public function deliveries(): HasMany { return $this->hasMany(CallbackDelivery::class); }

    public function wants(string $event): bool
    {
        return $this->is_active && (empty($this->events) || in_array($event, $this->events, true));
    }
}
