<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingCutoverApproval;
use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverAuthorityConsumption;
use App\Models\ClubAccountingCutoverDecision;
use App\Models\ClubAccountingCutoverExecutionEvent;
use App\Models\ClubAccountingCutoverExecutionSuccess;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverReview;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\Exceptions\CutoverExecutionAuditException;
use App\Services\Finance\Exceptions\CutoverExecutionOutcomeUnknownException;
use App\Services\Finance\Exceptions\CutoverExecutionConflictException;
use App\Services\Finance\Exceptions\CutoverExecutionException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Throwable;

final class ClubOpeningJournalActivationService
{
    private const SOURCE_TYPE = 'accounting_opening_package';

    public function __construct(private ClubCutoverAuthorityResolver $authority, private ClubAccountingCutoverPackageService $packages, private ClubJournalPostingService $journals, private OrganisationTransactionGuard $organisations, private CutoverExecutionAuditRecorder $audit) {}

    public function execute(CutoverExecutionRequest $request): ClubAccountingCutoverExecutionSuccess
    {
        $this->audit->assertStandalone();
        try {
            $this->audit->attempt($request);
        } catch (OrganisationAccessRejected|CutoverExecutionException $failure) {
            throw $failure;
        } catch (Throwable $failure) {
            throw new CutoverExecutionAuditException('attempt', $failure);
        }

        $callbackFinished = false;
        $pdo = null;
        try {
            $pdo = DB::connection()->getPdo();
            return $this->organisations->run($request->clubId, function (OrganisationTransactionContext $context) use ($request, &$callbackFinished): ClubAccountingCutoverExecutionSuccess {
                $callbackFinished = false;
                $result = $this->executeFinancial($context, $request);
                $callbackFinished = true;
                return $result;
            }, attempts: 3);
        } catch (Throwable $failure) {
            if (! $this->rollbackConfirmed($pdo) || ($callbackFinished && ! $this->serverAborted($failure))) {
                $unknown = new CutoverExecutionOutcomeUnknownException('Opening execution outcome is indeterminate; reconciliation is required.', previous: $failure);
                try {
                    $this->audit->indeterminate($request);
                } catch (Throwable $auditFailure) {
                    throw new CutoverExecutionAuditException('indeterminate', $auditFailure, $unknown);
                }
                throw $unknown;
            }
            if ($failure instanceof QueryException && ! $failure instanceof CutoverExecutionConflictException) {
                $failure = new CutoverExecutionConflictException('A concurrent cutover execution conflicted.', previous: $failure);
            }
            try {
                $this->audit->failure($request, $failure, $failure instanceof CutoverExecutionConflictException);
            } catch (Throwable $auditFailure) {
                throw new CutoverExecutionAuditException('terminal', $auditFailure, $failure);
            }
            throw $failure;
        }
    }

