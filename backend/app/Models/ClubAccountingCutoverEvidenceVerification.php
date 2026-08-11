<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverEvidenceVerification extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'evidence_reference_id', 'evidence_reference_public_id', 'evidence_binding_digest', 'package_version', 'package_lock_version', 'result', 'verification_reference', 'safe_note', 'verified_by_user_id', 'verified_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::orderedUuid());
        static::updating(fn () => throw new LogicException('Evidence verification provenance is immutable.'));
        static::deleting(fn () => throw new LogicException('Evidence verification provenance cannot be deleted.'));
    }
}
