<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasUlids;

    public const STATUSES = ['open' => 'Open', 'awaiting_customer' => 'Awaiting customer', 'resolved' => 'Resolved', 'closed' => 'Closed'];
    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    public const CATEGORIES = [
        'payments' => 'Payments & transactions', 'integration' => 'Webhooks & API', 'accounts' => 'M-Pesa accounts / Daraja',
        'billing' => 'Billing & float', 'account' => 'My CoinBox account', 'sales' => 'Sales & pricing', 'other' => 'Something else',
    ];

    protected $guarded = ['id', 'number'];

    protected function casts(): array
    {
        return [
            'last_customer_reply_at' => 'datetime',
            'last_staff_reply_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // number comes from a DB sequence
        static::created(fn (self $t) => $t->number ??= static::query()->whereKey($t->id)->value('number'));
    }

    public function messages(): HasMany { return $this->hasMany(TicketMessage::class)->orderBy('created_at'); }
    public function merchant(): BelongsTo { return $this->belongsTo(Merchant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }

    public function reference(): string { return '#'.$this->number; }

    public function mailboxAddress(): string
    {
        return config('coinbox.contact.'.$this->mailbox, config('coinbox.contact.support'));
    }

    public function isOpen(): bool { return in_array($this->status, ['open', 'awaiting_customer'], true); }

    /** Waiting on us: open and the customer spoke last (or nobody replied yet). */
    public function needsReply(): bool
    {
        return $this->status === 'open' && (! $this->last_staff_reply_at || ($this->last_customer_reply_at && $this->last_customer_reply_at->gt($this->last_staff_reply_at)));
    }

    public static function statusColor(?string $s): string
    {
        return match ($s) { 'open' => 'warning', 'awaiting_customer' => 'info', 'resolved' => 'success', default => 'gray' };
    }

    public static function priorityColor(?string $p): string
    {
        return match ($p) { 'urgent' => 'danger', 'high' => 'warning', 'low' => 'gray', default => 'primary' };
    }
}
