<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberLedgerEntry extends Model
{
    use HasFactory;

    public const ENTRY_TYPE_DEBIT = 'debit';
    public const ENTRY_TYPE_CREDIT = 'credit';

    public const SOURCE_MONTHLY_DUES = 'monthly_dues';
    public const SOURCE_MEMBERSHIP_FEE = 'membership_fee';
    public const SOURCE_FELLOWSHIP = 'fellowship';
    public const SOURCE_PROJECT_SHARE = 'project_share';
    public const SOURCE_FINE = 'fine';
    public const SOURCE_SPECIAL_COLLECTION = 'special_collection';
    public const SOURCE_PAYMENT = 'payment';
    public const SOURCE_ADJUSTMENT = 'adjustment';

    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'club_id',
        'member_id',
        'created_by_user_id',
        'entry_type',
        'source_type',
        'amount',
        'accounting_month',
        'due_date',
        'transaction_date',
        'reference_number',
        'description',
        'metadata',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'transaction_date' => 'date',
            'metadata' => 'array',
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

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}