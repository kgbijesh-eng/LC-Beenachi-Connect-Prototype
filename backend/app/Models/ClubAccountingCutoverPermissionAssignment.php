<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverPermissionAssignment extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'user_id', 'permission_id', 'assignment_reference', 'effective_at', 'expires_at', 'assigned_by_user_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Cutover permission assignments are immutable.'));
        static::deleting(fn () => throw new LogicException('Cutover permission assignments cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['effective_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverPermissionEvent::class, 'permission_assignment_id');
    }
}
