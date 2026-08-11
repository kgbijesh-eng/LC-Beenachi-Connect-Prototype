<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use LogicException;

class ClubOrdinaryIncomeReceiptCorrection extends Model
{
    protected $fillable = ['public_id', 'club_id', 'original_receipt_id', 'original_journal_entry_id', 'reversal_journal_entry_id', 'replacement_receipt_id', 'accounting_date', 'reason', 'idempotency_key', 'request_digest', 'created_by_user_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $correction) => $correction->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Ordinary income receipt corrections are immutable.'));
        static::deleting(fn () => throw new LogicException('Ordinary income receipt corrections cannot be deleted.'));
    }

    protected function casts(): array { return ['accounting_date' => 'date:Y-m-d']; }
    public function bindReplacementDuringConstruction(ClubOrdinaryIncomeReceipt $replacement): void
    {
        if (! $this->exists || ! $this->wasRecentlyCreated || $this->replacement_receipt_id !== null || DB::transactionLevel() < 1 || $replacement->club_id !== $this->club_id || $replacement->corrected_from_receipt_id !== $this->original_receipt_id) throw new LogicException('A correction replacement may be bound only once during its creating transaction.');
        $this->updateQuietly(['replacement_receipt_id' => $replacement->id]);
    }
    public function originalReceipt(): BelongsTo { return $this->belongsTo(ClubOrdinaryIncomeReceipt::class, 'original_receipt_id'); }
    public function originalJournal(): BelongsTo { return $this->belongsTo(ClubJournalEntry::class, 'original_journal_entry_id'); }
    public function reversalJournal(): BelongsTo { return $this->belongsTo(ClubJournalEntry::class, 'reversal_journal_entry_id'); }
    public function replacementReceipt(): BelongsTo { return $this->belongsTo(ClubOrdinaryIncomeReceipt::class, 'replacement_receipt_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