    private function rollbackConfirmed(?PDO $original): bool
    {
        try {
            $current = DB::connection()->getRawPdo();
            return $original !== null && ! $original->inTransaction() && $current instanceof PDO
                && $current === $original && ! $current->inTransaction() && DB::transactionLevel() === 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function serverAborted(Throwable $failure): bool
    {
        for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
            if (in_array((string) $cause->getCode(), ['40001', '40P01'], true)) {
                return true;
            }
        }
        return false;
    }

    private function executeFinancial(OrganisationTransactionContext $context, CutoverExecutionRequest $request): ClubAccountingCutoverExecutionSuccess
    {
        $context->assertUsable($request->clubId);
        $actor = User::query()->find($request->technicalOperatorUserId);
        if (! $actor) {
            throw new CutoverExecutionException('The cutover execution request is invalid.');
        }
        $package = ClubAccountingCutoverPackage::query()->whereKey($request->packageId)->where('club_id', $context->organisationId)->lockForUpdate()->first();
        if (! $package) {
            throw new CutoverExecutionException('The cutover package violates club scope.');
        }
        $existing = ClubAccountingCutoverExecutionSuccess::query()->where('execution_reference', $request->executionReference)->where('club_id', $context->organisationId)->lockForUpdate()->first();
        $boundary = ClubAccountingCutoverExecutionSuccess::query()->where('club_id', $package->club_id)->where('lions_year', $package->lions_year)->where('cutover_date', $package->cutover_date)->where('opening_source_type', self::SOURCE_TYPE)->lockForUpdate()->first();
        if ($existing || $boundary) {
            return $this->replay($request, $package, $actor, $existing, $boundary);
        }
        if ($package->status !== ClubAccountingCutoverPackage::STATUS_ACTIVATION_AUTHORIZED || $package->lock_version !== $request->expectedLockVersion || ! hash_equals((string) $package->canonical_checksum, $request->canonicalChecksum)) {
            throw new CutoverExecutionException('The package is not executable or its binding is stale.');
        }

        $appointment = $this->authority->appointment($package, $actor, ClubAccountingCutoverAuthorityAppointment::TYPE_TECHNICAL_OPERATOR);
        $permission = $this->authority->permission($package, $actor, 'finance.cutover.package.execute');
        [$review, $approval] = $this->trustedBindings($package);
        $dryRun = $this->decisionPair($package, ClubAccountingCutoverDecision::STAGE_DRY_RUN, $review, $approval);
        $activation = $this->decisionPair($package, ClubAccountingCutoverDecision::STAGE_ACTIVATION, $review, $approval);
        if (ClubAccountingCutoverAuthorityConsumption::query()->where('cutover_package_id', $package->id)->exists() || ClubJournalEntry::query()->where('club_id', $package->club_id)->where('source_type', self::SOURCE_TYPE)->exists()) {
            throw new CutoverExecutionConflictException('A prior opening execution exists.');
        }
        $validation = $this->packages->validate($package);
        $checksum = $this->packages->checksum($package);
        $preview = $this->packages->preview($package);
        if (! $validation['valid'] || ! hash_equals($request->canonicalChecksum, $checksum) || $preview['status'] !== 'BALANCED_PREVIEW') {
            throw new CutoverExecutionException('The package is not ready for opening posting.');
        }
        $period = ClubAccountingPeriod::query()->where('club_id', $package->club_id)->where('lions_year', $package->lions_year)->where('status', ClubAccountingPeriod::STATUS_OPEN)->lockForUpdate()->first();
        if (! $period) {
            throw new CutoverExecutionException('The cutover accounting period is unavailable.');
        }
        $lines = collect($preview['lines'])->filter(fn ($line) => $line['debit_paise'] > 0 || $line['credit_paise'] > 0)->map(function ($line) use ($package): JournalLineInput {
            $row = $package->scheduleRows()->whereKey($line['schedule_row_id'])->first();
            $account = $row?->ledger_account_id
                ? LedgerAccount::query()->whereKey($row->ledger_account_id)->where('club_id', $package->club_id)->where('is_active', true)->first()
                : LedgerAccount::query()->where('club_id', $package->club_id)->where('code', $line['account_code'])->where('is_active', true)->first();
            if (! $account) {
                throw new CutoverExecutionException('An opening journal account is unavailable.');
            }

            if (! $row || $row->fund_code !== $line['fund_code']) {
                throw new CutoverExecutionException('Opening row traceability is invalid.');
            }

            return new JournalLineInput($account->id, $line['direction'], max($line['debit_paise'], $line['credit_paise']), 'Opening balance: '.$line['row_key'], $row->fund_code, $row->id);
        })->values()->all();
        $journal = $this->journals->post(new JournalPostingRequest($package->club_id, $period->id, $package->cutover_date->format('Y-m-d'), 'Approved opening balances for '.$package->lions_year, 'INR', self::SOURCE_TYPE, $package->public_id, 'activated', $request->idempotencyKey, $actor->id, $lines, metadata: ['package_version' => $package->package_version, 'canonical_checksum' => $checksum, 'chart_version' => $package->chart_version, 'president_activation_decision' => $activation['president']->public_id, 'ca_activation_decision' => $activation['ca']->public_id]));
        $success = ClubAccountingCutoverExecutionSuccess::create(['club_id' => $package->club_id, 'cutover_package_id' => $package->id, 'package_version' => $package->package_version, 'canonical_checksum' => $checksum, 'lions_year' => $package->lions_year, 'cutover_date' => $package->cutover_date, 'opening_source_type' => self::SOURCE_TYPE, 'execution_reference' => $request->executionReference, 'idempotency_key' => $request->idempotencyKey, 'president_activation_decision_id' => $activation['president']->id, 'ca_activation_decision_id' => $activation['ca']->id, 'journal_entry_id' => $journal->id, 'executed_by_user_id' => $actor->id, 'technical_appointment_id' => $appointment->id, 'permission_assignment_id' => $permission->id, 'committed_at' => now()]);
        ClubAccountingCutoverAuthorityConsumption::create(['club_id' => $package->club_id, 'cutover_package_id' => $package->id, 'package_version' => $package->package_version, 'canonical_checksum' => $checksum, 'lions_year' => $package->lions_year, 'cutover_date' => $package->cutover_date, 'opening_source_type' => self::SOURCE_TYPE, 'president_activation_decision_id' => $activation['president']->id, 'ca_activation_decision_id' => $activation['ca']->id, 'execution_success_id' => $success->id, 'journal_entry_id' => $journal->id, 'consumed_at' => now()]);
        $package->update(['status' => ClubAccountingCutoverPackage::STATUS_POSTED, 'opening_journal_entry_id' => $journal->id, 'posted_at' => now(), 'posted_by_user_id' => $actor->id, 'lock_version' => $package->lock_version + 1, 'updated_by_user_id' => $actor->id]);
        $this->event($request, $actor, 'execution_success', 'posted', $success);

        return $success->fresh()->load('journalEntry.lines');
    }

    private function replay(CutoverExecutionRequest $request, ClubAccountingCutoverPackage $package, User $actor, ?ClubAccountingCutoverExecutionSuccess $byReference, ?ClubAccountingCutoverExecutionSuccess $byBoundary): ClubAccountingCutoverExecutionSuccess
    {
        if (! $byReference || ! $byBoundary || ! $byReference->is($byBoundary) || $byReference->cutover_package_id !== $package->id || $byReference->package_version !== $package->package_version || $byReference->executed_by_user_id !== $actor->id || ! hash_equals($byReference->canonical_checksum, $request->canonicalChecksum) || strtolower($byReference->idempotency_key) !== strtolower($request->idempotencyKey)) {
            throw new CutoverExecutionConflictException('The execution retry conflicts with a committed opening journal.');
        }
        $consumption = ClubAccountingCutoverAuthorityConsumption::query()->where('execution_success_id', $byReference->id)->where('journal_entry_id', $byReference->journal_entry_id)->first();
        if (! $consumption || $consumption->president_activation_decision_id !== $byReference->president_activation_decision_id || $consumption->ca_activation_decision_id !== $byReference->ca_activation_decision_id || $package->opening_journal_entry_id !== $byReference->journal_entry_id || $package->status !== ClubAccountingCutoverPackage::STATUS_POSTED) {
            throw new CutoverExecutionConflictException('The committed execution linkage is inconsistent.');
        }
        $this->event($request, $actor, 'execution_replay', 'replayed', $byReference);

        return $byReference->fresh()->load('journalEntry.lines');
    }

    private function trustedBindings(ClubAccountingCutoverPackage $package): array
    {
        $review = ClubAccountingCutoverReview::query()->where('cutover_package_id', $package->id)->where('status', 'current')->whereNotNull('authority_basis')->whereDoesntHave('events', fn ($query) => $query->whereIn('event_type', ['revoked', 'invalidated']))->latest('id')->first();
        $approval = ClubAccountingCutoverApproval::query()->where('cutover_package_id', $package->id)->where('status', 'current')->whereNotNull('authority_basis')->whereDoesntHave('events', fn ($query) => $query->whereIn('event_type', ['revoked', 'invalidated']))->latest('id')->first();
        foreach ([$review, $approval] as $binding) {
            if (! $binding || $binding->package_version !== $package->package_version || ! hash_equals($binding->package_checksum, (string) $package->canonical_checksum)) {
                throw new CutoverExecutionException('Trusted review and President approval are required.');
            }
        }
        if ($review->selected_classification !== $package->dr033_classification || $review->recorded_by_user_id === $approval->recorded_by_user_id) {
            throw new CutoverExecutionException('The trusted approval binding is invalid.');
        }
        $reviewer = User::query()->find($review->recorded_by_user_id);
        $president = User::query()->find($approval->recorded_by_user_id);
        if (! $reviewer || ! $president) {
            throw new CutoverExecutionException('The trusted approval actors are unavailable.');
        }
        $this->authority->appointment($package, $reviewer, ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA);
        $this->authority->permission($package, $reviewer, 'finance.cutover.package.accounting-review');
        $this->authority->president($package, $president, 'finance.cutover.package.business-approve');

        return [$review, $approval];
    }

    private function decisionPair(ClubAccountingCutoverPackage $package, string $stage, $review, $approval): array
    {
        $decisions = ClubAccountingCutoverDecision::query()->with('events')->where('cutover_package_id', $package->id)->where('decision_stage', $stage)->get()->reject(fn ($decision) => $decision->events->isNotEmpty())->filter(fn ($decision) => $decision->package_version === $package->package_version && hash_equals($decision->canonical_checksum, (string) $package->canonical_checksum) && $decision->accounting_review_id === $review->id && $decision->president_approval_id === $approval->id);
        $president = $decisions->where('actor_authority', 'president');
        $ca = $decisions->where('actor_authority', 'independent_ca');
        if ($president->count() !== 1 || $ca->count() !== 1 || $president->first()->recorded_by_user_id === $ca->first()->recorded_by_user_id) {
            throw new CutoverExecutionException('Separate current President and CA decisions are required.');
        }

        $presidentActor = User::query()->find($president->first()->recorded_by_user_id);
        $caActor = User::query()->find($ca->first()->recorded_by_user_id);
        if (! $presidentActor || ! $caActor) {
            throw new CutoverExecutionException('A decision actor is unavailable.');
        }
        $permission = $stage === ClubAccountingCutoverDecision::STAGE_DRY_RUN
            ? 'finance.cutover.package.dry-run-accept'
            : 'finance.cutover.package.activate-authorize';
        $this->authority->president($package, $presidentActor, $permission);
        $this->authority->appointment($package, $caActor, ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA);
        $this->authority->permission($package, $caActor, $permission);

        return ['president' => $president->first(), 'ca' => $ca->first()];
    }

    private function event(CutoverExecutionRequest $request, User $actor, string $type, string $result, ?ClubAccountingCutoverExecutionSuccess $success = null, ?string $reason = null): void
    {
        ClubAccountingCutoverExecutionEvent::create(['club_id' => $request->clubId, 'cutover_package_id' => $request->packageId, 'event_type' => $type, 'event_reference' => (string) Str::orderedUuid(), 'attempted_execution_reference' => $request->executionReference, 'execution_success_id' => $success?->id, 'result' => $result, 'reason_code' => $reason, 'actor_user_id' => $actor->id, 'recorded_at' => now()]);
    }
}
