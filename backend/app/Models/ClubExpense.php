<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

class ClubExpense extends Model
{
    public const STATUS_POSTED = 'posted';

    protected $fillable = ['public_id', 'club_id', 'accounting_date', 'expense_account_id', 'payment_account_id', 'fund_code', 'amount_paise', 'currency', 'payee', 'description', 'external_reference', 'idempotency_key', 'request_digest', 'journal_entry_id', 'created_by_user_id', 'corrected_from_expense_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $expense) => $expense->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $expense): void {
            if ($expense->getOriginal('journal_entry_id') !== null) {
                throw new LogicException('Posted expenses are immutable.');
            }
        });
        static::deleting(fn (self $expense) => throw new LogicException('Expenses cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['accounting_date' => 'date:Y-m-d', 'amount_paise' => 'integer'];
    }

    public function expenseAccount(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'expense_account_id'); }
    public function paymentAccount(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'payment_account_id'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(ClubJournalEntry::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function reversal(): HasOne { return $this->hasOne(ClubExpenseReversal::class, 'club_expense_id'); }
    public function correctedFrom(): BelongsTo { return $this->belongsTo(self::class, 'corrected_from_expense_id'); }
    public function correctedReplacement(): HasOne { return $this->hasOne(self::class, 'corrected_from_expense_id'); }
}
