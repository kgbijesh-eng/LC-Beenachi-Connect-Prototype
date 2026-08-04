<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingSourceIntegrationEvent extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Source integration events are immutable.'));
        static::deleting(fn () => throw new LogicException('Source integration events cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'reversal_effective_date' => 'date:Y-m-d', 'recorded_at' => 'datetime'];
    }
}
