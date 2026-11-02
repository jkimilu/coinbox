<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReversalRequest extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'result_payload' => 'array', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $r) => $r->callback_token ??= Str::lower(Str::random(40)));
    }

    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function isPending(): bool { return $this->status === 'pending'; }

    // Daraja blocks URLs containing "mpesa"/"safaricom", so use a neutral path.
    public function resultUrl(): string { return $this->baseUrl().'/api/rv/'.$this->callback_token.'/result'; }
    public function timeoutUrl(): string { return $this->baseUrl().'/api/rv/'.$this->callback_token.'/timeout'; }

    protected function baseUrl(): string
    {
        return rtrim(trim((string) config('coinbox.mpesa.callback_base_url')), '/');
    }
}
