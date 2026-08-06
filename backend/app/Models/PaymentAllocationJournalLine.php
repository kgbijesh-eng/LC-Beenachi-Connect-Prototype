<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentAllocationJournalLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_paise' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Allocation journal-line links are immutable.'));
        static::deleting(fn () => throw new LogicException('Allocation journal-line links are immutable.'));
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class, 'payment_allocation_id');
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(ClubJournalLine::class);
    }
}
