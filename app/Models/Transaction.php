<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Immutable payment record. Financial fields cannot change after creation;
 * reversals are recorded as separate rows of type "reversal".
 */
class Transaction extends Model
{
    use HasUlids;

    public const TYPE_PAYMENT = 'payment';
    public const TYPE_REVERSAL = 'reversal';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';

    /** Fields that may never change once written. */
    protected const IMMUTABLE = [
        'merchant_id', 'payment_account_id', 'account_route_id', 'provider_id', 'type', 'provider_code', 'payment_method',
        'provider_transaction_id', 'merchant_reference', 'amount', 'currency', 'paid_at', 'reverses_transaction_id',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $txn) {
            foreach (self::IMMUTABLE as $field) {
                if ($txn->isDirty($field)) {
                    throw new LogicException("Transaction field [{$field}] is immutable.");
                }
            }
        });
        static::deleting(fn () => throw new LogicException('Transactions cannot be deleted.'));
    }

    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }
    public function paymentAccount(): BelongsTo { return $this->belongsTo(PaymentAccount::class); }
    public function accountRoute(): BelongsTo { return $this->belongsTo(AccountRoute::class); }
    public function provider(): BelongsTo { return $this->belongsTo(PaymentProvider::class); }
    public function providerEvent(): BelongsTo { return $this->belongsTo(ProviderEvent::class); }
    public function reverses(): BelongsTo { return $this->belongsTo(self::class, 'reverses_transaction_id'); }
    public function reversals(): HasMany { return $this->hasMany(self::class, 'reverses_transaction_id'); }

    /** Public API representation (the common transaction format). */
    public function toApiArray(): array
    {
        return [
            'id' => 'txn_'.$this->id,
            'merchant_id' => 'mer_'.$this->merchant_id,
            'payment_account_id' => 'acct_'.$this->payment_account_id,
            'type' => $this->type,
            'provider' => $this->provider_code,
            'payment_method' => $this->payment_method,
            'provider_transaction_id' => $this->provider_transaction_id,
            'account_code' => $this->accountRoute?->code,
            'merchant_reference' => $this->merchant_reference,
            'account_number' => $this->metadata['bill_ref_number'] ?? $this->merchant_reference,
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payer' => [
                'identifier' => $this->payer_identifier,
                'name' => $this->payer_name,
            ],
            'reverses_transaction_id' => $this->reverses_transaction_id ? 'txn_'.$this->reverses_transaction_id : null,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
