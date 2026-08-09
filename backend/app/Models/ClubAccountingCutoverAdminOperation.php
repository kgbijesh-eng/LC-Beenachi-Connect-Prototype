<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverAdminOperation extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'operation_reference', 'package_version', 'package_lock_version', 'action', 'subject_user_id', 'target_type', 'target_key', 'target_record_id', 'effective_at', 'expires_at', 'request_digest', 'status', 'requested_by_user_id', 'requested_at', 'applied_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $m): void {
            if (array_diff(array_keys($m->getDirty()), ['status', 'target_record_id', 'applied_at', 'updated_at'])) {
                throw new LogicException('Cutover administration operation binding is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Cutover administration operations cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['effective_at' => 'datetime', 'expires_at' => 'datetime', 'requested_at' => 'datetime', 'applied_at' => 'datetime'];
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverAdminApproval::class, 'admin_operation_id');
    }
}
