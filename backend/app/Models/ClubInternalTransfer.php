<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class ClubInternalTransfer extends Model
{
    protected $fillable = ['public_id', 'club_id', 'accounting_date', 'source_account_id', 'destination_account_id', 'fund_code', 'amount_paise', 'currency', 'description', 'external_reference', 'idempotency_key', 'request_digest', 'journal_entry_id', 'created_by_user_id'];
    protected static function booted(): void
    {
        static::creating(fn (self $transfer) => $transfer->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $transfer): void { if ($transfer->getOriginal('journal_entry_id') !== null) throw new LogicException('Posted transfers are immutable.'); });
        static::deleting(fn () => throw new LogicException('Transfers cannot be deleted.'));
    }
    protected function casts(): array { return ['accounting_date' => 'date:Y-m-d', 'amount_paise' => 'integer']; }
    public function sourceAccount(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'source_account_id'); }
    public function destinationAccount(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'destination_account_id'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(ClubJournalEntry::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
