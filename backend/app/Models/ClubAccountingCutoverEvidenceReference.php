<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClubAccountingCutoverEvidenceReference extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'evidence_type', 'opaque_reference', 'evidence_date', 'evidence_version', 'digest_sha256', 'verification_status', 'reviewer_note'];

    protected static function booted(): void
    {
        static::creating(fn (self $evidence) => $evidence->public_id ??= (string) Str::orderedUuid());
    }

    protected function casts(): array
    {
        return ['evidence_date' => 'date:Y-m-d'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ClubAccountingCutoverPackage::class, 'cutover_package_id');
    }
}
