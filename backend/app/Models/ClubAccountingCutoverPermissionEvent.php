<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverPermissionEvent extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'permission_assignment_id', 'event_type', 'event_reference', 'reason', 'recorded_by_user_id', 'recorded_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Permission events are immutable.'));
        static::deleting(fn () => throw new LogicException('Permission events cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }
}
