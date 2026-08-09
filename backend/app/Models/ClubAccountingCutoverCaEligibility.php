<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverCaEligibility extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'authority_appointment_id', 'external_reference_set', 'external_reference_digest', 'checked_on', 'independence_state', 'expires_at', 'admin_operation_id', 'eligible_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('CA eligibility provenance is immutable.'));
        static::deleting(fn () => throw new LogicException('CA eligibility provenance cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['checked_on' => 'date', 'expires_at' => 'datetime', 'eligible_at' => 'datetime'];
    }
}
