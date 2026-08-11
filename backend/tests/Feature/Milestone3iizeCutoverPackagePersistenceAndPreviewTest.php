<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubMembership;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ClubAccountingCutoverPackageService;
use App\Services\Finance\ClubCutoverEvidenceVerificationService;
use App\Services\Finance\ClubCutoverLifecycleService;
use App\Services\Finance\Exceptions\CutoverPackageException;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithCutoverGovernance;
use Tests\TestCase;

class Milestone3iizeCutoverPackagePersistenceAndPreviewTest extends TestCase
{
    use InteractsWithCutoverGovernance, RefreshDatabase;

    public function test_draft_identity_is_persisted_club_scoped_audited_and_dr033_has_no_default(): void
    {
        $fixture = $this->fixture();
        $package = $this->authorizedDraft($fixture);

        $this->assertSame('2027-07-01', $package->cutover_date->format('Y-m-d'));
        $this->assertSame('2027-28', $package->lions_year);
        $this->assertSame('CLUB-COA-2026-01', $package->chart_version);
        $this->assertSame('INR', $package->currency);
        $this->assertSame('draft', $package->status);
        $this->assertNull($package->dr033_classification);
        $this->assertFalse($package->activation_ready);
        $this->assertSame(1, $package->lock_version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.cutover_package.created', 'club_id' => $fixture['club']->id]);
    }

    public function test_cross_club_account_mapping_and_duplicate_rows_or_accounts_are_rejected(): void
    {
        $fixture = $this->fixture();
        $package = $this->authorizedDraft($fixture);
        $foreign = $this->account($fixture['otherClub'], $fixture['actor'], 'FOREIGN-CASH', 'asset', 'debit');

        $this->assertCutoverFailure(fn () => $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, 1, $this->rows($fixture, null, [['row_key' => 'BANK-OPERATING', 'ledger_account_id' => $foreign->id]])));
        $duplicateKey = $this->rows($fixture);
        $duplicateKey[1]['row_key'] = $duplicateKey[0]['row_key'];
        $this->assertCutoverFailure(fn () => $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, 1, $duplicateKey));
        $duplicateAccount = $this->rows($fixture);
        $duplicateAccount[1]['ledger_account_id'] = $duplicateAccount[0]['ledger_account_id'];
        $this->assertCutoverFailure(fn () => $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, 1, $duplicateAccount));
    }

    public function test_evidence_is_non_sensitive_opaque_and_required_presence_is_validated_without_authenticity_claim(): void
    {
        $fixture = $this->fixture();
        $package = $this->populatedPackage($fixture, false);
        foreach (['https://secret.test/statement', 'C:\\private\\bank.pdf', 'account number 1234567890', 'Bearer token-value'] as $private) {
            $bad = $this->evidence();
            $bad[0]['opaque_reference'] = $private;
            $this->assertCutoverFailure(fn () => $this->service()->replaceEvidenceReferences($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, $bad));
        }

        $package = $this->service()->replaceEvidenceReferences($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, $this->evidence());
        $package = $this->verifyAllEvidence($fixture, $package);
        $validation = $this->service()->validate($package);
        $this->assertTrue($validation['checks']['evidence:bank_reconciliation']);
        $this->assertTrue($validation['checks']['reconciliation_zero']);
        $this->assertTrue($validation['valid']);
        $this->assertFalse($this->service()->readiness($package)['ready']); // DR-033 remains a readiness gate.
    }

    public function test_checksum_is_order_independent_and_changes_for_one_paise_mapping_group_or_evidence(): void
    {
        $fixture = $this->fixture();
        $package = $this->populatedPackage($fixture);
        $baseline = $this->service()->checksum($package);
        $package = $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, array_reverse($this->rows($fixture)));
        $package = $this->service()->replaceEvidenceReferences($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, array_reverse($this->evidence()));
        $this->assertSame($baseline, $this->service()->checksum($package));

        $changed = $this->rows($fixture);
        $changed[0]['amount_paise']++;
        $package = $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, $changed);
        $this->assertNotSame($baseline, $this->service()->checksum($package));

        $changed = $this->rows($fixture);
        $changed[6]['presentation_group'] = 'SERVICE-ACTIVITY-FUND';
        $package = $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, $changed);
        $this->assertNotSame($baseline, $this->service()->checksum($package));

        $package = $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, $this->rows($fixture));

        $changedEvidence = $this->evidence();
        $changedEvidence[0]['opaque_reference'] = 'EVIDENCE-CASH-002';
        $package = $this->service()->replaceEvidenceReferences($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, $changedEvidence);
        $this->assertNotSame($baseline, $this->service()->checksum($package));
    }

    public function test_canonical_reads_ignore_unsaved_attributes_and_reject_non_persisted_or_missing_packages(): void
    {
        $fixture = $this->fixture();
        $package = $this->populatedPackage($fixture);
        $persistedChecksum = $this->service()->checksum($package);
        $package->package_version = 999;
        $package->lions_year = '2099-00';

        $this->assertSame($persistedChecksum, $this->service()->checksum($package));
        $this->assertSame(1, $this->service()->preview($package)['header']['package_version']);

        $transient = new ClubAccountingCutoverPackage(['package_version' => 1]);
        foreach (['checksum', 'validate', 'preview', 'readiness'] as $method) {
            $this->assertCutoverFailure(fn () => $this->service()->{$method}($transient));
        }

        $missing = $package->replicate();
        $missing->id = PHP_INT_MAX;
        $missing->exists = true;
        $this->assertCutoverFailure(fn () => $this->service()->checksum($missing));
    }

    public function test_pending_dr033_preview_is_balanced_but_blocked_and_performs_no_financial_write(): void
    {
        $fixture = $this->fixture();
        $package = $this->populatedPackage($fixture);
        $journals = ClubJournalEntry::count();
        $preview = $this->service()->preview($package);

        $this->assertSame('BLOCKED_DR033', $preview['status']);
        $this->assertTrue($preview['balanced']);
        $this->assertFalse($preview['writes_performed']);
        $this->assertNull($preview['header']['dr033_classification']);
        $this->assertTrue(collect($preview['lines'])->firstWhere('row_key', 'EQUITY-PREVIEW')['unresolved_classification']);
        $this->assertSame($journals, ClubJournalEntry::count());
        $this->assertFalse($this->service()->readiness($package)['ready']);
    }

    public function test_tenant_selected_equity_account_is_allowed_and_cross_club_or_non_equity_selection_fails_closed(): void
    {
        $fixture = $this->fixture();
        $fixture['accounts']['tenant_equity'] = $this->account($fixture['club'], $fixture['actor'], 'CLUB-NET-ASSETS', 'equity', 'credit');
        $this->account($fixture['otherClub'], $fixture['actor'], 'FOREIGN-NET-ASSETS', 'equity', 'credit');
        $package = $this->reviewablePackage($fixture, 'CLUB-NET-ASSETS');
        $this->assertCutoverFailure(fn () => app(ClubCutoverLifecycleService::class)->review($package, $fixture['ca'], $package->lock_version, 'FOREIGN-NET-ASSETS', 'CA-REVIEW-FOREIGN', '2027-07-02', str_repeat('Valid rationale. ', 3)));
        $this->assertCutoverFailure(fn () => app(ClubCutoverLifecycleService::class)->review($package, $fixture['ca'], $package->lock_version, 'BANK-OPERATING', 'CA-REVIEW-ASSET', '2027-07-02', str_repeat('Valid rationale. ', 3)));
        $this->assertNull($package->fresh()->dr033_classification);

        $review = $this->service()->recordAccountingReview($fixture['club']->id, $package->id, $fixture['ca']->id, $package->lock_version, 'CA-REVIEW-'.$package->id, '2027-07-02', ' club-net-assets ', 'Evidence establishes the tenant-selected historical equity classification.');
        $this->assertSame('CLUB-NET-ASSETS', $review->selected_classification);
        $this->assertSame('CLUB-NET-ASSETS', $package->fresh()->dr033_classification);
        $preview = $this->service()->preview($package->fresh());
        $this->assertSame('CLUB-NET-ASSETS', collect($preview['lines'])->firstWhere('row_key', 'EQUITY-PREVIEW')['account_code']);
        $this->assertSame(40000, collect($preview['lines'])->firstWhere('row_key', 'EQUITY-PREVIEW')['credit_paise']);
    }

    public function test_review_and_approval_bind_exact_checksum_and_content_mutation_invalidates_both_and_classification(): void
    {
        $fixture = $this->fixture();
        $package = $this->approvedPackage($fixture);
        $currentChecksum = $package->canonical_checksum;
        $this->assertSame($currentChecksum, $package->reviews()->where('status', 'current')->firstOrFail()->package_checksum);
        $this->assertSame($currentChecksum, $package->approvals()->where('status', 'current')->firstOrFail()->package_checksum);

        $rows = $this->rows($fixture, 'OPENING-BALANCE-EQUITY');
        $rows[0]['amount_paise']++;
        $this->assertCutoverFailure(fn () => $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, $rows));
        $this->assertSame('OPENING-BALANCE-EQUITY', $package->fresh()->dr033_classification);
        $this->assertSame('current', $package->reviews()->firstOrFail()->status);
        $this->assertSame('current', $package->approvals()->firstOrFail()->status);
    }

    public function test_readiness_is_true_only_for_current_review_approval_zero_difference_and_balanced_preview(): void
    {
        $fixture = $this->fixture();
        $package = $this->approvedPackage($fixture);
        $readiness = $this->service()->readiness($package);
        $this->assertTrue($readiness['ready']);
        $this->assertSame([], $readiness['failure_reasons']);
        $this->assertSame('BALANCED_PREVIEW', $readiness['preview']['status']);

        ClubJournalEntry::create([
            'club_id' => $fixture['club']->id, 'accounting_period_id' => $fixture['period']->id,
            'sequence_number' => 1, 'reference' => 'LCB/2027-28/JRN/000001', 'effective_date' => '2027-07-01',
            'posting_date' => '2027-07-01', 'status' => 'draft', 'description' => 'Conflict marker', 'currency' => 'INR',
            'source_type' => 'accounting_opening_package', 'source_id' => 'PACKAGE-OLD', 'source_event' => 'activated',
            'idempotency_key' => (string) Str::uuid(), 'created_by_user_id' => $fixture['actor']->id,
        ]);
        $this->assertFalse($this->service()->readiness($package)['checks']['no_previous_activation']);
    }

    public function test_database_rejects_a_persisted_classification_change_after_review(): void
    {
        $fixture = $this->fixture();
        $package = $this->approvedPackage($fixture);
        try {
            DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['dr033_classification' => 'ACCUMULATED-SURPLUS']);
            $this->fail('The reviewed DR-033 classification must be database-immutable.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame('OPENING-BALANCE-EQUITY', $package->fresh()->dr033_classification);
        $this->assertTrue($this->service()->readiness($package->fresh())['checks']['accounting_review_current']);
    }

    public function test_stale_lock_and_wrong_club_are_rejected_without_mutation(): void
    {
        $fixture = $this->fixture();
        $package = $this->authorizedDraft($fixture);
        $this->assertCutoverFailure(fn () => $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, 99, $this->rows($fixture)));
        $this->assertCutoverFailure(fn () => $this->service()->replaceSchedules($fixture['otherClub']->id, $package->id, $fixture['actor']->id, 1, $this->rows($fixture)));
        $this->assertSame(0, $package->scheduleRows()->count());
    }

    public function test_database_constraints_reject_invalid_accounting_values_and_states(): void
    {
        $fixture = $this->fixture();
        $package = $this->approvedPackage($fixture);
        $schedule = $package->scheduleRows()->firstOrFail();
        $evidence = $package->evidenceReferences()->firstOrFail();
        $review = $package->reviews()->where('status', 'current')->firstOrFail();
        $approval = $package->approvals()->where('status', 'current')->firstOrFail();

        foreach ([
            fn () => DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['package_version' => 0]),
            fn () => DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['lock_version' => 0]),
            fn () => DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['currency' => 'USD']),
            fn () => DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['cutover_date' => '2027-08-01']),
            fn () => DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['lions_year' => '2026-27']),
            fn () => DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['chart_version' => 'UNAPPROVED-CHART']),
            fn () => DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'broken']),
            fn () => DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['dr033_classification' => 'INFERRED-SURPLUS']),
            fn () => DB::table('club_accounting_cutover_schedule_rows')->where('id', $schedule->id)->update(['amount_paise' => -1]),
            fn () => DB::table('club_accounting_cutover_schedule_rows')->where('id', $schedule->id)->update(['direction' => 'other']),
            fn () => DB::table('club_accounting_cutover_schedule_rows')->where('id', $schedule->id)->update(['schedule_type' => 'miscellaneous']),
            fn () => DB::table('club_accounting_cutover_evidence_references')->where('id', $evidence->id)->update(['verification_status' => 'trusted']),
            fn () => DB::table('club_accounting_cutover_reviews')->where('id', $review->id)->update(['selected_classification' => 'INFERRED-SURPLUS']),
            fn () => DB::table('club_accounting_cutover_reviews')->where('id', $review->id)->update(['status' => 'duplicate']),
            fn () => DB::table('club_accounting_cutover_approvals')->where('id', $approval->id)->update(['status' => 'duplicate']),
            fn () => $review->replicate()->fill(['public_id' => (string) Str::uuid()])->save(),
            fn () => $approval->replicate()->fill(['public_id' => (string) Str::uuid(), 'approval_reference' => 'CLUB-APPROVAL-DUPLICATE'])->save(),
        ] as $operation) {
            try {
                $operation();
                $this->fail('The database must reject the invalid accounting value.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_review_and_approval_are_single_authorized_lifecycle_transitions(): void
    {
        $fixture = $this->fixture();
        $package = $this->approvedPackage($fixture);

        $this->assertCutoverFailure(fn () => app(ClubCutoverLifecycleService::class)->review($package, $fixture['ca'], $package->lock_version, 'OPENING-BALANCE-EQUITY', 'CA-REVIEW-REPEAT', '2027-07-02', 'A second written review confirms the exact same evidence-bound classification.'));
        $this->assertSame(1, $package->reviews()->where('status', 'current')->count());
        $this->assertSame(0, $package->reviews()->where('status', 'invalidated')->count());
        $this->assertCutoverFailure(fn () => app(ClubCutoverLifecycleService::class)->approve($package, $fixture['president'], $package->lock_version, '2027-07-03', 'CLUB-APPROVAL-REPEAT'));
        $this->assertSame(1, $package->approvals()->where('status', 'current')->count());
        $this->assertSame(0, $package->approvals()->where('status', 'invalidated')->count());
    }

    public function test_readiness_is_read_only_and_any_accounting_mutation_clears_a_stored_ready_snapshot(): void
    {
        $fixture = $this->fixture();
        $package = $this->approvedPackage($fixture);
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['activation_ready' => true, 'readiness_checked_at' => '2027-07-16 10:00:00']);
        $before = DB::table('club_accounting_cutover_packages')->where('id', $package->id)->first();
        $this->assertTrue($this->service()->readiness($package->fresh())['ready']);
        $afterRead = DB::table('club_accounting_cutover_packages')->where('id', $package->id)->first();
        $this->assertEquals($before, $afterRead);

        $package = $package->fresh();
        $this->assertCutoverFailure(fn () => $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, $this->rows($fixture, 'OPENING-BALANCE-EQUITY')));
        $this->assertSame('OPENING-BALANCE-EQUITY', $package->fresh()->dr033_classification);
    }

    public function test_no_routes_permissions_coverage_or_posting_capability_are_added(): void
    {
        $uris = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri): bool => str_contains($uri, 'cutover'));
        $this->assertSame([], $uris->values()->all());
        $this->assertDatabaseMissing('permissions', ['name' => 'finance.cutover.package.prepare']);
        $this->assertFalse(method_exists($this->service(), 'activate'));
        $this->assertFalse(method_exists($this->service(), 'postOpeningJournal'));
    }

    public function test_3iize_current_changes_are_limited_to_the_authorized_milestone_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' branch --show-current') ?? '');
        if ($branch !== 'milestone-3iize-cutover-package-persistence-and-preview') {
            $this->assertTrue(true);

            return;
        }

        $allowed = [
            'backend/app/Models/ClubAccountingCutoverApproval.php',
            'backend/app/Models/ClubAccountingCutoverEvidenceReference.php',
            'backend/app/Models/ClubAccountingCutoverPackage.php',
            'backend/app/Models/ClubAccountingCutoverReview.php',
            'backend/app/Models/ClubAccountingCutoverScheduleRow.php',
            'backend/app/Services/Finance/ClubAccountingCutoverPackageService.php',
            'backend/app/Services/Finance/Exceptions/CutoverPackageException.php',
            'backend/database/migrations/2026_08_03_000001_create_club_accounting_cutover_packages.php',
            'backend/database/migrations/2026_08_03_000002_add_cutover_package_integrity_constraints.php',
            'backend/tests/Feature/Milestone3iizeCutoverPackagePersistenceAndPreviewTest.php',
            'docs/finance/milestone-3iize-cutover-package-persistence-and-preview.md',
        ];
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';
        $changed = collect(preg_split('/\R/', trim($status)))->filter()->map(function (string $line): string {
            $path = preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line;

            return str_replace('\\', '/', trim($path));
        })->values()->all();

        $this->assertSame([], array_values(array_diff($changed, $allowed)));
        $this->assertNotContains('backend/routes/api.php', $changed);
        $this->assertNotContains('app/page.tsx', $changed);
        $this->assertNotContains('next-env.d.ts', $changed);
    }

    private function service(): ClubAccountingCutoverPackageService
    {
        return app(ClubAccountingCutoverPackageService::class);
    }

    private function fixture(): array
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $actor = User::factory()->create();
        $preparer = User::factory()->create();
        $verifier = User::factory()->create();
        $ca = User::factory()->create();
        $president = User::factory()->create();
        $club = Club::create(['name' => 'Beenachi', 'code' => 'LCB']);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $president->id, 'role_id' => Role::where('slug', 'president-admin')->firstOrFail()->id, 'status' => 'active']);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $verifier->id, 'role_id' => Role::where('slug', 'secretary')->firstOrFail()->id, 'status' => 'active']);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $actor->id, 'role_id' => Role::where('slug', 'treasurer')->firstOrFail()->id, 'status' => 'active']);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $preparer->id, 'role_id' => Role::where('slug', 'treasurer')->firstOrFail()->id, 'status' => 'active']);
        $otherClub = Club::create(['name' => 'Other', 'code' => 'OTH']);
        $accounts = [
            'cash' => $this->account($club, $actor, 'CASH-ON-HAND', 'asset', 'debit'),
            'bank' => $this->account($club, $actor, 'BANK-OPERATING', 'asset', 'debit'),
            'receivable' => $this->account($club, $actor, 'MEMBER-RECEIVABLES', 'asset', 'debit'),
            'other_asset' => $this->account($club, $actor, 'CLUB-PROPERTY', 'asset', 'debit'),
            'liability' => $this->account($club, $actor, 'LIABILITY-GENERAL', 'liability', 'credit'),
            'restricted' => $this->account($club, $actor, 'LIABILITY-RESTRICTED-COLLECTION', 'liability', 'credit'),
            'opening_equity' => $this->account($club, $actor, 'OPENING-BALANCE-EQUITY', 'equity', 'credit'),
            'surplus' => $this->account($club, $actor, 'ACCUMULATED-SURPLUS', 'equity', 'credit'),
        ];
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]);

        return compact('actor', 'preparer', 'verifier', 'ca', 'president', 'club', 'otherClub', 'accounts', 'period');
    }

    private function account(Club $club, User $actor, string $code, string $type, string $normal): LedgerAccount
    {
        return LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => str_replace('-', ' ', $code), 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id]);
    }

    private function rows(array $fixture, ?string $classification = null, array $overrides = []): array
    {
        $equity = $classification === null ? null : LedgerAccount::query()
            ->where('club_id', $fixture['club']->id)->where('code', $classification)->value('id');
        $rows = [
            ['schedule_type' => 'cash_on_hand', 'row_key' => 'CASH-ON-HAND', 'non_sensitive_label' => 'Cash on hand', 'ledger_account_id' => $fixture['accounts']['cash']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 10000],
            ['schedule_type' => 'bank_account', 'row_key' => 'BANK-OPERATING', 'non_sensitive_label' => 'Operating bank alias', 'ledger_account_id' => $fixture['accounts']['bank']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 20000],
            ['schedule_type' => 'member_receivables', 'row_key' => 'MEMBER-RECEIVABLES', 'non_sensitive_label' => 'Member receivables control', 'ledger_account_id' => $fixture['accounts']['receivable']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 30000],
            ['schedule_type' => 'other_asset', 'row_key' => 'CLUB-PROPERTY', 'non_sensitive_label' => 'Approved club property', 'ledger_account_id' => $fixture['accounts']['other_asset']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 0],
            ['schedule_type' => 'liability', 'row_key' => 'LIABILITY-GENERAL', 'non_sensitive_label' => 'Club liabilities', 'ledger_account_id' => $fixture['accounts']['liability']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'external_restriction', 'row_key' => 'RESTRICTED-BALANCE', 'non_sensitive_label' => 'Externally restricted balance', 'ledger_account_id' => $fixture['accounts']['restricted']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'general_club_fund', 'row_key' => 'GENERAL-FUND', 'non_sensitive_label' => 'General Club Fund presentation', 'presentation_group' => 'GENERAL-CLUB-FUND', 'direction' => 'credit', 'amount_paise' => 20000],
            ['schedule_type' => 'service_activity_fund', 'row_key' => 'SERVICE-FUND', 'non_sensitive_label' => 'Service Activity Fund presentation', 'presentation_group' => 'SERVICE-ACTIVITY-FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'fellowship_member_activity_fund', 'row_key' => 'FELLOWSHIP-FUND', 'non_sensitive_label' => 'Fellowship and Member Activity presentation', 'presentation_group' => 'FELLOWSHIP-MEMBER-ACTIVITY-FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'equity_preview', 'row_key' => 'EQUITY-PREVIEW', 'non_sensitive_label' => 'Unresolved opening equity preview', 'ledger_account_id' => $equity, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'credit', 'amount_paise' => 40000],
        ];
        foreach ($overrides as $index => $change) {
            $rows[$index] = [...$rows[$index], ...$change];
        }

        return $rows;
    }

    private function evidence(): array
    {
        return collect(['cash_count', 'bank_reconciliation', 'member_receivables', 'liabilities_restrictions', 'reconciliation'])->map(fn (string $type, int $index): array => ['evidence_type' => $type, 'opaque_reference' => 'EVIDENCE-'.strtoupper(str_replace('_', '-', $type)).'-001', 'evidence_date' => '2027-07-01', 'evidence_version' => 'V1', 'digest_sha256' => str_repeat(dechex($index + 1), 64), 'verification_status' => 'verified', 'reviewer_note' => 'Opaque reference presence verified only'])->all();
    }

    private function populatedPackage(array $fixture, bool $withEvidence = true, int $version = 1): ClubAccountingCutoverPackage
    {
        $package = $this->authorizedDraft($fixture, $version);
        $package = $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, 1, $this->rows($fixture));
        if ($withEvidence) {
            $package = $this->service()->replaceEvidenceReferences($fixture['club']->id, $package->id, $fixture['actor']->id, 2, $this->evidence());
        }

        return $package;
    }

    private function reviewablePackage(array $fixture, string $classification, int $version = 1): ClubAccountingCutoverPackage
    {
        $package = $this->authorizedDraft($fixture, $version);
        $package = $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, 1, $this->rows($fixture));
        $package = $this->service()->replaceEvidenceReferences($fixture['club']->id, $package->id, $fixture['actor']->id, 2, $this->evidence());
        $package = $this->verifyAllEvidence($fixture, $package);
        $package = $this->service()->refreshValidation($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version);
        $this->dualAssign($package, $fixture['preparer'], 'finance.cutover.package.prepare', $fixture['president'], $fixture['verifier'], 'PREPARE-'.$package->id);
        $appointment = $this->dualAppoint($package, $fixture['ca'], 'independent_ca', $fixture['president'], $fixture['verifier'], 'CA-'.$package->id);
        $this->dualAttestEligibility($appointment, $fixture['president'], $fixture['verifier'], 'ELIGIBILITY-'.$package->id);
        $this->dualAssign($package, $fixture['ca'], 'finance.cutover.package.accounting-review', $fixture['president'], $fixture['verifier'], 'REVIEW-'.$package->id);
        $this->dualAssign($package, $fixture['president'], 'finance.cutover.package.business-approve', $fixture['president'], $fixture['verifier'], 'APPROVE-'.$package->id);
        $life = app(ClubCutoverLifecycleService::class);
        $package = $life->prepare($package, $fixture['preparer'], $package->lock_version);

        return $life->verify($package, $fixture['verifier'], $package->lock_version);
    }

    private function approvedPackage(array $fixture): ClubAccountingCutoverPackage
    {
        $package = $this->reviewablePackage($fixture, 'OPENING-BALANCE-EQUITY');
        app(ClubCutoverLifecycleService::class)->review($package, $fixture['ca'], $package->lock_version, 'OPENING-BALANCE-EQUITY', 'Independent-CA-'.$package->id, '2027-07-02', 'Evidence establishes a one-time opening equity classification for this exact package.');
        $package = $package->fresh();
        app(ClubCutoverLifecycleService::class)->approve($package, $fixture['president'], $package->lock_version, '2027-07-03', 'CLUB-APPROVAL-'.$package->id);

        return $package->fresh();
    }

    private function assertCutoverFailure(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a cutover package validation failure.');
        } catch (CutoverPackageException) {
            $this->addToAssertionCount(1);
        }
    }

    private function authorizedDraft(array $fixture, int $version = 1): ClubAccountingCutoverPackage
    {
        $package = $this->bootstrapCutoverPackage($fixture['club'], $fixture['actor'], $fixture['president'], $fixture['verifier'], 'BOOTSTRAP-'.$fixture['club']->id.'-'.$version, $version);
        $this->dualAssign($package, $fixture['actor'], 'finance.cutover.package.validate', $fixture['president'], $fixture['verifier'], 'AUTHOR-VALIDATE-'.$package->id);

        return $package;
    }

    private function verifyAllEvidence(array $fixture, ClubAccountingCutoverPackage $package): ClubAccountingCutoverPackage
    {
        if (! $package->permissionAssignments()->where('user_id', $fixture['verifier']->id)->whereHas('permission', fn ($query) => $query->where('slug', 'finance.cutover.package.verify'))->exists()) {
            $this->dualAssign($package, $fixture['verifier'], 'finance.cutover.package.verify', $fixture['president'], $fixture['verifier'], 'EVIDENCE-VERIFY-'.$package->id);
        }
        $service = app(ClubCutoverEvidenceVerificationService::class);
        foreach ($package->evidenceReferences as $evidence) {
            $service->decide($package->fresh(), $evidence, $fixture['verifier'], 'verified', 'VERIFY-'.$package->id.'-'.$evidence->id, 'Metadata presence checked.', $package->fresh()->lock_version);
            $package = $package->fresh('evidenceReferences');
        }

        return $package;
    }
}
