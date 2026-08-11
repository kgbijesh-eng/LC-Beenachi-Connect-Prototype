<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingPeriod extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'public_id', 'club_id', 'lions_year', 'start_date', 'end_date', 'status',
        'closed_at', 'closed_by_user_id', 'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $period) => $period->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $period): void {
            if ($period->getOriginal('status') !== self::STATUS_OPEN || $period->status !== self::STATUS_CLOSED
                || ! $period->closed_at || ! $period->closed_by_user_id
                || array_diff(array_keys($period->getDirty()), ['status', 'closed_at', 'closed_by_user_id', 'updated_at']) !== []) {
                throw new LogicException('Accounting periods may only be closed through the controlled lifecycle.');
            }
        });
    }

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'closed_at' => 'datetime'];
    }

    public function scopeForClub(Builder $query, int $clubId): Builder
    {
        return $query->where('club_id', $clubId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(ClubJournalEntry::class, 'accounting_period_id');
    }
}
