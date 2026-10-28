<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallbackDelivery extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo { return $this->belongsTo(CallbackEndpoint::class, 'callback_endpoint_id'); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }
}
