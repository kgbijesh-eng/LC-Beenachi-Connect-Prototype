<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClubAccountingCutoverApproval extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'approval_authority', 'approval_date', 'approval_reference', 'package_version', 'chart_version', 'package_checksum', 'status', 'recorded_by_user_id', 'permission_assignment_id', 'authority_basis', 'recorded_at', 'invalidated_at', 'invalidation_reason'];

    protected static function booted(): void
    {
        static::creating(fn (self $approval) => $approval->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $approval): void {
            if ($approval->authority_basis !== null) {
                throw new \LogicException('Trusted President approvals are immutable.');
            }
        });
        static::deleting(function (self $approval): void {
            if ($approval->authority_basis !== null) {
                throw new \LogicException('Trusted President approvals cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return ['approval_date' => 'date:Y-m-d', 'recorded_at' => 'datetime', 'invalidated_at' => 'datetime', 'package_version' => 'integer'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ClubAccountingCutoverPackage::class, 'cutover_package_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverApprovalEvent::class, 'cutover_approval_id');
    }
}
