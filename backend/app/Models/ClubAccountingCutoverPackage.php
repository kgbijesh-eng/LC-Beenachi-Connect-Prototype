<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClubAccountingCutoverPackage extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const CURRENCY_INR = 'INR';

    public const CHART_VERSION = 'CLUB-COA-2026-01';

    public const LIONS_YEAR = '2027-28';

    public const CUTOVER_DATE = '2027-07-01';

    public const CLASSIFICATION_OPENING_EQUITY = 'OPENING-BALANCE-EQUITY';

    public const CLASSIFICATION_ACCUMULATED_SURPLUS = 'ACCUMULATED-SURPLUS';

    protected $fillable = [
        'public_id', 'club_id', 'lions_year', 'cutover_date', 'chart_version', 'package_version',
        'status', 'currency', 'prepared_by_user_id', 'prepared_at', 'last_validated_at',
        'canonical_checksum', 'reconciliation_difference_paise', 'dr033_classification',
        'activation_ready', 'readiness_checked_at', 'invalidation_reason', 'lock_version',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $package) => $package->public_id ??= (string) Str::orderedUuid());
        static::deleting(function (self $package): void {
            if ($package->status !== self::STATUS_DRAFT) {
                throw new LogicException('Relied-upon cutover packages cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'cutover_date' => 'date:Y-m-d', 'package_version' => 'integer', 'prepared_at' => 'datetime',
            'last_validated_at' => 'datetime', 'reconciliation_difference_paise' => 'integer',
            'activation_ready' => 'boolean', 'readiness_checked_at' => 'datetime', 'lock_version' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function scheduleRows(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverScheduleRow::class, 'cutover_package_id');
    }

    public function evidenceReferences(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverEvidenceReference::class, 'cutover_package_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverReview::class, 'cutover_package_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverApproval::class, 'cutover_package_id');
    }
}
