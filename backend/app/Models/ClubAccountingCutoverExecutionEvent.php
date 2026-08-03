<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverExecutionEvent extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'event_type', 'event_reference', 'attempted_execution_reference', 'execution_success_id', 'result', 'reason_code', 'actor_user_id', 'recorded_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Execution events are immutable.'));
        static::deleting(fn () => throw new LogicException('Execution events cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }
}
