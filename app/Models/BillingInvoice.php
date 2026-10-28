<?php

namespace App\Models;

use App\Support\Billing;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingInvoice extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'processed_volume' => 'decimal:2',
            'fee_percent' => 'decimal:3',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }
    public function floatEntries(): HasMany { return $this->hasMany(FloatEntry::class); }

    public function outstanding(): float
    {
        return max(0, round((float) $this->amount - (float) $this->amount_paid, 2));
    }

    /** M-Pesa account reference for paying this invoice, e.g. "234#INV001". */
    public function paymentReference(): string
    {
        return $this->merchant->account_number.'#'.$this->number;
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_at->isPast();
    }

    public function displayStatus(): string
    {
        return match (true) {
            $this->status === 'paid' => 'Paid',
            $this->status === 'void' => 'Void',
            $this->isOverdue() => 'Overdue',
            (float) $this->amount_paid > 0 => 'Part paid',
            default => 'Unpaid',
        };
    }

    public static function statusColor(string $display): string
    {
        return match ($display) { 'Paid' => 'success', 'Overdue' => 'danger', 'Part paid', 'Unpaid' => 'warning', default => 'gray' };
    }

    public function periodLabel(): string
    {
        return $this->period_start->format('F Y');
    }

    public function payBill(): string
    {
        return Billing::payBill();
    }
}
