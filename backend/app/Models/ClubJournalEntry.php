<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubJournalEntry extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_VOIDED = 'voided';

    public const CURRENCY_INR = 'INR';

    protected $fillable = [
        'public_id', 'club_id', 'accounting_period_id', 'sequence_number', 'reference',
        'effective_date', 'posting_date', 'posted_at', 'status', 'description', 'currency',
        'source_type', 'source_id', 'source_event', 'idempotency_key', 'reversal_of_entry_id',
        'created_by_user_id', 'posted_by_user_id', 'reversed_by_user_id', 'voided_at',
        'voided_by_user_id', 'metadata',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $entry) => $entry->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $entry): void {
            if ($entry->getOriginal('status') === self::STATUS_POSTED && $entry->isApprovedReversalTransition()) {
                return;
            }
            if (in_array($entry->getOriginal('status'), [self::STATUS_POSTED, self::STATUS_REVERSED], true)) {
                throw new LogicException('Posted journal entries are immutable.');
            }
        });
        static::deleting(function (self $entry): void {
            if (in_array($entry->status, [self::STATUS_POSTED, self::STATUS_REVERSED], true)) {
                throw new LogicException('Posted journal entries cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer', 'effective_date' => 'date:Y-m-d',
            'posting_date' => 'date:Y-m-d', 'posted_at' => 'datetime',
            'voided_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    public function scopeForClub(Builder $query, int $clubId): Builder
    {
        return $query->where('club_id', $clubId);
    }

    public function scopeInPeriod(Builder $query, int $periodId): Builder
    {
        return $query->where('accounting_period_id', $periodId);
    }

    public function scopeStatementOrder(Builder $query): Builder
    {
        return $query->orderBy('posting_date')->orderBy('effective_date')->orderBy('sequence_number')->orderBy('id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(ClubAccountingPeriod::class, 'accounting_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ClubJournalLine::class, 'journal_entry_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_entry_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    private function isApprovedReversalTransition(): bool
    {
        if ($this->status !== self::STATUS_REVERSED || ! $this->reversed_by_user_id) {
            return false;
        }
        if (array_diff(array_keys($this->getDirty()), ['status', 'reversed_by_user_id', 'updated_at']) !== []) {
            return false;
        }

        return $this->reversals()
            ->where('status', self::STATUS_POSTED)
            ->count() === 1;
    }
}
