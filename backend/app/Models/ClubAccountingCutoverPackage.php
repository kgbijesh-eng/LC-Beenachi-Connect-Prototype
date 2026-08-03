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

    public const STATUS_PREPARED = 'prepared';

    public const STATUS_INTERNALLY_VERIFIED = 'internally_verified';

    public const STATUS_ACCOUNTING_REVIEWED = 'accounting_reviewed';

    public const STATUS_BUSINESS_APPROVED = 'business_approved';

    public const STATUS_DRY_RUN_ACCEPTED = 'dry_run_accepted';

    public const STATUS_ACTIVATION_AUTHORIZED = 'activation_authorized';

    public const STATUS_POSTED = 'posted';

    public const CURRENCY_INR = 'INR';

    public const CHART_VERSION = 'CLUB-COA-2026-01';

    public const LIONS_YEAR = '2027-28';

    public const CUTOVER_DATE = '2027-07-01';

    public const CLASSIFICATION_OPENING_EQUITY = 'OPENING-BALANCE-EQUITY';

    public const CLASSIFICATION_ACCUMULATED_SURPLUS = 'ACCUMULATED-SURPLUS';

    protected $fillable = [
        'public_id', 'club_id', 'lions_year', 'cutover_date', 'chart_version', 'package_version',
        'status', 'currency', 'prepared_by_user_id', 'prepared_at', 'internally_verified_by_user_id', 'internally_verified_at', 'last_validated_at',
        'canonical_checksum', 'reconciliation_difference_paise', 'dr033_classification',
        'activation_ready', 'readiness_checked_at', 'invalidation_reason', 'opening_journal_entry_id',
        'posted_at', 'posted_by_user_id', 'lock_version',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $package) => $package->public_id ??= (string) Str::orderedUuid());
        static::updating(function (self $package): void {
            $from = $package->getOriginal('status');
            $to = $package->status;
            $transitions = [
                self::STATUS_DRAFT => [self::STATUS_DRAFT, self::STATUS_PREPARED],
                self::STATUS_PREPARED => [self::STATUS_PREPARED, self::STATUS_INTERNALLY_VERIFIED],
                self::STATUS_INTERNALLY_VERIFIED => [self::STATUS_INTERNALLY_VERIFIED, self::STATUS_ACCOUNTING_REVIEWED],
                self::STATUS_ACCOUNTING_REVIEWED => [self::STATUS_ACCOUNTING_REVIEWED, self::STATUS_BUSINESS_APPROVED],
                self::STATUS_BUSINESS_APPROVED => [self::STATUS_BUSINESS_APPROVED, self::STATUS_DRY_RUN_ACCEPTED],
                self::STATUS_DRY_RUN_ACCEPTED => [self::STATUS_DRY_RUN_ACCEPTED, self::STATUS_ACTIVATION_AUTHORIZED],
                self::STATUS_ACTIVATION_AUTHORIZED => [self::STATUS_ACTIVATION_AUTHORIZED, self::STATUS_POSTED],
                self::STATUS_POSTED => [self::STATUS_POSTED],
            ];
            if (! in_array($to, $transitions[$from] ?? [$from], true)) {
                throw new LogicException('The cutover package lifecycle transition is invalid.');
            }
            if ($from === self::STATUS_DRAFT && $to === self::STATUS_DRAFT) {
                return;
            }
            $allowed = ['status', 'lock_version', 'activation_ready', 'readiness_checked_at', 'invalidation_reason', 'updated_by_user_id', 'updated_at'];
            if ($from === self::STATUS_DRAFT && $to === self::STATUS_PREPARED) {
                $allowed[] = 'prepared_by_user_id';
                $allowed[] = 'prepared_at';
                $allowed[] = 'canonical_checksum';
            }
            if ($from === self::STATUS_PREPARED && $to === self::STATUS_INTERNALLY_VERIFIED) {
                $allowed[] = 'internally_verified_by_user_id';
                $allowed[] = 'internally_verified_at';
            }
            $dr033Transition = $from === self::STATUS_INTERNALLY_VERIFIED
                && $package->status === self::STATUS_ACCOUNTING_REVIEWED
                && $package->getOriginal('dr033_classification') === null
                && in_array($package->dr033_classification, [self::CLASSIFICATION_OPENING_EQUITY, self::CLASSIFICATION_ACCUMULATED_SURPLUS], true);
            if ($dr033Transition) {
                $allowed[] = 'dr033_classification';
                $allowed[] = 'canonical_checksum';
            }
            if ($from === self::STATUS_ACTIVATION_AUTHORIZED && $to === self::STATUS_POSTED) {
                $allowed = array_merge($allowed, ['opening_journal_entry_id', 'posted_at', 'posted_by_user_id']);
                if ($package->opening_journal_entry_id === null || $package->posted_at === null || $package->posted_by_user_id === null) {
                    throw new LogicException('Posting metadata is required for the posted transition.');
                }
            }
            if (array_diff(array_keys($package->getDirty()), $allowed) !== []) {
                throw new LogicException('Prepared cutover accounting content is immutable.');
            }
        });
        static::deleting(function (self $package): void {
            if ($package->status !== self::STATUS_DRAFT) {
                throw new LogicException('Relied-upon cutover packages cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'cutover_date' => 'date:Y-m-d', 'package_version' => 'integer', 'prepared_at' => 'datetime', 'internally_verified_at' => 'datetime',
            'last_validated_at' => 'datetime', 'reconciliation_difference_paise' => 'integer',
            'activation_ready' => 'boolean', 'readiness_checked_at' => 'datetime', 'opening_journal_entry_id' => 'integer',
            'posted_at' => 'datetime', 'lock_version' => 'integer',
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

    public function authorityAppointments(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverAuthorityAppointment::class, 'cutover_package_id');
    }

    public function permissionAssignments(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverPermissionAssignment::class, 'cutover_package_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ClubAccountingCutoverDecision::class, 'cutover_package_id');
    }

    public function openingJournal(): BelongsTo
    {
        return $this->belongsTo(ClubJournalEntry::class, 'opening_journal_entry_id');
    }
}
