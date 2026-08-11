<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class ClubExpenseReversal extends Model
{
    protected $fillable = ['public_id', 'club_id', 'club_expense_id', 'reversal_journal_entry_id', 'accounting_date', 'reason', 'idempotency_key', 'request_digest', 'created_by_user_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $reversal) => $reversal->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Expense reversals are immutable.'));
        static::deleting(fn () => throw new LogicException('Expense reversals cannot be deleted.'));
    }

    protected function casts(): array { return ['accounting_date' => 'date:Y-m-d']; }

    public function expense(): BelongsTo { return $this->belongsTo(ClubExpense::class, 'club_expense_id'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(ClubJournalEntry::class, 'reversal_journal_entry_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
