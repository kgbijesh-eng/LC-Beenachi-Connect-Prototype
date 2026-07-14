<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LedgerAccount extends Model
{
    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const NORMAL_DEBIT = 'debit';

    public const NORMAL_CREDIT = 'credit';

    public const CURRENCY_INR = 'INR';

    protected $fillable = [
        'public_id', 'club_id', 'code', 'name', 'account_type', 'normal_balance',
        'currency', 'is_active', 'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $account) => $account->public_id ??= (string) Str::orderedUuid());
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeForClub(Builder $query, int $clubId): Builder
    {
        return $query->where('club_id', $clubId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(ClubJournalLine::class);
    }
}
