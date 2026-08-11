<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

class MemberPayment extends Model
{
    public const MODES = ['cash', 'upi', 'bank_transfer', 'cheque', 'other'];

    public const STATUSES = ['recorded', 'voided'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'payment_date' => 'date', 'voided_at' => 'datetime', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $m): void {
            if ($m->isDirty('public_id')) {
                throw new LogicException('Payment public identity is immutable.');
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replacement_payment_id');
    }
}
