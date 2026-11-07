<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\ClubAccountingCutoverApproval;
use App\Models\ClubAccountingCutoverApprovalEvent;
use App\Models\ClubAccountingCutoverEvidenceVerification;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverReview;
use App\Models\ClubAccountingCutoverReviewEvent;
use App\Models\ClubAccountingCutoverScheduleRow;
use App\Models\ClubJournalEntry;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\Exceptions\CutoverPackageException;
use DateTimeImmutable;
use Illuminate\Support\Str;
use JsonException;

final class ClubAccountingCutoverPackageService
{
    public function __construct(
        private readonly ClubCutoverAuthorityResolver $authority,
        private readonly OrganisationTransactionGuard $organisations,
    ) {}

    public const PREVIEW_CONTRACT = 'OPENING-JOURNAL-PREVIEW-2026-01';

    private const REQUIRED_SCHEDULES = [
        'cash_on_hand', 'bank_account', 'member_receivables', 'liability', 'external_restriction',
        'other_asset', 'general_club_fund', 'service_activity_fund', 'fellowship_member_activity_fund',
        'equity_preview',
    ];

    private const POSTING_TYPES = [
        'cash_on_hand', 'bank_account', 'member_receivables', 'liability', 'external_restriction',
        'other_asset', 'equity_preview',
    ];

    private const PRESENTATION_GROUPS = [
        'general_club_fund' => 'GENERAL-CLUB-FUND',
        'service_activity_fund' => 'SERVICE-ACTIVITY-FUND',
        'fellowship_member_activity_fund' => 'FELLOWSHIP-MEMBER-ACTIVITY-FUND',
    ];

    private const REQUIRED_EVIDENCE = ['cash_count', 'bank_reconciliation', 'member_receivables', 'liabilities_restrictions', 'reconciliation'];

    public function createDraft(int $clubId, int $actorUserId, int $packageVersion = 1): ClubAccountingCutoverPackage
    {
        throw new CutoverPackageException('A dual-approved package bootstrap operation is required.');
    }

    public function replaceSchedules(int $clubId, int $packageId, int $actorUserId, int $expectedLockVersion, array $rows): ClubAccountingCutoverPackage
    {
        return $this->mutate($clubId, $packageId, $actorUserId, $expectedLockVersion, 'schedules changed', function (ClubAccountingCutoverPackage $package) use ($rows): void {
            $normalized = $this->normalizeScheduleRows($package->club_id, $rows);
            $package->scheduleRows()->delete();
            foreach ($normalized as $row) {
                $package->scheduleRows()->create(['club_id' => $package->club_id, ...$row]);
            }
        });
    }

    public function replaceEvidenceReferences(int $clubId, int $packageId, int $actorUserId, int $expectedLockVersion, array $references): ClubAccountingCutoverPackage
    {
        $normalized = $this->normalizeEvidence($references);

        return $this->mutate($clubId, $packageId, $actorUserId, $expectedLockVersion, 'evidence references changed', function (ClubAccountingCutoverPackage $package) use ($normalized): void {
            $package->evidenceReferences()->delete();
            foreach ($normalized as $reference) {
                $package->evidenceReferences()->create(['club_id' => $package->club_id, ...$reference]);
            }
        });
    }

