<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_paise' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Finalized payment allocations are immutable.'));
        static::deleting(fn () => throw new \LogicException('Finalized payment allocations are immutable.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(MemberPayment::class, 'member_payment_id');
    }

    public function memberCharge(): BelongsTo
    {
        return $this->belongsTo(MemberCharge::class);
    }
}
