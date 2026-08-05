<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingIntegrationProposalDecision extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Accounting integration decisions are immutable.'));
        static::deleting(fn () => throw new LogicException('Accounting integration decisions cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'decided_at' => 'datetime'];
    }
}