    public function validate(ClubAccountingCutoverPackage $package): array
    {
        $package = $this->loadCanonical($package);
        $checks = [];
        $checks['cutover_date'] = $package->cutover_date->format('Y-m-d') === ClubAccountingCutoverPackage::CUTOVER_DATE;
        $checks['lions_year'] = $package->lions_year === ClubAccountingCutoverPackage::LIONS_YEAR;
        $checks['chart_version'] = $package->chart_version === ClubAccountingCutoverPackage::CHART_VERSION;
        $checks['currency'] = $package->currency === ClubAccountingCutoverPackage::CURRENCY_INR;
        $checks['package_version'] = $package->package_version > 0;

        $types = $package->scheduleRows->pluck('schedule_type');
        foreach (self::REQUIRED_SCHEDULES as $type) {
            $checks["schedule:{$type}"] = $types->contains($type);
        }
        $checks['unique_row_keys'] = $package->scheduleRows->pluck('row_key')->unique()->count() === $package->scheduleRows->count();
        $mapped = $package->scheduleRows->whereIn('schedule_type', self::POSTING_TYPES)->whereNotNull('ledger_account_id');
        $checks['unique_account_fund_mappings'] = $mapped->map(fn ($row) => $row->ledger_account_id.'|'.$row->fund_code)->unique()->count() === $mapped->count();
        $checks['posting_funds'] = $package->scheduleRows->whereIn('schedule_type', self::POSTING_TYPES)->every(fn ($row) => FundCode::tryFrom((string) $row->fund_code) !== null);
        $checks['eligible_accounts'] = $package->scheduleRows->every(function (ClubAccountingCutoverScheduleRow $row) use ($package): bool {
            if (! in_array($row->schedule_type, self::POSTING_TYPES, true)) {
                return $row->ledger_account_id === null;
            }
            if ($row->schedule_type === 'equity_preview' && $package->dr033_classification === null) {
                return $row->ledger_account_id === null;
            }
            if ($row->schedule_type === 'equity_preview' && $row->ledger_account_id === null) {
                return LedgerAccount::query()->where('club_id', $package->club_id)->where('code', $package->dr033_classification)
                    ->where('account_type', LedgerAccount::TYPE_EQUITY)->where('currency', $package->currency)->where('is_active', true)->exists();
            }

            if ($row->ledgerAccount === null || $row->ledgerAccount->club_id !== $package->club_id
                || ! $row->ledgerAccount->is_active || $row->ledgerAccount->currency !== $package->currency) {
                return false;
            }
            if ($row->schedule_type === 'equity_preview') {
                return $row->ledgerAccount->account_type === LedgerAccount::TYPE_EQUITY
                    && $row->ledgerAccount->code === $package->dr033_classification;
            }

            return true;
        });
        $checks['directions'] = $package->scheduleRows->every(fn (ClubAccountingCutoverScheduleRow $row): bool => $this->validDirection($row));
        $checks['presentation_groups'] = collect(self::PRESENTATION_GROUPS)->every(function (string $group, string $type) use ($package): bool {
            $rows = $package->scheduleRows->where('schedule_type', $type);

            return $rows->isNotEmpty() && $rows->every(fn ($row): bool => $row->presentation_group === $group && $row->ledger_account_id === null);
        });

        $evidenceTypes = $package->evidenceReferences->filter(function ($evidence) use ($package): bool {
            if ($evidence->verification_status !== 'verified') {
                return false;
            }

            $verification = ClubAccountingCutoverEvidenceVerification::query()
                ->where('club_id', $package->club_id)->where('cutover_package_id', $package->id)
                ->where('package_version', $package->package_version)->where('evidence_reference_id', $evidence->id)
                ->where('evidence_reference_public_id', $evidence->public_id)->latest('id')->first();

            return $verification !== null && $verification->result === 'verified'
                && hash_equals($verification->evidence_binding_digest, ClubCutoverEvidenceVerificationService::bindingDigest($evidence));
        })->pluck('evidence_type');
        foreach (self::REQUIRED_EVIDENCE as $type) {
            $checks["evidence:{$type}"] = $evidenceTypes->contains($type);
        }
        $difference = $this->reconciliationDifference($package);
        $checks['reconciliation_zero'] = $difference === 0;
        $checks['fund_balanced'] = collect($this->preview($package)['fund_totals'])->every(fn ($totals) => $totals['debit_paise'] === $totals['credit_paise']);

        return ['valid' => ! in_array(false, $checks, true), 'checks' => $checks, 'failure_reasons' => array_keys(array_filter($checks, fn (bool $passed): bool => ! $passed)), 'reconciliation_difference_paise' => $difference];
    }

