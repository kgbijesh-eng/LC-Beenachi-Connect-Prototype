<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverReviewEvent extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'cutover_review_id', 'event_type', 'event_reference', 'reason', 'recorded_by_user_id', 'recorded_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $event) => $event->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Accounting review events are immutable.'));
        static::deleting(fn () => throw new LogicException('Accounting review events cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }
}
