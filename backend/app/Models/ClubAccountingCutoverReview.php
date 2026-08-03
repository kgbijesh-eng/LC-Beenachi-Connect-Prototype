<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClubAccountingCutoverReview extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'reviewer_identifier', 'review_date', 'selected_classification', 'written_rationale', 'package_version', 'chart_version', 'package_checksum', 'status', 'recorded_by_user_id', 'authority_appointment_id', 'permission_assignment_id', 'authority_basis', 'recorded_at', 'invalidated_at', 'invalidation_reason'];

    protected static function booted(): void
    {
        static::creating(fn (self $review) => $review->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $review): void {
            if ($review->authority_basis !== null) {
                throw new \LogicException('Trusted accounting reviews are immutable.');
            }
        });
        static::deleting(function (self $review): void {
            if ($review->authority_basis !== null) {
                throw new \LogicException('Trusted accounting reviews cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return ['review_date' => 'date:Y-m-d', 'recorded_at' => 'datetime', 'invalidated_at' => 'datetime', 'package_version' => 'integer'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ClubAccountingCutoverPackage::class, 'cutover_package_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverReviewEvent::class, 'cutover_review_id');
    }
}