    public function refreshValidation(int $clubId, int $packageId, int $actorUserId, int $expectedLockVersion): ClubAccountingCutoverPackage
    {
        return $this->organisations->run($clubId, function (OrganisationTransactionContext $context) use ($packageId, $actorUserId, $expectedLockVersion): ClubAccountingCutoverPackage {
            $package = $this->lockPackage($context->organisationId, $packageId, $expectedLockVersion);
            $actor = User::query()->find($actorUserId);
            if (! $actor) {
                throw new CutoverPackageException('The validation request violates club scope.');
            }
            try {
                $this->authority->permission($package, $actor, 'finance.cutover.package.validate');
            } catch (\Throwable $e) {
                throw new CutoverPackageException('Persisted validation requires the exact package validation permission.', previous: $e);
            }
            $validation = $this->validate($package);
            $checksum = $this->checksum($package);
            $package->update([
                'last_validated_at' => now(), 'canonical_checksum' => $checksum,
                'reconciliation_difference_paise' => $validation['reconciliation_difference_paise'],
                'activation_ready' => false, 'readiness_checked_at' => null,
                'updated_by_user_id' => $actorUserId, 'lock_version' => $package->lock_version + 1,
            ]);
            $this->audit($package, $actorUserId, 'finance.cutover_package.validated', ['valid' => $validation['valid'], 'checksum' => $checksum]);

            return $this->loadCanonical($package->fresh());
        }, attempts: 1);
    }

