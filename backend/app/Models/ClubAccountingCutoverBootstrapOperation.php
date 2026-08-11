<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverBootstrapOperation extends Model
{
    protected $fillable = ['public_id', 'club_id', 'operation_reference', 'package_version', 'lions_year', 'cutover_date', 'chart_version', 'preparer_user_id', 'permission_id', 'request_digest', 'status', 'cutover_package_id', 'permission_assignment_id', 'requested_by_user_id', 'requested_at', 'applied_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $model): void {
            if (array_diff(array_keys($model->getDirty()), ['status', 'cutover_package_id', 'permission_assignment_id', 'applied_at', 'updated_at'])) {
                throw new LogicException('Cutover bootstrap operation binding is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Cutover bootstrap operations cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['cutover_date' => 'date:Y-m-d', 'requested_at' => 'datetime', 'applied_at' => 'datetime'];
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverBootstrapApproval::class, 'bootstrap_operation_id');
    }
}
