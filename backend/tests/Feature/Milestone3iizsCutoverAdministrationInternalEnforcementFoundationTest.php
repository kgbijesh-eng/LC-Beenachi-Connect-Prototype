<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ClubAccountingCutoverPackageService;
use App\Services\Finance\ClubCutoverAuthorityAdministrationService;
use App\Services\Finance\ClubCutoverAuthorityReadService;
use App\Services\Finance\ClubCutoverAuthorityResolver;
use App\Services\Finance\ClubCutoverEvidenceVerificationService;
use App\Services\Finance\ClubCutoverPackageBootstrapService;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;
use App\Services\Finance\Exceptions\CutoverPackageException;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithCutoverGovernance;
use Tests\Support\CutoverPostgresTestCase;

class Milestone3iizsCutoverAdministrationInternalEnforcementFoundationTest extends CutoverPostgresTestCase
{
    use InteractsWithCutoverGovernance;

    public function test_package_bootstrap_requires_both_distinct_governance_officers_and_is_atomic_and_replay_safe(): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $club = Club::create(['name' => 'Bootstrap Club', 'code' => 'BST']);
        config(['instance.organisation_code' => 'BST']);
        foreach (['president', 'secretary', 'preparer'] as $key) {
            $$key = User::factory()->create();
        }
        $this->cutoverMember($club, $president, 'president-admin');
        $this->cutoverMember($club, $secretary, 'secretary');
        $this->cutoverMember($club, $preparer, 'treasurer');
        $service = app(ClubCutoverPackageBootstrapService::class);
        $operation = $service->request($club, $preparer, 1, '  bootstrap-001  ', $secretary);
        $this->assertSame('BOOTSTRAP-001', $operation->operation_reference);
        $this->assertSame($operation->id, $service->request($club, $preparer, 1, '  bootstrap-001  ', $secretary)->id);
        $this->assertSame($operation->id, $service->request($club, $preparer, 1, 'BOOTSTRAP-001', $secretary)->id);
        $this->assertSame('pending', $service->approve($operation, $president, 'BOOTSTRAP-001-P')->status);
        $this->assertDatabaseCount('club_accounting_cutover_packages', 0);
        $this->expectAuthorization(fn () => $service->approve($operation, $president, 'BOOTSTRAP-001-P2'));
        $applied = $service->approve($operation, $secretary, 'BOOTSTRAP-001-S');
        $this->assertSame('applied', $applied->status);
        $this->assertDatabaseHas('club_accounting_cutover_packages', ['id' => $applied->cutover_package_id, 'status' => 'draft']);
        $this->assertDatabaseHas('club_accounting_cutover_permission_assignments', ['id' => $applied->permission_assignment_id, 'cutover_package_id' => $applied->cutover_package_id, 'user_id' => $preparer->id]);
        $this->assertSame($applied->cutover_package_id, $service->approve($applied, $secretary, 'IGNORED-REPLAY')->cutover_package_id);
        $otherPreparer = User::factory()->create();
        $this->cutoverMember($club, $otherPreparer, 'treasurer');
        $this->expectAuthorization(fn () => $service->request($club, $otherPreparer, 1, ' bootstrap-001 ', $secretary));
        $this->expectException(CutoverPackageException::class);
        app(ClubAccountingCutoverPackageService::class)->createDraft($club->id, $preparer->id);
    }

    public function test_bootstrap_revalidates_preparer_and_governance_basis_before_atomic_apply(): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $club = Club::create(['name' => 'Bootstrap Revalidation', 'code' => 'BSR']);
        config(['instance.organisation_code' => 'BSR']);
        foreach (['president', 'secretary', 'preparer', 'outsider'] as $key) {
            $$key = User::factory()->create();
        }
        $this->cutoverMember($club, $president, 'president-admin');
        $this->cutoverMember($club, $secretary, 'secretary');
        $this->cutoverMember($club, $preparer, 'treasurer');
        $service = app(ClubCutoverPackageBootstrapService::class);
        $operation = $service->request($club, $preparer, 1, 'BOOTSTRAP-REVALIDATE', $secretary);
        $service->approve($operation, $president, 'BOOTSTRAP-REVALIDATE-P');
        ClubMembership::query()->where('club_id', $club->id)->where('user_id', $preparer->id)->update(['status' => 'inactive']);
        $this->expectAuthorization(fn () => $service->approve($operation, $secretary, 'BOOTSTRAP-REVALIDATE-S'));
        $this->assertDatabaseCount('club_accounting_cutover_packages', 0);
        $this->assertDatabaseCount('club_accounting_cutover_permission_assignments', 0);
        $this->expectAuthorization(fn () => $service->approve($operation, $outsider, 'BOOTSTRAP-WRONG-CLUB'));
    }

    public function test_runtime_permission_rejects_lost_treasurer_secretary_and_president_basis(): void
    {
        $f = $this->fixture();
        $resolver = app(ClubCutoverAuthorityResolver::class);
        $prepare = $this->dualAssign($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', $f['president'], $f['secretary'], 'ROLELOSS-PREPARE');
        $this->assertSame($prepare->id, $resolver->permission($f['package'], $f['treasurer'], 'finance.cutover.package.prepare')->id);
        ClubMembership::query()->where('club_id', $f['club']->id)->where('user_id', $f['treasurer']->id)->update(['role_id' => Role::where('slug', 'member')->firstOrFail()->id]);
        $this->expectAuthorization(fn () => $resolver->permission($f['package'], $f['treasurer'], 'finance.cutover.package.prepare'));

        $this->dualAssign($f['package'], $f['secretary'], 'finance.cutover.package.verify', $f['president'], $f['secretary'], 'ROLELOSS-VERIFY');
        ClubMembership::query()->where('club_id', $f['club']->id)->where('user_id', $f['secretary']->id)->update(['status' => 'inactive']);
        $this->expectAuthorization(fn () => $resolver->permission($f['package'], $f['secretary'], 'finance.cutover.package.verify'));
        ClubMembership::query()->where('club_id', $f['club']->id)->where('user_id', $f['secretary']->id)->update(['status' => 'active']);

        $this->dualAssign($f['package'], $f['president'], 'finance.cutover.package.business-approve', $f['president'], $f['secretary'], 'ROLELOSS-PRESIDENT');
        ClubMembership::query()->where('club_id', $f['club']->id)->where('user_id', $f['president']->id)->update(['status' => 'inactive']);
        $this->expectAuthorization(fn () => $resolver->permission($f['package'], $f['president'], 'finance.cutover.package.business-approve'));
    }

    public function test_runtime_permission_rejects_lost_ca_and_operator_appointment_basis(): void
    {
        $f = $this->fixture();
        $resolver = app(ClubCutoverAuthorityResolver::class);
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $caAppointment = $this->dualAppoint($f['package'], $f['ca'], 'independent_ca', $f['president'], $f['secretary'], 'ROLELOSS-CA-APPOINT');
        $this->dualAttestEligibility($caAppointment, $f['president'], $f['secretary'], 'ROLELOSS-CA-ELIGIBLE');
        $this->dualAssign($f['package'], $f['ca'], 'finance.cutover.package.accounting-review', $f['president'], $f['secretary'], 'ROLELOSS-CA-PERMISSION');
        $this->assertNotNull($resolver->permission($f['package'], $f['ca'], 'finance.cutover.package.accounting-review'));
        $admin->selfRevokeAppointment($caAppointment, $f['ca'], 'ROLELOSS-CA-REVOKE', 'Professional appointment withdrawn.');
        $this->expectAuthorization(fn () => $resolver->permission($f['package'], $f['ca'], 'finance.cutover.package.accounting-review'));

        $operatorAppointment = $this->dualAppoint($f['package'], $f['operator'], 'technical_operator', $f['president'], $f['secretary'], 'ROLELOSS-OPERATOR-APPOINT');
        $this->dualAssign($f['package'], $f['operator'], 'finance.cutover.package.execute', $f['president'], $f['secretary'], 'ROLELOSS-OPERATOR-PERMISSION');
        $this->assertNotNull($resolver->permission($f['package'], $f['operator'], 'finance.cutover.package.execute'));
        $admin->selfRevokeAppointment($operatorAppointment, $f['operator'], 'ROLELOSS-OPERATOR-REVOKE', 'Technical appointment withdrawn.');
        $this->expectAuthorization(fn () => $resolver->permission($f['package'], $f['operator'], 'finance.cutover.package.execute'));
    }

    public function test_runtime_appointments_reject_later_president_and_secretary_conflicts(): void
    {
        $f = $this->fixture();
        $resolver = app(ClubCutoverAuthorityResolver::class);
        $caAppointment = $this->dualAppoint($f['package'], $f['ca'], 'independent_ca', $f['president'], $f['secretary'], 'ROLEGAIN-CA');
        $this->dualAttestEligibility($caAppointment, $f['president'], $f['secretary'], 'ROLEGAIN-CA-ELIGIBILITY');
        $operatorAppointment = $this->dualAppoint($f['package'], $f['operator'], 'technical_operator', $f['president'], $f['secretary'], 'ROLEGAIN-OPERATOR');
        $this->cutoverMember($f['club'], $f['ca'], 'president-admin');
        $this->expectAuthorization(fn () => $resolver->appointment($f['package'], $f['ca'], 'independent_ca'));

        $this->cutoverMember($f['club'], $f['operator'], 'president-admin');
        $this->expectAuthorization(fn () => $resolver->appointment($f['package'], $f['operator'], 'technical_operator'));
        ClubMembership::query()->where('club_id', $f['club']->id)->where('user_id', $f['operator']->id)
            ->update(['role_id' => Role::where('slug', 'secretary')->firstOrFail()->id]);
        $this->expectAuthorization(fn () => $resolver->appointment($f['package'], $f['operator'], 'technical_operator'));
    }

    public function test_draft_appointment_requests_reject_secretary_and_self_governance_conflicts(): void
    {
        $f = $this->fixture();
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $this->assertNull($f['package']->internally_verified_by_user_id);
        $this->expectAuthorization(fn () => $admin->requestAppointment($f['package'], $f['secretary'], 'independent_ca', 'DRAFT-SECRETARY-CA', $f['secretary']));
        $this->expectAuthorization(fn () => $admin->requestAppointment($f['package'], $f['secretary'], 'technical_operator', 'DRAFT-SECRETARY-OPERATOR', $f['secretary']));
    }

    public function test_permission_operation_requires_distinct_president_and_secretary_and_is_replay_safe(): void
    {
        $f = $this->fixture();
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $op = $admin->requestPermission($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', 'PERMISSION-001', $f['secretary']);
        $this->assertSame('pending', $admin->approve($op, $f['president'], 'PERMISSION-001-P')->status);
        $this->assertDatabaseMissing('club_accounting_cutover_permission_assignments', ['cutover_package_id' => $f['package']->id, 'user_id' => $f['treasurer']->id]);
        $this->expectAuthorization(fn () => $admin->approve($op, $f['president'], 'PERMISSION-001-P2'));
        $applied = $admin->approve($op, $f['secretary'], 'PERMISSION-001-S');
        $this->assertSame('applied', $applied->status);
        $this->assertDatabaseHas('club_accounting_cutover_permission_assignments', ['cutover_package_id' => $f['package']->id, 'user_id' => $f['treasurer']->id]);
        $this->assertSame($applied->id, $admin->requestPermission($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', 'PERMISSION-001', $f['secretary'])->id);
        $this->expectAuthorization(fn () => $admin->requestPermission($f['package'], $f['treasurer'], 'finance.cutover.package.validate', 'PERMISSION-001', $f['secretary']));
    }

    public function test_secretary_alone_wrong_club_inactive_and_ambiguous_officers_fail_closed(): void
    {
        $f = $this->fixture();
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $op = $admin->requestPermission($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', 'OFFICERS-001', $f['secretary']);
        $this->assertSame('pending', $admin->approve($op, $f['secretary'], 'OFFICERS-001-S')->status);
        $other = $this->clubActor('secretary', 'Other', 'OTH');
        $this->expectAuthorization(fn () => $admin->approve($op, $other, 'OFFICERS-WRONG'));
        ClubMembership::where('user_id', $f['president']->id)->update(['status' => 'inactive']);
        $this->expectAuthorization(fn () => $admin->approve($op, $f['president'], 'OFFICERS-INACTIVE'));
        ClubMembership::where('user_id', $f['president']->id)->update(['status' => 'active']);
        $extra = User::factory()->create();
        $this->cutoverMember($f['club'], $extra, 'president-admin');
        $this->expectAuthorization(fn () => $admin->approve($op, $f['president'], 'OFFICERS-AMBIGUOUS'));
    }

    public function test_operation_approval_rejects_a_changed_package_lock_binding(): void
    {
        $f = $this->fixture();
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $operation = $admin->requestPermission($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', 'STALE-OPERATION', $f['secretary']);
        $f['package']->update(['lock_version' => $f['package']->lock_version + 1]);
        $this->expectAuthorization(fn () => $admin->approve($operation, $f['president'], 'STALE-OPERATION-P'));
        $this->assertDatabaseCount('club_accounting_cutover_admin_approvals', 0);
    }

    public function test_duplicate_current_permission_is_prevented_even_by_direct_database_insert(): void
    {
        $f = $this->fixture();
        $assignment = $this->dualAssign($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', $f['president'], $f['secretary'], 'DUP-001');
        $this->expectException(QueryException::class);
        DB::table('club_accounting_cutover_permission_assignments')->insert([...$assignment->getAttributes(), 'id' => null, 'public_id' => (string) Str::uuid(), 'assignment_reference' => 'DUP-DIRECT', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_ca_and_operator_appointments_enforce_separation_and_ca_eligibility_is_distinct(): void
    {
        $f = $this->fixture();
        $f['package']->update(['prepared_by_user_id' => $f['treasurer']->id, 'internally_verified_by_user_id' => $f['secretary']->id]);
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        foreach ([$f['president'], $f['treasurer'], $f['secretary']] as $index => $conflict) {
            $this->expectAuthorization(fn () => $admin->requestAppointment($f['package'], $conflict, 'independent_ca', 'CA-CONFLICT-'.$index, $f['secretary']));
        }
        $caAppointment = $this->dualAppoint($f['package'], $f['ca'], 'independent_ca', $f['president'], $f['secretary'], 'CA-VALID');
        $this->assertDatabaseCount('club_accounting_cutover_ca_eligibilities', 0);
        $op = $admin->requestCaEligibility($caAppointment, $this->eligibilityRefs(), '2027-07-01', 'confirmed', 'ELIGIBLE-001', $f['secretary']);
        $admin->approve($op, $f['president'], 'ELIGIBLE-001-P');
        $this->assertDatabaseCount('club_accounting_cutover_ca_eligibilities', 0);
        $admin->approve($op, $f['secretary'], 'ELIGIBLE-001-S');
        $this->assertDatabaseCount('club_accounting_cutover_ca_eligibilities', 1);
        $this->assertDatabaseCount('club_accounting_cutover_reviews', 0);
        $this->expectAuthorization(fn () => $admin->requestAppointment($f['package'], $f['ca'], 'technical_operator', 'OP-AS-CA', $f['secretary']));
        foreach ([$f['president'], $f['treasurer'], $f['secretary']] as $index => $conflict) {
            $this->expectAuthorization(fn () => $admin->requestAppointment($f['package'], $conflict, 'technical_operator', 'OP-CONFLICT-'.$index, $f['secretary']));
        }
    }

    public function test_sensitive_ca_metadata_is_rejected(): void
    {
        $f = $this->fixture();
        $appointment = $this->dualAppoint($f['package'], $f['ca'], 'independent_ca', $f['president'], $f['secretary'], 'CA-SAFE');
        $refs = $this->eligibilityRefs();
        $refs['identity'] = 'https://protected.example/passport';
        $this->expectAuthorization(fn () => app(ClubCutoverAuthorityAdministrationService::class)->requestCaEligibility($appointment, $refs, '2027-07-01', 'confirmed', 'ELIGIBLE-UNSAFE', $f['secretary']));
    }

    public function test_self_and_third_party_revocation_are_append_only_and_consumed_authority_fails_closed(): void
    {
        $f = $this->fixture();
        $assignment = $this->dualAssign($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', $f['president'], $f['secretary'], 'REVOKE-PERM');
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $this->expectAuthorization(fn () => $admin->selfRevokePermission($assignment, $f['secretary'], 'BAD-SELF', 'Not the holder.'));
        $admin->selfRevokePermission($assignment, $f['treasurer'], 'GOOD-SELF', 'Holder fail-safe.');
        $this->assertDatabaseHas('club_accounting_cutover_permission_assignments', ['id' => $assignment->id]);
        $appointment = $this->dualAppoint($f['package'], $f['operator'], 'technical_operator', $f['president'], $f['secretary'], 'REVOKE-OP');
        $op = $admin->requestAppointmentRevocation($appointment, 'REVOKE-THIRD', 'Governance withdrawal.', $f['secretary']);
        $admin->approve($op, $f['president'], 'REVOKE-THIRD-P');
        $this->assertDatabaseCount('club_accounting_cutover_authority_events', 0);
        $admin->approve($op, $f['secretary'], 'REVOKE-THIRD-S');
        $this->assertDatabaseCount('club_accounting_cutover_authority_events', 1);
    }

    public function test_package_authoring_and_validation_require_exact_permissions_and_submission_is_unverified(): void
    {
        $f = $this->fixture();
        $service = app(ClubAccountingCutoverPackageService::class);
        $this->expectPackage(fn () => $service->replaceEvidenceReferences($f['club']->id, $f['package']->id, $f['treasurer']->id, 1, $this->evidence('verified')));
        $this->dualAssign($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', $f['president'], $f['secretary'], 'AUTHOR-PREPARE');
        $package = $service->replaceEvidenceReferences($f['club']->id, $f['package']->id, $f['treasurer']->id, 1, $this->evidence('verified'));
        $this->assertSame(['unverified'], $package->evidenceReferences->pluck('verification_status')->unique()->values()->all());
        $package = $service->replaceEvidenceReferences($f['club']->id, $package->id, $f['treasurer']->id, $package->lock_version, $this->evidence('rejected'));
        $this->assertSame(['unverified'], $package->evidenceReferences->pluck('verification_status')->unique()->values()->all());
        $this->expectPackage(fn () => $service->refreshValidation($f['club']->id, $package->id, $f['treasurer']->id, $package->lock_version));
    }

    public function test_secretary_verification_is_authorized_bound_and_immutable(): void
    {
        $f = $this->fixture();
        $service = app(ClubAccountingCutoverPackageService::class);
        $this->dualAssign($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', $f['president'], $f['secretary'], 'VERIFY-PREPARE');
        $this->dualAssign($f['package'], $f['secretary'], 'finance.cutover.package.verify', $f['president'], $f['secretary'], 'VERIFY-PERM');
        $package = $service->replaceEvidenceReferences($f['club']->id, $f['package']->id, $f['treasurer']->id, 1, $this->evidence('verified'));
        $evidence = $package->evidenceReferences->first();
        $verifier = app(ClubCutoverEvidenceVerificationService::class);
        $this->expectAuthorization(fn () => $verifier->decide($package, $evidence, $f['treasurer'], 'verified', 'VERIFY-BAD', null, $package->lock_version));
        $record = $verifier->decide($package, $evidence, $f['secretary'], 'verified', 'VERIFY-GOOD', 'Metadata check only.', $package->lock_version);
        $this->assertSame('verified', $evidence->fresh()->verification_status);
        $this->assertDatabaseCount('club_accounting_cutover_ca_eligibilities', 0);
        try {
            DB::table('club_accounting_cutover_evidence_verifications')->where('id', $record->id)->update(['result' => 'rejected']);
            $this->fail('Verification provenance must be immutable.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        $replacement = $service->replaceEvidenceReferences($f['club']->id, $package->id, $f['treasurer']->id, $package->fresh()->lock_version, $this->evidence('verified'));
        $this->assertSame('unverified', $replacement->evidenceReferences->first()->verification_status);
        $this->assertDatabaseHas('club_accounting_cutover_evidence_verifications', ['id' => $record->id, 'evidence_reference_public_id' => $evidence->public_id, 'result' => 'verified']);
    }

    public function test_evidence_verification_requires_authoritative_provenance_and_replay_semantics_match_exactly(): void
    {
        $f = $this->fixture();
        $service = app(ClubAccountingCutoverPackageService::class);
        $this->dualAssign($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', $f['president'], $f['secretary'], 'PROVENANCE-PREPARE');
        $this->dualAssign($f['package'], $f['secretary'], 'finance.cutover.package.verify', $f['president'], $f['secretary'], 'PROVENANCE-VERIFY');
        $package = $service->replaceEvidenceReferences($f['club']->id, $f['package']->id, $f['treasurer']->id, 1, $this->evidence('verified'));
        $evidence = $package->evidenceReferences->first();
        DB::table('club_accounting_cutover_evidence_references')->where('id', $evidence->id)->update(['verification_status' => 'verified']);
        $this->assertFalse($service->validate($package->fresh())['checks']['evidence:cash_count']);

        DB::table('club_accounting_cutover_evidence_references')->where('id', $evidence->id)->update(['verification_status' => 'unverified']);
        $verifier = app(ClubCutoverEvidenceVerificationService::class);
        $record = $verifier->decide($package->fresh(), $evidence->fresh(), $f['secretary'], 'verified', 'REPLAY-VERIFY', 'Exact safe note.', $package->lock_version);
        $replay = $verifier->decide($package->fresh(), $evidence->fresh(), $f['secretary'], 'verified', 'REPLAY-VERIFY', 'Exact safe note.', $package->lock_version);
        $this->assertSame($record->id, $replay->id);
        $this->expectAuthorization(fn () => $verifier->decide($package->fresh(), $evidence->fresh(), $f['secretary'], 'verified', 'REPLAY-VERIFY', 'Changed safe note.', $package->lock_version));

        $other = $package->evidenceReferences()->create(['club_id' => $package->club_id, 'evidence_type' => 'bank_reconciliation', 'opaque_reference' => 'EVIDENCE-BANK-RETRY', 'evidence_date' => '2027-07-01', 'evidence_version' => 'V1', 'digest_sha256' => str_repeat('b', 64), 'verification_status' => 'unverified']);
        $this->expectAuthorization(fn () => $verifier->decide($package->fresh(), $other, $f['secretary'], 'verified', 'REPLAY-VERIFY', 'Exact safe note.', $package->lock_version));
    }

    public function test_stale_evidence_verification_fails_closed(): void
    {
        $f = $this->fixture();
        $service = app(ClubAccountingCutoverPackageService::class);
        $this->dualAssign($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', $f['president'], $f['secretary'], 'STALE-PREPARE');
        $this->dualAssign($f['package'], $f['secretary'], 'finance.cutover.package.verify', $f['president'], $f['secretary'], 'STALE-VERIFY');
        $package = $service->replaceEvidenceReferences($f['club']->id, $f['package']->id, $f['treasurer']->id, 1, $this->evidence('verified'));
        $this->expectAuthorization(fn () => app(ClubCutoverEvidenceVerificationService::class)->decide($package, $package->evidenceReferences->first(), $f['secretary'], 'verified', 'STALE-ACT', null, $package->lock_version - 1));
    }

    public function test_authority_reads_are_least_privilege_and_club_scoped(): void
    {
        $f = $this->fixture();
        $f['package']->update(['prepared_by_user_id' => $f['treasurer']->id]);
        $this->dualAssign($f['package'], $f['treasurer'], 'finance.cutover.package.prepare', $f['president'], $f['secretary'], 'READ-PREPARE');
        $ca = $this->dualAppoint($f['package'], $f['ca'], 'independent_ca', $f['president'], $f['secretary'], 'READ-CA');
        $this->dualAttestEligibility($ca, $f['president'], $f['secretary'], 'READ-CA-ELIGIBILITY');
        $operator = $this->dualAppoint($f['package'], $f['operator'], 'technical_operator', $f['president'], $f['secretary'], 'READ-OP');
        $read = app(ClubCutoverAuthorityReadService::class);
        $presidentHistory = $read->metadata($f['package'], $f['president']);
        $this->assertCount(2, $presidentHistory['appointments']);
        $this->assertCount(1, $presidentHistory['bootstrap_operations']);
        $this->assertNotEmpty($presidentHistory['operations']);
        $this->assertCount(1, $presidentHistory['ca_eligibilities']);
        $this->assertCount(2, $read->metadata($f['package'], $f['secretary'])['appointments']);
        $this->assertCount(0, $read->metadata($f['package'], $f['treasurer'])['appointments']);
        $this->assertSame([$ca->id], $read->metadata($f['package'], $f['ca'])['appointments']->pluck('id')->all());
        $this->assertSame([$operator->id], $read->metadata($f['package'], $f['operator'])['appointments']->pluck('id')->all());
        $member = User::factory()->create();
        $this->cutoverMember($f['club'], $member, 'member');
        $this->expectAuthorization(fn () => $read->metadata($f['package'], $member));
        $other = $this->clubActor('president-admin', 'Other Club', 'OTR');
        $this->expectAuthorization(fn () => $read->metadata($f['package'], $other));
    }

    private function fixture(): array
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $club = Club::create(['name' => 'Beenachi', 'code' => 'LCB']);
        foreach (['president', 'secretary', 'treasurer', 'creator', 'ca', 'operator'] as $key) {
            $$key = User::factory()->create();
        }
        $this->cutoverMember($club, $president, 'president-admin');
        $this->cutoverMember($club, $secretary, 'secretary');
        $this->cutoverMember($club, $treasurer, 'treasurer');
        $this->cutoverMember($club, $creator, 'treasurer');
        $package = $this->bootstrapCutoverPackage($club, $creator, $president, $secretary, 'FIXTURE-BOOTSTRAP');

        return compact('club', 'package', 'president', 'secretary', 'treasurer', 'ca', 'operator');
    }

    private function clubActor(string $role, string $name, string $code): User
    {
        $club = Club::create(['name' => $name, 'code' => $code]);
        $user = User::factory()->create();
        $this->cutoverMember($club, $user, $role);

        return $user;
    }

    private function evidence(string $status): array
    {
        return [['evidence_type' => 'cash_count', 'opaque_reference' => 'EVIDENCE-CASH-001', 'evidence_date' => '2027-07-01', 'evidence_version' => 'V1', 'digest_sha256' => str_repeat('a', 64), 'verification_status' => $status]];
    }

    private function eligibilityRefs(): array
    {
        return ['professional_qualification' => 'QUALIFICATION-001', 'identity' => 'IDENTITY-OPAQUE-001', 'professional_standing' => 'STANDING-OPAQUE-001', 'engagement' => 'ENGAGEMENT-OPAQUE-001', 'independence' => 'INDEPENDENCE-OPAQUE-001'];
    }

    private function expectAuthorization(callable $call): void
    {
        try {
            $call();
            $this->fail('Expected authorization failure.');
        } catch (CutoverAuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function expectPackage(callable $call): void
    {
        try {
            $call();
            $this->fail('Expected package failure.');
        } catch (CutoverPackageException) {
            $this->addToAssertionCount(1);
        }
    }
}
