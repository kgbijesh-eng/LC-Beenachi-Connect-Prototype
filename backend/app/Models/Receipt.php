<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    public const STATUSES = [
        'issued',
        'cancelled',
    ];

    protected $fillable = [
        'club_id',
        'payment_id',
        'receipt_number',
        'receipt_date',
        'issued_by_user_id',
        'status',
        'notes',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}