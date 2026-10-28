<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable prepaid-float ledger line. Balance = sum(amount). */
class FloatEntry extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const TYPES = ['topup' => 'Top-up', 'invoice_charge' => 'Invoice payment', 'adjustment' => 'Adjustment'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Float entries are immutable; add an adjustment instead.'));
        static::deleting(fn () => throw new LogicException('Float entries cannot be deleted.'));
    }

    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id'); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }
}
