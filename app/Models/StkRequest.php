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
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }

    public const PURPOSE_COLLECTION = 'collection';

    /** Merchant API representation of a prompt sent with POST /v1/stk. */
    public function toApiArray(): array
    {
        return [
            'id' => 'stk_'.$this->id,
            'object' => 'stk_request',
            'payment_account_id' => 'acct_'.$this->payment_account_id,
            'short_code' => $this->paymentAccount?->external_account_reference,
            'phone' => $this->maskedPhone(),
            'amount' => $this->amount,
            'account_reference' => $this->account_reference,
            'description' => $this->description,
            'status' => $this->status,
            'result_code' => $this->result_code,
            'result_description' => $this->result_desc,
            'mpesa_receipt' => $this->mpesa_receipt,
            'checkout_request_id' => $this->checkout_request_id,
            'transaction' => $this->transaction?->toApiArray(),
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }

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
