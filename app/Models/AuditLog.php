<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function subject(): MorphTo { return $this->morphTo(); }

    public static function record(string $action, ?Model $subject = null, array $data = [], ?string $merchantId = null): self
    {
        $user = auth()->user();

        return static::create([
            'merchant_id' => $merchantId ?? $user?->merchant_id ?? $subject?->merchant_id ?? null,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'data' => $data ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
