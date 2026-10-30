<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StkRequest extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['callback_payload' => 'array', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $r) => $r->callback_token ??= Str::lower(Str::random(40)));
    }

    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }
    public function paymentAccount(): BelongsTo { return $this->belongsTo(PaymentAccount::class); }

    public function isPending(): bool { return $this->status === 'pending'; }

    public function callbackUrl(): string
    {
        // Daraja blocks URLs containing "mpesa"/"safaricom", so use a neutral path.
        return rtrim(trim((string) config('coinbox.mpesa.callback_base_url')), '/').'/api/stk/'.$this->callback_token;
    }

    /** "2547****4321" */
    public function maskedPhone(): string
    {
        return substr($this->phone, 0, 4).'****'.substr($this->phone, -4);
    }
}
