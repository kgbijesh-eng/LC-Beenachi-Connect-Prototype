<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class ClubJournalLine extends Model
{
    public const DIRECTION_DEBIT = 'debit';

    public const DIRECTION_CREDIT = 'credit';

    protected $fillable = [
        'public_id', 'club_id', 'journal_entry_id', 'ledger_account_id',
        'line_number', 'direction', 'amount_paise', 'description', 'fund_code',
        'opening_schedule_row_id', 'reversal_of_line_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            $line->public_id ??= (string) Str::orderedUuid();
            $line->guardParentIsEditable();
        });
        static::updating(fn (self $line) => $line->guardParentIsEditable());
        static::deleting(fn (self $line) => $line->guardParentIsEditable());
    }

    protected function casts(): array
    {
        return ['line_number' => 'integer', 'amount_paise' => 'integer'];
    }

    public function scopeForClub(Builder $query, int $clubId): Builder
    {
        return $query->where('club_id', $clubId);
    }

    public function scopeStatementOrder(Builder $query): Builder
    {
        return $query->orderBy('line_number')->orderBy('id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(ClubJournalEntry::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    private function guardParentIsEditable(): void
    {
        $status = ClubJournalEntry::query()->whereKey($this->journal_entry_id)->value('status');
        if (in_array($status, [ClubJournalEntry::STATUS_POSTED, ClubJournalEntry::STATUS_REVERSED], true)) {
            throw new LogicException('Lines belonging to posted journal entries are immutable.');
        }
    }
}
