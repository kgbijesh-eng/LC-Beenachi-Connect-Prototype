<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    public const MODES = [
        'cash',
        'bank_transfer',
        'upi',
        'cheque',
        'card',
        'adjustment',
    ];

    public const STATUSES = [
        'draft',
        'posted',
        'cancelled',
        'reversed',
    ];

    protected $fillable = [
        'club_id',
        'member_id',
        'amount',
        'payment_date',
        'payment_mode',
        'reference_number',
        'notes',
        'received_by_user_id',
        'ledger_entry_id',
        'status',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(MemberLedgerEntry::class, 'ledger_entry_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}