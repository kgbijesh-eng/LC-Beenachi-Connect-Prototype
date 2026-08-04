<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingSourceMappingEvent extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Accounting source mapping events are immutable.'));
        static::deleting(fn () => throw new LogicException('Accounting source mapping events cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }
}
