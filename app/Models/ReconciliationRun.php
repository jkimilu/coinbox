<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationRun extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'completed_at' => 'datetime',
            'discrepancies' => 'array',
        ];
    }

    public function paymentAccount(): BelongsTo { return $this->belongsTo(PaymentAccount::class); }
}
