<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClubAccountingCutoverReview extends Model
{
    protected $fillable = ['public_id', 'club_id', 'cutover_package_id', 'reviewer_identifier', 'review_date', 'selected_classification', 'written_rationale', 'package_version', 'chart_version', 'package_checksum', 'status', 'recorded_by_user_id', 'recorded_at', 'invalidated_at', 'invalidation_reason'];

    protected static function booted(): void
    {
        static::creating(fn (self $review) => $review->public_id ??= (string) Str::orderedUuid());
    }

    protected function casts(): array
    {
        return ['review_date' => 'date:Y-m-d', 'recorded_at' => 'datetime', 'invalidated_at' => 'datetime', 'package_version' => 'integer'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ClubAccountingCutoverPackage::class, 'cutover_package_id');
    }
}
