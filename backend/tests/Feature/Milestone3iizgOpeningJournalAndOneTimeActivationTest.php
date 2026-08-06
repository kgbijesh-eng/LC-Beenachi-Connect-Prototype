<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverApprovalEvent;
use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverAuthorityConsumption;
use App\Models\ClubAccountingCutoverExecutionSuccess;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverReviewEvent;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\ClubMembership;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ClubAccountingCutoverPackageService;
use App\Services\Finance\ClubCutoverAuthorityAdministrationService;
use App\Services\Finance\ClubCutoverAuthorityResolver;
use App\Services\Finance\ClubCutoverLifecycleService;
use App\Services\Finance\ClubOpeningJournalActivationService;
use App\Services\Finance\CutoverExecutionRequest;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;
use App\Services\Finance\Exceptions\CutoverExecutionConflictException;
use App\Services\Finance\Exceptions\CutoverExecutionException;
use App\Services\Finance\Exceptions\CutoverPackageException;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Milestone3iizgOpeningJournalAndOneTimeActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_exist_but_no_generic_role_or_finance_manage_grants_cutover_authority(): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $cutover = Permission::query()->where('slug', 'like', 'finance.cutover.package.%')->pluck('slug');
        $this->assertCount(10, $cutover);
        foreach (Role::all() as $role) {
            $this->assertSame([], $role->permissions()->whereIn('slug', $cutover)->pluck('slug')->all());
        }
        $this->assertNotNull(Permission::query()->where('slug', 'finance.manage')->first());
    }

    public function test_authorized_lifecycle_sets_dr033_once_and_records_immutable_decisions(): void
    {
        $fixture = $this->authorizedFixture();
        $package = $fixture['package'];
        $this->assertSame(ClubAccountingCutoverPackage::STATUS_ACTIVATION_AUTHORIZED, $package->status);
        $this->assertSame('OPENING-BALANCE-EQUITY', $package->dr033_classification);
        $this->assertSame(4, $package->decisions()->count());
        $this->assertSame(2, $package->decisions()->where('decision_stage', 'dry_run')->count());
        $this->assertSame(2, $package->decisions()->where('decision_stage', 'activation')->count());
        $this->assertSame('2027-07-02', $package->reviews()->firstOrFail()->review_date->format('Y-m-d'));
        $this->assertSame('2027-07-03', $package->approvals()->firstOrFail()->approval_date->format('Y-m-d'));
        $this->expectException(LogicException::class);
        $package->decisions()->firstOrFail()->update(['reason' => 'Forbidden mutation']);
    }

    public function test_posting_metadata_is_allowed_only_during_activation_authorized_to_posted(): void
    {
        $fixture = $this->authorizedFixture();
        $package = $fixture['package'];
        foreach ([
            ['posted_at' => now()],
            ['posted_by_user_id' => $fixture['operator']->id],
        ] as $attributes) {
            try {
                $package->fresh()->update($attributes);
                $this->fail('Same-state posting metadata mutation must fail.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            DB::transaction(fn () => DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['posted_at' => now()]));
            $this->fail('Direct posting metadata mutation must fail at the database boundary.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $success = app(ClubOpeningJournalActivationService::class)->execute($this->request($fixture));
        try {
            DB::transaction(fn () => DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['opening_journal_entry_id' => null]));
            $this->fail('The committed opening journal linkage must be immutable.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame($success->journal_entry_id, $package->fresh()->opening_journal_entry_id);
    }

    public function test_trusted_review_and_approval_are_database_immutable_and_invalidation_is_append_only(): void
    {
        $fixture = $this->authorizedFixture();
        $review = $fixture['package']->reviews()->firstOrFail();
        $approval = $fixture['package']->approvals()->firstOrFail();
        foreach ([
            fn () => DB::transaction(fn () => DB::table('club_accounting_cutover_reviews')->where('id', $review->id)->update(['written_rationale' => 'Silently rewritten'])),
            fn () => DB::transaction(fn () => DB::table('club_accounting_cutover_approvals')->where('id', $approval->id)->delete()),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('A trusted decision mutation must fail at the database boundary.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        ClubAccountingCutoverReviewEvent::create(['club_id' => $review->club_id, 'cutover_package_id' => $review->cutover_package_id, 'cutover_review_id' => $review->id, 'event_type' => 'invalidated', 'event_reference' => 'REVIEW-INVALIDATED-001', 'reason' => 'Binding withdrawn without rewriting the review.', 'recorded_by_user_id' => $fixture['ca']->id, 'recorded_at' => now()]);
        ClubAccountingCutoverApprovalEvent::create(['club_id' => $approval->club_id, 'cutover_package_id' => $approval->cutover_package_id, 'cutover_approval_id' => $approval->id, 'event_type' => 'invalidated', 'event_reference' => 'APPROVAL-INVALIDATED-001', 'reason' => 'Binding withdrawn without rewriting the approval.', 'recorded_by_user_id' => $fixture['president']->id, 'recorded_at' => now()]);
        $this->assertSame('current', $review->fresh()->status);
        $this->assertSame('current', $approval->fresh()->status);
        $this->expectException(CutoverExecutionException::class);
        app(ClubOpeningJournalActivationService::class)->execute($this->request($fixture));
    }

    public function test_first_execution_posts_once_consumes_pair_and_exact_replay_returns_same_journal(): void
    {
        $fixture = $this->authorizedFixture();
        $request = $this->request($fixture);
        $first = app(ClubOpeningJournalActivationService::class)->execute($request);
        $replay = app(ClubOpeningJournalActivationService::class)->execute($request);

        $this->assertTrue($first->is($replay));
        $this->assertSame(1, ClubJournalEntry::query()->where('source_type', 'accounting_opening_package')->count());
        $this->assertSame(1, ClubAccountingCutoverExecutionSuccess::count());
        $this->assertSame(1, ClubAccountingCutoverAuthorityConsumption::count());
        $this->assertSame(ClubAccountingCutoverPackage::STATUS_POSTED, $fixture['package']->fresh()->status);
        $this->assertSame($first->journal_entry_id, $fixture['package']->fresh()->opening_journal_entry_id);
        $this->assertSame('posted', $first->journalEntry->status);
        $this->assertSame($first->journalEntry->lines->where('direction', 'debit')->sum('amount_paise'), $first->journalEntry->lines->where('direction', 'credit')->sum('amount_paise'));
        $this->assertDatabaseHas('club_accounting_cutover_execution_events', ['event_type' => 'execution_replay', 'execution_success_id' => $first->id]);
    }

    public function test_revoked_activation_or_changed_retry_fails_without_partial_posting_or_consumption(): void
    {
        $fixture = $this->authorizedFixture();
        $activation = $fixture['package']->decisions()->where('decision_stage', 'activation')->where('actor_authority', 'president')->firstOrFail();
        app(ClubCutoverLifecycleService::class)->revoke($activation, $fixture['president'], 'REVOKE-ACTIVATION-001', 'President withdrew unconsumed authority.');
        try {
            app(ClubOpeningJournalActivationService::class)->execute($this->request($fixture));
            $this->fail('Revoked activation must fail.');
        } catch (CutoverExecutionException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, ClubJournalEntry::query()->where('source_type', 'accounting_opening_package')->count());
        $this->assertSame(0, ClubAccountingCutoverExecutionSuccess::count());
        $this->assertSame(0, ClubAccountingCutoverAuthorityConsumption::count());
        $this->assertDatabaseHas('club_accounting_cutover_execution_events', ['event_type' => 'execution_failure', 'result' => 'not_posted']);
    }

    public function test_failure_after_journal_creation_rolls_back_journal_success_consumption_link_and_posted_state(): void
    {
        $fixture = $this->authorizedFixture();
        ClubAccountingCutoverExecutionSuccess::creating(fn () => throw new \RuntimeException('Synthetic committed-success failure.'));
        try {
            app(ClubOpeningJournalActivationService::class)->execute($this->request($fixture));
            $this->fail('Synthetic failure must escape.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic committed-success failure.', $exception->getMessage());
        }
        $this->assertSame(0, ClubJournalEntry::query()->where('source_type', 'accounting_opening_package')->count());
        $this->assertSame(0, ClubAccountingCutoverExecutionSuccess::count());
        $this->assertSame(0, ClubAccountingCutoverAuthorityConsumption::count());
        $this->assertNull($fixture['package']->fresh()->opening_journal_entry_id);
        $this->assertNotSame(ClubAccountingCutoverPackage::STATUS_POSTED, $fixture['package']->fresh()->status);
        $this->assertDatabaseHas('club_accounting_cutover_execution_events', ['event_type' => 'execution_failure', 'result' => 'not_posted']);
    }

    #[DataProvider('materialFailureStages')]
    public function test_each_material_write_stage_rolls_back_completely(string $stage): void
    {
        $fixture = $this->authorizedFixture();
        $failure = fn () => throw new \RuntimeException("Synthetic {$stage} failure.");
        match ($stage) {
            'journal_create' => ClubJournalEntry::creating(fn (ClubJournalEntry $entry) => $entry->source_type === 'accounting_opening_package' ? $failure() : null),
            'journal_line_create' => ClubJournalLine::creating($failure),
            'journal_post' => ClubJournalEntry::updating(fn (ClubJournalEntry $entry) => $entry->status === ClubJournalEntry::STATUS_POSTED ? $failure() : null),
            'execution_success' => ClubAccountingCutoverExecutionSuccess::creating($failure),
            'authority_consumption' => ClubAccountingCutoverAuthorityConsumption::creating($failure),
            'package_linkage' => ClubAccountingCutoverPackage::updating(fn (ClubAccountingCutoverPackage $package) => $package->status === ClubAccountingCutoverPackage::STATUS_POSTED ? $failure() : null),
        };

        try {
            app(ClubOpeningJournalActivationService::class)->execute($this->request($fixture));
            $this->fail("{$stage} must fail.");
        } catch (\RuntimeException $exception) {
            $this->assertSame("Synthetic {$stage} failure.", $exception->getMessage());
        }
        $this->assertSame(0, ClubJournalEntry::query()->where('source_type', 'accounting_opening_package')->count());
        $this->assertSame(0, ClubAccountingCutoverExecutionSuccess::count());
        $this->assertSame(0, ClubAccountingCutoverAuthorityConsumption::count());
        $this->assertNull($fixture['package']->fresh()->opening_journal_entry_id);
        $this->assertSame(ClubAccountingCutoverPackage::STATUS_ACTIVATION_AUTHORIZED, $fixture['package']->fresh()->status);
    }

    public static function materialFailureStages(): array
    {
        return collect(['journal_create', 'journal_line_create', 'journal_post', 'execution_success', 'authority_consumption', 'package_linkage'])
            ->mapWithKeys(fn (string $stage): array => [$stage => [$stage]])->all();
    }

    public function test_committed_execution_changed_retry_conflicts_and_database_uniqueness_rejects_duplicate_success(): void
    {
        $fixture = $this->authorizedFixture();
        $service = app(ClubOpeningJournalActivationService::class);
        $request = $this->request($fixture);
        $success = $service->execute($request);
        $changed = new CutoverExecutionRequest($request->clubId, $request->packageId, $request->expectedLockVersion, $request->canonicalChecksum, $request->executionReference, (string) Str::uuid(), $request->technicalOperatorUserId);
        try {
            $service->execute($changed);
            $this->fail('Changed retry must conflict.');
        } catch (CutoverExecutionConflictException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, ClubJournalEntry::query()->where('source_type', 'accounting_opening_package')->count());
        $this->assertDatabaseHas('club_accounting_cutover_execution_events', ['event_type' => 'execution_conflict', 'result' => 'not_posted']);
        try {
            $success->replicate()->fill(['public_id' => (string) Str::uuid(), 'execution_reference' => 'EXECUTION-DUPLICATE'])->save();
            $this->fail('Package binding uniqueness must reject duplicate success.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_competing_first_execution_for_same_boundary_commits_at_most_once(): void
    {
        $fixture = $this->authorizedFixture();
        $service = app(ClubOpeningJournalActivationService::class);
        $first = $this->request($fixture);
        $service->execute($first);
        $competing = new CutoverExecutionRequest($first->clubId, $first->packageId, $first->expectedLockVersion, $first->canonicalChecksum, 'EXECUTION-COMPETING-002', (string) Str::uuid(), $first->technicalOperatorUserId);
        try {
            $service->execute($competing);
            $this->fail('A competing first execution must conflict with the committed boundary.');
        } catch (CutoverExecutionConflictException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, ClubJournalEntry::query()->where('source_type', 'accounting_opening_package')->count());
        $this->assertSame(1, ClubAccountingCutoverExecutionSuccess::count());
        $this->assertSame(1, ClubAccountingCutoverAuthorityConsumption::count());
    }

    public function test_missing_or_ambiguous_president_resolution_fails_closed(): void
    {
        $fixture = $this->baseFixture();
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $admin->assignPermission($fixture['package'], $fixture['president'], 'finance.cutover.package.business-approve', 'PRESIDENT-PERMISSION', $fixture['grantor']);
        $other = User::factory()->create();
        ClubMembership::create(['club_id' => $fixture['club']->id, 'user_id' => $other->id, 'role_id' => Role::where('slug', 'president-admin')->firstOrFail()->id, 'status' => 'active']);
        $this->expectException(CutoverAuthorizationException::class);
        app(ClubCutoverAuthorityResolver::class)->president($fixture['package'], $fixture['president'], 'finance.cutover.package.business-approve');
    }

    public function test_permission_without_appointment_and_cross_package_appointment_fail_closed(): void
    {
        $fixture = $this->baseFixture();
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $admin->assignPermission($fixture['package'], $fixture['ca'], 'finance.cutover.package.accounting-review', 'CA-PERM-ONLY', $fixture['grantor']);
        $resolver = app(ClubCutoverAuthorityResolver::class);
        $this->expectException(CutoverAuthorizationException::class);
        $resolver->appointment($fixture['package'], $fixture['ca'], ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA);
    }

    public function test_database_enforces_single_current_appointment_and_declared_execution_uniqueness(): void
    {
        $fixture = $this->baseFixture();
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $admin->appoint($fixture['package'], $fixture['ca'], ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA, 'CA-PRIMARY-APPOINTMENT', $fixture['grantor']);
        try {
            $admin->appoint($fixture['package'], User::factory()->create(), ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA, 'CA-CONFLICTING-APPOINTMENT', $fixture['grantor']);
            $this->fail('A second current appointment for an exclusive package authority must fail.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $migration = file_get_contents(database_path('migrations/2026_08_03_000003_add_cutover_activation_foundation.php'));
        foreach ([
            'cutover_success_package_unique', 'cutover_package_opening_journal_unique',
            'cutover_success_boundary_unique', "execution_reference', 120)->unique()",
            'cutover_success_package_club_fk', 'cutover_success_journal_club_fk',
            'cutover_consumption_pair_unique', 'cutover_consumption_success_unique',
            'cutover_one_active_appointment_guard', 'cutover_one_current_decision_guard',
        ] as $constraint) {
            $this->assertStringContainsString($constraint, $migration);
        }
    }

    public function test_president_cannot_make_independent_ca_dry_run_decision_even_with_ca_authority(): void
    {
        $fixture = $this->businessApprovedFixture();
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $caAppointment = ClubAccountingCutoverAuthorityAppointment::query()->where('cutover_package_id', $fixture['package']->id)->where('authority_type', 'independent_ca')->firstOrFail();
        $admin->revokeAppointment($caAppointment, $fixture['grantor'], 'REVOKE-CA-FOR-PRESIDENT-DRY', 'Simulate an incorrect replacement appointment.');
        $admin->appoint($fixture['package'], $fixture['president'], 'independent_ca', 'APPOINT-PRESIDENT-AS-CA-DRY', $fixture['grantor']);

        try {
            app(ClubCutoverLifecycleService::class)->decide($fixture['package']->fresh(), $fixture['president'], $fixture['package']->fresh()->lock_version, 'dry_run', 'independent_ca', 'INVALID-PRESIDENT-CA-DRY', 'President must not act as the trusted reviewer.');
            $this->fail('The President approver must not submit the independent-CA dry-run decision.');
        } catch (CutoverPackageException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(ClubAccountingCutoverPackage::STATUS_BUSINESS_APPROVED, $fixture['package']->fresh()->status);
        $this->assertSame(0, $fixture['package']->decisions()->count());
    }

    public function test_president_cannot_make_independent_ca_activation_decision_even_with_ca_authority(): void
    {
        $fixture = $this->businessApprovedFixture();
        $life = app(ClubCutoverLifecycleService::class);
        $package = $fixture['package']->fresh();
        $life->decide($package, $fixture['president'], $package->lock_version, 'dry_run', 'president', 'VALID-PRESIDENT-DRY', 'President accepts dry run.');
        $package = $package->fresh();
        $life->decide($package, $fixture['ca'], $package->lock_version, 'dry_run', 'independent_ca', 'VALID-CA-DRY', 'Reviewer accepts dry run.');
        $package = $package->fresh();
        $life->decide($package, $fixture['president'], $package->lock_version, 'activation', 'president', 'VALID-PRESIDENT-ACTIVATION', 'President authorizes activation.');

        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $caAppointment = ClubAccountingCutoverAuthorityAppointment::query()->where('cutover_package_id', $package->id)->where('authority_type', 'independent_ca')->firstOrFail();
        $admin->revokeAppointment($caAppointment, $fixture['grantor'], 'REVOKE-CA-FOR-PRESIDENT-ACT', 'Simulate an incorrect replacement appointment.');
        $admin->appoint($package, $fixture['president'], 'independent_ca', 'APPOINT-PRESIDENT-AS-CA-ACT', $fixture['grantor']);

        try {
            $life->decide($package->fresh(), $fixture['president'], $package->fresh()->lock_version, 'activation', 'independent_ca', 'INVALID-PRESIDENT-CA-ACT', 'President must not act as the trusted reviewer.');
            $this->fail('The President approver must not submit the independent-CA activation decision.');
        } catch (CutoverPackageException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(ClubAccountingCutoverPackage::STATUS_DRY_RUN_ACCEPTED, $package->fresh()->status);
        $this->assertSame(1, $package->decisions()->where('decision_stage', 'activation')->count());
    }

    public function test_accounting_reviewer_cannot_make_president_decision(): void
    {
        $fixture = $this->businessApprovedFixture();

        try {
            app(ClubCutoverLifecycleService::class)->decide($fixture['package'], $fixture['ca'], $fixture['package']->lock_version, 'dry_run', 'president', 'INVALID-CA-PRESIDENT-DRY', 'CA must not act as President.');
            $this->fail('The accounting reviewer must not submit a President decision.');
        } catch (CutoverAuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(ClubAccountingCutoverPackage::STATUS_BUSINESS_APPROVED, $fixture['package']->fresh()->status);
    }

    public function test_duplicate_current_president_decision_is_rejected_but_revoked_decision_can_be_replaced(): void
    {
        $this->assertDuplicateDecisionRejectedAndReplacementAllowed('president', 'president');
    }

    public function test_duplicate_current_ca_decision_is_rejected_but_revoked_decision_can_be_replaced(): void
    {
        $this->assertDuplicateDecisionRejectedAndReplacementAllowed('independent_ca', 'ca');
    }

    public function test_database_rejects_duplicate_current_decision(): void
    {
        $fixture = $this->businessApprovedFixture();
        $life = app(ClubCutoverLifecycleService::class);
        $decision = $life->decide($fixture['package'], $fixture['president'], $fixture['package']->lock_version, 'dry_run', 'president', 'DB-GUARD-FIRST', 'Current President decision.');

        $this->expectException(QueryException::class);
        $decision->replicate()->fill(['public_id' => (string) Str::uuid(), 'decision_reference' => 'DB-GUARD-DUPLICATE'])->save();
    }

    public function test_scope_has_no_cutover_delivery_surface_or_source_integration(): void
    {
        $this->assertCount(0, collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'cutover')));
        $routes = file_get_contents(base_path('routes/api.php'));
        $this->assertStringNotContainsString('cutover', strtolower($routes));
        $paymentService = file_get_contents(app_path('Services/Finance/PaymentRecordingService.php'));
        $this->assertStringNotContainsString('cutover', strtolower($paymentService));
    }

    private function authorizedFixture(): array
    {
        $f = $this->businessApprovedFixture();
        $life = app(ClubCutoverLifecycleService::class);
        $p = $f['package']->fresh();
        $life->decide($p, $f['president'], $p->lock_version, 'dry_run', 'president', 'DRY-PRESIDENT-001', 'President accepts exact dry run.');
        $p = $p->fresh();
        $life->decide($p, $f['ca'], $p->lock_version, 'dry_run', 'independent_ca', 'DRY-CA-001', 'Independent CA accepts exact dry run.');
        $p = $p->fresh();
        $life->decide($p, $f['president'], $p->lock_version, 'activation', 'president', 'ACT-PRESIDENT-001', 'President authorizes exact activation.');
        $p = $p->fresh();
        $life->decide($p, $f['ca'], $p->lock_version, 'activation', 'independent_ca', 'ACT-CA-001', 'Independent CA authorizes exact activation.');

        return [...$f, 'package' => $p->fresh()];
    }

    private function businessApprovedFixture(): array
    {
        $f = $this->baseFixture();
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $life = app(ClubCutoverLifecycleService::class);
        foreach ([[$f['preparer'], 'prepare'], [$f['verifier'], 'verify'], [$f['ca'], 'accounting-review'], [$f['ca'], 'dry-run-accept'], [$f['ca'], 'activate-authorize'], [$f['ca'], 'rollback-authorize'], [$f['president'], 'business-approve'], [$f['president'], 'dry-run-accept'], [$f['president'], 'activate-authorize'], [$f['president'], 'rollback-authorize'], [$f['operator'], 'execute']] as $index => [$user, $permission]) {
            $admin->assignPermission($f['package'], $user, "finance.cutover.package.{$permission}", "ASSIGN-{$index}-{$permission}", $f['grantor']);
        }
        $admin->appoint($f['package'], $f['ca'], 'independent_ca', 'APPOINT-CA-001', $f['grantor']);
        $admin->appoint($f['package'], $f['operator'], 'technical_operator', 'APPOINT-OPERATOR-001', $f['grantor']);
        $package = $life->prepare($f['package'], $f['preparer'], $f['package']->lock_version);
        $package = $life->verify($package, $f['verifier'], $package->lock_version);
        $life->review($package, $f['ca'], $package->lock_version, 'OPENING-BALANCE-EQUITY', 'CA-REVIEW-001', '2027-07-02', 'Reconciled historical evidence substantiates opening balance equity.');
        $package = $package->fresh();
        $life->approve($package, $f['president'], $package->lock_version, '2027-07-03', 'PRESIDENT-APPROVAL-001');

        return [...$f, 'package' => $package->fresh()];
    }

    private function assertDuplicateDecisionRejectedAndReplacementAllowed(string $authority, string $actorKey): void
    {
        $fixture = $this->businessApprovedFixture();
        $life = app(ClubCutoverLifecycleService::class);
        $package = $fixture['package']->fresh();
        $decision = $life->decide($package, $fixture[$actorKey], $package->lock_version, 'dry_run', $authority, "FIRST-{$authority}", 'First current decision.');
        $package = $package->fresh();
        try {
            $life->decide($package, $fixture[$actorKey], $package->lock_version, 'dry_run', $authority, "DUPLICATE-{$authority}", 'Duplicate current decision.');
            $this->fail("A duplicate current {$authority} decision must fail.");
        } catch (CutoverPackageException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, $package->decisions()->where('decision_stage', 'dry_run')->where('actor_authority', $authority)->count());

        $life->revoke($decision, $fixture[$actorKey], "REVOKE-{$authority}", 'Withdraw current decision before replacement.');
        $package = $package->fresh();
        $life->decide($package, $fixture[$actorKey], $package->lock_version, 'dry_run', $authority, "REPLACEMENT-{$authority}", 'Legitimate replacement decision.');
        $this->assertSame(2, $package->decisions()->where('decision_stage', 'dry_run')->where('actor_authority', $authority)->count());
    }

    private function baseFixture(): array
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        foreach (['grantor', 'preparer', 'verifier', 'ca', 'president', 'operator'] as $key) {
            $$key = User::factory()->create();
        }
        $club = Club::create(['name' => 'Beenachi', 'code' => 'LCB']);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $president->id, 'role_id' => Role::where('slug', 'president-admin')->firstOrFail()->id, 'status' => 'active']);
        $accounts = [];
        foreach ([['cash', 'CASH-ON-HAND', 'asset', 'debit'], ['bank', 'BANK-OPERATING', 'asset', 'debit'], ['receivable', 'MEMBER-RECEIVABLES', 'asset', 'debit'], ['other_asset', 'CLUB-PROPERTY', 'asset', 'debit'], ['liability', 'LIABILITY-GENERAL', 'liability', 'credit'], ['restricted', 'LIABILITY-RESTRICTED-COLLECTION', 'liability', 'credit'], ['equity', 'OPENING-BALANCE-EQUITY', 'equity', 'credit'], ['surplus', 'ACCUMULATED-SURPLUS', 'equity', 'credit']] as [$key,$code,$type,$normal]) {
            $accounts[$key] = LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $grantor->id]);
        }
        ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $grantor->id]);
        $service = app(ClubAccountingCutoverPackageService::class);
        $package = $service->createDraft($club->id, $grantor->id);
        $rows = [
            ['schedule_type' => 'cash_on_hand', 'row_key' => 'CASH', 'non_sensitive_label' => 'Cash', 'ledger_account_id' => $accounts['cash']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 10000],
            ['schedule_type' => 'bank_account', 'row_key' => 'BANK', 'non_sensitive_label' => 'Bank', 'ledger_account_id' => $accounts['bank']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 20000],
            ['schedule_type' => 'member_receivables', 'row_key' => 'RECEIVABLES', 'non_sensitive_label' => 'Receivables', 'ledger_account_id' => $accounts['receivable']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 30000],
            ['schedule_type' => 'other_asset', 'row_key' => 'OTHER-ASSET', 'non_sensitive_label' => 'Other approved asset', 'ledger_account_id' => $accounts['other_asset']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 0],
            ['schedule_type' => 'liability', 'row_key' => 'LIABILITY', 'non_sensitive_label' => 'Liability', 'ledger_account_id' => $accounts['liability']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'external_restriction', 'row_key' => 'RESTRICTED', 'non_sensitive_label' => 'Restricted', 'ledger_account_id' => $accounts['restricted']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'general_club_fund', 'row_key' => 'GENERAL', 'non_sensitive_label' => 'General', 'presentation_group' => 'GENERAL-CLUB-FUND', 'direction' => 'credit', 'amount_paise' => 20000],
            ['schedule_type' => 'service_activity_fund', 'row_key' => 'SERVICE', 'non_sensitive_label' => 'Service', 'presentation_group' => 'SERVICE-ACTIVITY-FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'fellowship_member_activity_fund', 'row_key' => 'FELLOWSHIP', 'non_sensitive_label' => 'Fellowship', 'presentation_group' => 'FELLOWSHIP-MEMBER-ACTIVITY-FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'equity_preview', 'row_key' => 'EQUITY', 'non_sensitive_label' => 'Equity', 'ledger_account_id' => null, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'credit', 'amount_paise' => 40000],
        ];
        $package = $service->replaceSchedules($club->id, $package->id, $grantor->id, $package->lock_version, $rows);
        $evidence = collect(['cash_count', 'bank_reconciliation', 'member_receivables', 'liabilities_restrictions', 'reconciliation'])->map(fn ($type, $i) => ['evidence_type' => $type, 'opaque_reference' => 'EVIDENCE-'.strtoupper(str_replace('_', '-', $type)).'-001', 'evidence_date' => '2027-07-01', 'evidence_version' => 'V1', 'digest_sha256' => str_repeat(dechex($i + 1), 64), 'verification_status' => 'verified'])->all();
        $package = $service->replaceEvidenceReferences($club->id, $package->id, $grantor->id, $package->lock_version, $evidence);
        $package = $service->refreshValidation($club->id, $package->id, $grantor->id, $package->lock_version);

        return compact('club', 'grantor', 'preparer', 'verifier', 'ca', 'president', 'operator', 'accounts', 'package');
    }

    private function request(array $fixture): CutoverExecutionRequest
    {
        return new CutoverExecutionRequest($fixture['club']->id, $fixture['package']->id, $fixture['package']->lock_version, $fixture['package']->canonical_checksum, 'EXECUTION-OPENING-001', '11111111-1111-4111-8111-111111111111', $fixture['operator']->id);
    }
}