    public function checksum(ClubAccountingCutoverPackage $package): string
    {
        $package = $this->loadCanonical($package);
        $content = [
            'club_id' => $package->club_id,
            'cutover_date' => $package->cutover_date->format('Y-m-d'),
            'lions_year' => $package->lions_year,
            'chart_version' => $package->chart_version,
            'package_version' => $package->package_version,
            'currency' => $package->currency,
            'dr033_classification' => $package->dr033_classification,
            'schedule_rows' => $package->scheduleRows->map(fn ($row): array => [
                'schedule_type' => $row->schedule_type, 'row_key' => $row->row_key,
                'label' => $row->non_sensitive_label, 'account_code' => $row->schedule_type === 'equity_preview' && $row->ledgerAccount === null ? $package->dr033_classification : $row->ledgerAccount?->code,
                'presentation_group' => $row->presentation_group, 'fund_code' => $row->fund_code, 'direction' => $row->direction,
                'amount_paise' => $row->amount_paise,
            ])->sortBy(fn (array $row): string => $row['schedule_type'].'|'.$row['row_key'])->values()->all(),
            'evidence_references' => $package->evidenceReferences->map(fn ($evidence): array => [
                'evidence_type' => $evidence->evidence_type, 'opaque_reference' => $evidence->opaque_reference,
                'evidence_date' => $evidence->evidence_date->format('Y-m-d'), 'evidence_version' => $evidence->evidence_version,
                'digest_sha256' => $evidence->digest_sha256, 'verification_status' => $evidence->verification_status,
            ])->sortBy(fn (array $row): string => $row['evidence_type'].'|'.$row['opaque_reference'])->values()->all(),
        ];

        try {
            return hash('sha256', json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            throw new CutoverPackageException('The cutover package checksum cannot be calculated.', previous: $exception);
        }
    }

    public function recordAccountingReview(int $clubId, int $packageId, int $actorUserId, int $expectedLockVersion, string $reviewerIdentifier, string $reviewDate, string $classification, string $rationale): ClubAccountingCutoverReview
    {
        $classification = strtoupper(trim($classification));
        if (! preg_match('/\A[A-Z0-9][A-Z0-9._:-]{2,39}\z/', $classification)
            || trim($reviewerIdentifier) === '' || $this->containsSensitiveValue($reviewerIdentifier)
            || strlen(trim($rationale)) < 20 || $this->containsSensitiveValue($rationale) || ! $this->validDate($reviewDate)) {
            throw new CutoverPackageException('The accounting review is invalid.');
        }
        $package = ClubAccountingCutoverPackage::query()->whereKey($packageId)->where('club_id', $clubId)->first();
        $actor = User::query()->find($actorUserId);
        if (! $package || ! $actor) {
            throw new CutoverPackageException('The accounting review request violates club scope.');
        }

        return app(ClubCutoverLifecycleService::class)->review($package, $actor, $expectedLockVersion, $classification, trim($reviewerIdentifier), $reviewDate, trim($rationale));
    }

    public function recordClubApproval(int $clubId, int $packageId, int $actorUserId, int $expectedLockVersion, string $authority, string $approvalDate, string $reference): ClubAccountingCutoverApproval
    {
        if (trim($authority) === '' || $this->containsSensitiveValue($authority) || ! $this->validDate($approvalDate)) {
            throw new CutoverPackageException('The club approval is invalid.');
        }
        $this->assertOpaque($reference, 'approval reference');

        if (trim($authority) !== 'President') {
            throw new CutoverPackageException('Only the authenticated President may approve the package.');
        }
        $package = ClubAccountingCutoverPackage::query()->whereKey($packageId)->where('club_id', $clubId)->first();
        $actor = User::query()->find($actorUserId);
        if (! $package || ! $actor) {
            throw new CutoverPackageException('The President approval request violates club scope.');
        }

        return app(ClubCutoverLifecycleService::class)->approve($package, $actor, $expectedLockVersion, $approvalDate, strtoupper(trim($reference)));
    }

    public function preview(ClubAccountingCutoverPackage $package): array
    {
        $package = $this->loadCanonical($package);
        $lines = $package->scheduleRows->whereIn('schedule_type', self::POSTING_TYPES)->filter(fn ($row): bool => $row->amount_paise > 0)->map(function ($row) use ($package): array {
            $unresolved = $row->schedule_type === 'equity_preview' && $package->dr033_classification === null;

            return [
                'schedule_row_id' => $row->id, 'fund_code' => $row->fund_code,
                'row_key' => $row->row_key, 'account_id' => $row->ledger_account_id,
                'account_code' => $unresolved ? null : ($row->ledgerAccount?->code ?? ($row->schedule_type === 'equity_preview' ? $package->dr033_classification : null)),
                'unresolved_classification' => $unresolved, 'direction' => $row->direction,
                'debit_paise' => $row->direction === 'debit' ? $row->amount_paise : 0,
                'credit_paise' => $row->direction === 'credit' ? $row->amount_paise : 0,
            ];
        })->sortBy(fn (array $line): string => $line['row_key'])->values();
        $debits = $lines->sum('debit_paise');
        $credits = $lines->sum('credit_paise');
        $fundTotals = $lines->groupBy('fund_code')->map(fn ($rows) => ['debit_paise' => (int) $rows->sum('debit_paise'), 'credit_paise' => (int) $rows->sum('credit_paise')])->all();

        return [
            'contract_version' => self::PREVIEW_CONTRACT,
            'status' => $package->dr033_classification === null ? 'BLOCKED_DR033' : ($debits === $credits ? 'BALANCED_PREVIEW' : 'UNBALANCED_PREVIEW'),
            'writes_performed' => false,
            'header' => ['club_id' => $package->club_id, 'effective_date' => $package->cutover_date->format('Y-m-d'), 'lions_year' => $package->lions_year, 'source_type' => 'accounting_opening_package_preview', 'package_version' => $package->package_version, 'package_checksum' => $this->checksum($package), 'dr033_classification' => $package->dr033_classification],
            'lines' => $lines->all(), 'fund_totals' => $fundTotals, 'total_debit_paise' => $debits, 'total_credit_paise' => $credits,
            'balanced' => $debits === $credits && collect($fundTotals)->every(fn ($totals) => $totals['debit_paise'] === $totals['credit_paise']),
        ];
    }

    public function readiness(ClubAccountingCutoverPackage $package): array
    {
        $package = $this->loadCanonical($package);
        $validation = $this->validate($package);
        $checksum = $this->checksum($package);
        $review = $package->reviews->where('status', 'current')->sortByDesc('id')->first();
        $approval = $package->approvals->where('status', 'current')->sortByDesc('id')->first();
        $preview = $this->preview($package);
        $checks = $validation['checks'] + [
            'checksum_current' => $package->canonical_checksum !== null && hash_equals($package->canonical_checksum, $checksum),
            'dr033_selected' => is_string($package->dr033_classification)
                && LedgerAccount::query()->where('club_id', $package->club_id)
                    ->where('code', $package->dr033_classification)
                    ->where('account_type', LedgerAccount::TYPE_EQUITY)
                    ->where('currency', $package->currency)
                    ->where('is_active', true)->exists(),
            'accounting_review_current' => $this->bindingMatches($package, $review, $checksum),
            'club_approval_current' => $this->bindingMatches($package, $approval, $checksum),
            'no_previous_activation' => ! ClubJournalEntry::query()->where('club_id', $package->club_id)->where('source_type', 'accounting_opening_package')->exists(),
            'opening_preview_balanced' => $preview['balanced'] && $preview['status'] === 'BALANCED_PREVIEW',
        ];

        return ['ready' => ! in_array(false, $checks, true), 'checks' => $checks, 'failure_reasons' => array_keys(array_filter($checks, fn (bool $passed): bool => ! $passed)), 'package_checksum' => $checksum, 'preview' => $preview];
    }

    private function mutate(int $clubId, int $packageId, int $actorUserId, int $expectedLockVersion, string $reason, callable $mutation): ClubAccountingCutoverPackage
    {
        return $this->organisations->run($clubId, function (OrganisationTransactionContext $context) use ($packageId, $actorUserId, $expectedLockVersion, $reason, $mutation): ClubAccountingCutoverPackage {
            $package = $this->lockPackage($context->organisationId, $packageId, $expectedLockVersion);
            $actor = User::query()->find($actorUserId);
            if (! $actor) {
                throw new CutoverPackageException('The package authoring request violates club scope.');
            }
            try {
                $this->authority->permission($package, $actor, 'finance.cutover.package.prepare');
            } catch (\Throwable $e) {
                throw new CutoverPackageException('Package authoring requires the exact package preparation permission.', previous: $e);
            }
            if ($package->status !== ClubAccountingCutoverPackage::STATUS_DRAFT) {
                throw new CutoverPackageException('Only draft cutover packages may be edited.');
            }
            $mutation($package);
            $this->invalidateBindings($package, $actorUserId, $reason);
            $package->update([
                'canonical_checksum' => null, 'last_validated_at' => null, 'dr033_classification' => null,
                'activation_ready' => false, 'readiness_checked_at' => null, 'invalidation_reason' => $reason,
                'updated_by_user_id' => $actorUserId, 'lock_version' => $package->lock_version + 1,
            ]);
            $this->audit($package, $actorUserId, 'finance.cutover_package.accounting_content_changed', ['reason' => $reason, 'lock_version' => $package->lock_version]);

            return $this->loadCanonical($package->fresh());
        }, attempts: 1);
    }

    private function normalizeScheduleRows(int $clubId, array $rows): array
    {
        if (! array_is_list($rows) || $rows === []) {
            throw new CutoverPackageException('Cutover schedules are required.');
        }
        $normalized = [];
        $keys = [];
        $accountFunds = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new CutoverPackageException('A cutover schedule row is invalid.');
            }
            $type = strtolower(trim((string) ($row['schedule_type'] ?? '')));
            $key = strtoupper(trim((string) ($row['row_key'] ?? '')));
            $label = trim((string) ($row['non_sensitive_label'] ?? ''));
            $direction = strtolower(trim((string) ($row['direction'] ?? '')));
            $amount = $row['amount_paise'] ?? null;
            $accountId = $row['ledger_account_id'] ?? null;
            $group = isset($row['presentation_group']) ? strtoupper(trim((string) $row['presentation_group'])) : null;
            $fund = isset($row['fund_code']) ? strtoupper(trim((string) $row['fund_code'])) : null;
            if (! in_array($type, self::REQUIRED_SCHEDULES, true) || ! preg_match('/\A[A-Z][A-Z0-9-]{1,79}\z/', $key)
                || $label === '' || $this->containsSensitiveValue($label) || ! in_array($direction, ['debit', 'credit'], true)
                || ! is_int($amount) || $amount < 0 || ($accountId !== null && (! is_int($accountId) || $accountId < 1)) || isset($keys[$key])) {
                throw new CutoverPackageException('A cutover schedule row is invalid.');
            }
            $keys[$key] = true;
            if ($accountId !== null) {
                if (! FundCode::tryFrom((string) $fund) || isset($accountFunds[$accountId.'|'.$fund])) {
                    throw new CutoverPackageException('A posting row requires a unique canonical account and fund mapping.');
                }
                $accountFunds[$accountId.'|'.$fund] = true;
                $account = LedgerAccount::query()->whereKey($accountId)->first();
                if (! $account || $account->club_id !== $clubId || ! $account->is_active) {
                    throw new CutoverPackageException('A cutover account mapping violates club scope or eligibility.');
                }
            }
            if (in_array($type, self::POSTING_TYPES, true) && ! FundCode::tryFrom((string) $fund)) {
                throw new CutoverPackageException('Every opening posting row requires a canonical fund.');
            }
            $normalized[] = ['schedule_type' => $type, 'row_key' => $key, 'non_sensitive_label' => $label, 'ledger_account_id' => $accountId, 'presentation_group' => $group, 'fund_code' => $fund, 'direction' => $direction, 'amount_paise' => $amount];
        }

