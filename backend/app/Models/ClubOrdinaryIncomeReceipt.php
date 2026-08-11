<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class ClubOrdinaryIncomeReceipt extends Model
{
    protected $fillable = ['public_id', 'club_id', 'subtype', 'accounting_date', 'receiving_account_id', 'income_account_id', 'fund_code', 'amount_paise', 'currency', 'description', 'payer_name', 'external_reference', 'event_label', 'idempotency_key', 'request_digest', 'journal_entry_id', 'created_by_user_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $receipt) => $receipt->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $receipt): void {
            if ($receipt->getOriginal('journal_entry_id') !== null) throw new LogicException('Posted ordinary income receipts are immutable.');
        });
        static::deleting(fn () => throw new LogicException('Ordinary income receipts cannot be deleted.'));
    }

    protected function casts(): array { return ['accounting_date' => 'date:Y-m-d', 'amount_paise' => 'integer']; }
    public function club(): BelongsTo { return $this->belongsTo(Club::class); }
    public function receivingAccount(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'receiving_account_id'); }
    public function incomeAccount(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'income_account_id'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(ClubJournalEntry::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
