<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverBootstrapApproval extends Model
{
    protected $fillable = ['public_id', 'club_id', 'bootstrap_operation_id', 'approval_role', 'approved_by_user_id', 'approval_reference', 'operation_digest', 'approved_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Cutover bootstrap approvals are immutable.'));
        static::deleting(fn () => throw new LogicException('Cutover bootstrap approvals cannot be deleted.'));
    }
}