        return $normalized;
    }

    private function normalizeEvidence(array $references): array
    {
        if (! array_is_list($references)) {
            throw new CutoverPackageException('Evidence references are invalid.');
        }
        $normalized = [];
        $seen = [];
        foreach ($references as $reference) {
            if (! is_array($reference)) {
                throw new CutoverPackageException('An evidence reference is invalid.');
            }
            $type = strtolower(trim((string) ($reference['evidence_type'] ?? '')));
            $opaque = strtoupper(trim((string) ($reference['opaque_reference'] ?? '')));
            $version = strtoupper(trim((string) ($reference['evidence_version'] ?? '')));
            // Submission is never an authoritative verification act (CADR-005).
            $status = 'unverified';
            $date = (string) ($reference['evidence_date'] ?? '');
            $digest = isset($reference['digest_sha256']) ? strtolower(trim((string) $reference['digest_sha256'])) : null;
            $note = isset($reference['reviewer_note']) ? trim((string) $reference['reviewer_note']) : null;
            $this->assertOpaque($opaque, 'evidence reference');
            if (! preg_match('/\A[a-z][a-z0-9_]{2,39}\z/', $type) || ! preg_match('/\A[A-Z0-9][A-Z0-9._:-]{0,39}\z/', $version)
                || ! $this->validDate($date)
                || ($digest !== null && ! preg_match('/\A[a-f0-9]{64}\z/', $digest)) || ($note !== null && ($this->containsSensitiveValue($note) || strlen($note) > 500)) || isset($seen[$opaque])) {
                throw new CutoverPackageException('An evidence reference is invalid.');
            }
            $seen[$opaque] = true;
            $normalized[] = ['evidence_type' => $type, 'opaque_reference' => $opaque, 'evidence_date' => $date, 'evidence_version' => $version, 'digest_sha256' => $digest, 'verification_status' => $status, 'reviewer_note' => $note];
        }

        return $normalized;
    }

    private function lockPackage(int $clubId, int $packageId, int $expectedLockVersion): ClubAccountingCutoverPackage
    {
        $package = ClubAccountingCutoverPackage::query()->whereKey($packageId)->where('club_id', $clubId)->lockForUpdate()->first();
        if (! $package || $package->club_id !== $clubId) {
            throw new CutoverPackageException('The cutover package violates club scope.');
        }
        if ($package->lock_version !== $expectedLockVersion) {
            throw new CutoverPackageException('The cutover package lock version is stale.');
        }

        return $package;
    }

    private function loadCanonical(ClubAccountingCutoverPackage $package): ClubAccountingCutoverPackage
    {
        if (! $package->exists || $package->getKey() === null) {
            throw new CutoverPackageException('The persisted cutover package is unavailable.');
        }

        $persisted = ClubAccountingCutoverPackage::query()->whereKey($package->getKey())->first();
        if (! $persisted) {
            throw new CutoverPackageException('The persisted cutover package is unavailable.');
        }

        return $persisted->load([
            'scheduleRows' => fn ($query) => $query->with('ledgerAccount')->orderBy('schedule_type')->orderBy('row_key'),
            'evidenceReferences' => fn ($query) => $query->orderBy('evidence_type')->orderBy('opaque_reference'),
            'reviews' => fn ($query) => $query->with('events')->orderBy('id'), 'approvals' => fn ($query) => $query->with('events')->orderBy('id'),
        ]);
    }

    private function invalidateBindings(ClubAccountingCutoverPackage $package, int $actorUserId, string $reason): void
    {
        ClubAccountingCutoverReview::query()->where('cutover_package_id', $package->id)->where('status', 'current')->each(function (ClubAccountingCutoverReview $review) use ($package, $actorUserId, $reason): void {
            if (! $review->events()->whereIn('event_type', ['revoked', 'invalidated'])->exists()) {
                ClubAccountingCutoverReviewEvent::create(['club_id' => $package->club_id, 'cutover_package_id' => $package->id, 'cutover_review_id' => $review->id, 'event_type' => 'invalidated', 'event_reference' => (string) Str::orderedUuid(), 'reason' => $reason, 'recorded_by_user_id' => $actorUserId, 'recorded_at' => now()]);
            }
        });
        ClubAccountingCutoverApproval::query()->where('cutover_package_id', $package->id)->where('status', 'current')->each(function (ClubAccountingCutoverApproval $approval) use ($package, $actorUserId, $reason): void {
            if (! $approval->events()->whereIn('event_type', ['revoked', 'invalidated'])->exists()) {
                ClubAccountingCutoverApprovalEvent::create(['club_id' => $package->club_id, 'cutover_package_id' => $package->id, 'cutover_approval_id' => $approval->id, 'event_type' => 'invalidated', 'event_reference' => (string) Str::orderedUuid(), 'reason' => $reason, 'recorded_by_user_id' => $actorUserId, 'recorded_at' => now()]);
            }
        });
    }

    private function reconciliationDifference(ClubAccountingCutoverPackage $package): int
    {
        $debits = 0;
        $credits = 0;
        foreach ($package->scheduleRows->whereIn('schedule_type', self::POSTING_TYPES) as $row) {
            if ($row->direction === 'debit') {
                $debits = $this->checkedAdd($debits, $row->amount_paise);
            } else {
                $credits = $this->checkedAdd($credits, $row->amount_paise);
            }
        }

        return $debits - $credits;
    }

    private function checkedAdd(int $total, int $amount): int
    {
        if ($amount > PHP_INT_MAX - $total) {
            throw new CutoverPackageException('A cutover monetary total exceeds the supported range.');
        }

        return $total + $amount;
    }

    private function validDirection(ClubAccountingCutoverScheduleRow $row): bool
    {
        if (in_array($row->schedule_type, ['cash_on_hand', 'bank_account', 'member_receivables', 'other_asset'], true)) {
            return $row->direction === 'debit';
        }
        if (in_array($row->schedule_type, ['liability', 'external_restriction'], true)) {
            return $row->direction === 'credit';
        }

        return in_array($row->direction, ['debit', 'credit'], true);
    }

    private function bindingMatches(ClubAccountingCutoverPackage $package, mixed $binding, string $checksum): bool
    {
        return $binding !== null && ! $binding->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty()
            && $binding->package_version === $package->package_version
            && $binding->chart_version === $package->chart_version
            && hash_equals($binding->package_checksum, $checksum)
            && (! ($binding instanceof ClubAccountingCutoverReview)
                || $binding->selected_classification === $package->dr033_classification);
    }

    private function assertOpaque(string $value, string $field): void
    {
        if (! preg_match('/\A[A-Z0-9][A-Z0-9._:-]{5,119}\z/', strtoupper(trim($value))) || $this->containsSensitiveValue($value)) {
            throw new CutoverPackageException("The {$field} must be a non-sensitive opaque identifier.");
        }
    }

    private function containsSensitiveValue(string $value): bool
    {
        return (bool) preg_match('~(?:https?://|file:/{2}|[A-Za-z]:[\\\\/]|/(?:home|users|var|private)/|BEGIN [A-Z ]+KEY|password|secret|bearer|auth(?:entication)?[_ -]?token|account\s*(?:number|no\.?|#)?\s*\d{6,})~i', $value);
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function audit(ClubAccountingCutoverPackage $package, int $actorUserId, string $action, array $metadata): void
    {
        if (! User::query()->whereKey($actorUserId)->exists()) {
            throw new CutoverPackageException('The cutover actor is invalid.');
        }
        AuditLog::create(['club_id' => $package->club_id, 'user_id' => $actorUserId, 'action' => $action, 'auditable_type' => ClubAccountingCutoverPackage::class, 'auditable_id' => $package->id, 'metadata' => $metadata]);
    }
}
