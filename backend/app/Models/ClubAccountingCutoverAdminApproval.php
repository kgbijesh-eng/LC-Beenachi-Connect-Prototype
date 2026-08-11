<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverAdminApproval extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'admin_operation_id', 'approval_role', 'approved_by_user_id', 'approval_reference', 'operation_digest', 'approved_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Cutover administration approvals are immutable.'));
        static::deleting(fn () => throw new LogicException('Cutover administration approvals cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }
}
