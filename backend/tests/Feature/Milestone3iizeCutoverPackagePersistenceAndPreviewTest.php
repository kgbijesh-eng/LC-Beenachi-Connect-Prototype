<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\ClubAccountingCutoverPackageService;
use App\Services\Finance\Exceptions\CutoverPackageException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone3iizeCutoverPackagePersistenceAndPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_identity_is_persisted_club_scoped_audited_and_dr033_has_no_default(): void
    {
        $fixture = $this->fixture();
        $package = $this->service()->createDraft($fixture['club']->id, $fixture['actor']->id);

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
        $package = $this->service()->createDraft($fixture['club']->id, $fixture['actor']->id);
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
        $validation = $this->service()->validate($package);
        $this->assertTrue($validation['checks']['evidence:bank_reconciliation']);
        $this->assertTrue($validation['checks']['reconciliation_zero']);
        $this->assertTrue($validation['valid']);
        $this->assertFalse($this->service()->readiness($package)['ready']); // DR-033 remains a readiness gate.
    }

    public function test_checksum_is_order_independent_and_changes_for_one_paise_mapping_group_or_evidence(): void
    {
        $fixture = $this->fixture();
        $first = $this->populatedPackage($fixture);
        $second = $this->service()->createDraft($fixture['club']->id, $fixture['actor']->id, 2);
        $second = $this->service()->replaceSchedules($fixture['club']->id, $second->id, $fixture['actor']->id, 1, array_reverse($this->rows($fixture)));
        $second = $this->service()->replaceEvidenceReferences($fixture['club']->id, $second->id, $fixture['actor']->id, 2, array_reverse($this->evidence()));

        // Package version is semantically relevant, so normalize it only for this equality assertion.
        $second->package_version = 1;
        $this->assertSame($this->service()->checksum($first), $this->service()->checksum($second));

        $changed = $this->rows($fixture);
        $changed[0]['amount_paise']++;
        $first = $this->service()->replaceSchedules($fixture['club']->id, $first->id, $fixture['actor']->id, $first->lock_version, $changed);
        $this->assertNotSame($this->service()->checksum($second), $this->service()->checksum($first));

        $changed = $this->rows($fixture);
        $changed[6]['presentation_group'] = 'SERVICE-ACTIVITY-FUND';
        $first = $this->service()->replaceSchedules($fixture['club']->id, $first->id, $fixture['actor']->id, $first->lock_version, $changed);
        $this->assertNotSame($this->service()->checksum($second), $this->service()->checksum($first));

        $changedEvidence = $this->evidence();
        $changedEvidence[0]['opaque_reference'] = 'EVIDENCE-CASH-002';
        $first = $this->service()->replaceEvidenceReferences($fixture['club']->id, $first->id, $fixture['actor']->id, $first->lock_version, $changedEvidence);
        $this->assertNotSame($this->service()->checksum($second), $this->service()->checksum($first));
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

    public function test_only_two_classifications_are_allowed_and_nothing_is_inferred_from_net_assets(): void
    {
        $fixture = $this->fixture();
        $package = $this->reviewablePackage($fixture, 'OPENING-BALANCE-EQUITY');
        $this->assertCutoverFailure(fn () => $this->service()->recordAccountingReview($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, 'CA Reviewer', '2027-07-10', 'NET-ASSETS-SURPLUS', str_repeat('Valid rationale. ', 3)));
        $this->assertNull($package->fresh()->dr033_classification);

        foreach (['OPENING-BALANCE-EQUITY', 'ACCUMULATED-SURPLUS'] as $classification) {
            $candidate = $classification === 'OPENING-BALANCE-EQUITY' ? $package : $this->reviewablePackage($fixture, $classification, 2);
            $review = $this->service()->recordAccountingReview($fixture['club']->id, $candidate->id, $fixture['actor']->id, $candidate->lock_version, 'CA Reviewer', '2027-07-10', $classification, 'Evidence establishes the selected historical equity classification.');
            $this->assertSame($classification, $review->selected_classification);
            $this->assertSame($classification, $candidate->fresh()->dr033_classification);
        }
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
        $package = $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, $rows);
        $this->assertNull($package->dr033_classification);
        $this->assertNull($package->canonical_checksum);
        $this->assertFalse($package->activation_ready);
        $this->assertSame('invalidated', $package->reviews()->firstOrFail()->status);
        $this->assertSame('invalidated', $package->approvals()->firstOrFail()->status);
        $this->assertNotNull($package->reviews()->firstOrFail()->invalidated_at);
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

    public function test_stale_lock_and_wrong_club_are_rejected_without_mutation(): void
    {
        $fixture = $this->fixture();
        $package = $this->service()->createDraft($fixture['club']->id, $fixture['actor']->id);
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

    public function test_repeated_review_and_approval_leave_exactly_one_current_binding(): void
    {
        $fixture = $this->fixture();
        $package = $this->approvedPackage($fixture);

        $this->service()->recordAccountingReview($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, 'Second independent CA reviewer', '2027-07-13', 'OPENING-BALANCE-EQUITY', 'A second written review confirms the exact same evidence-bound classification.');
        $package = $package->fresh();
        $this->assertSame(1, $package->reviews()->where('status', 'current')->count());
        $this->assertSame(1, $package->reviews()->where('status', 'invalidated')->count());
        $this->assertSame(0, $package->approvals()->where('status', 'current')->count());

        $this->service()->recordClubApproval($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, 'President and authorised club body', '2027-07-14', 'CLUB-APPROVAL-2027-002');
        $package = $package->fresh();
        $this->service()->recordClubApproval($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, 'President and authorised club body', '2027-07-15', 'CLUB-APPROVAL-2027-003');
        $this->assertSame(1, $package->approvals()->where('status', 'current')->count());
        $this->assertSame(2, $package->approvals()->where('status', 'invalidated')->count());
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
        $package = $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, $this->rows($fixture, 'OPENING-BALANCE-EQUITY'));
        $this->assertFalse($package->activation_ready);
        $this->assertNull($package->readiness_checked_at);
        $this->assertNull($package->canonical_checksum);
        $this->assertNull($package->dr033_classification);
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
        $actor = User::factory()->create();
        $club = Club::create(['name' => 'Beenachi', 'code' => 'LCB']);
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

        return compact('actor', 'club', 'otherClub', 'accounts', 'period');
    }

    private function account(Club $club, User $actor, string $code, string $type, string $normal): LedgerAccount
    {
        return LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => str_replace('-', ' ', $code), 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id]);
    }

    private function rows(array $fixture, ?string $classification = null, array $overrides = []): array
    {
        $equity = $classification === 'ACCUMULATED-SURPLUS' ? $fixture['accounts']['surplus']->id : ($classification === 'OPENING-BALANCE-EQUITY' ? $fixture['accounts']['opening_equity']->id : null);
        $rows = [
            ['schedule_type' => 'cash_on_hand', 'row_key' => 'CASH-ON-HAND', 'non_sensitive_label' => 'Cash on hand', 'ledger_account_id' => $fixture['accounts']['cash']->id, 'direction' => 'debit', 'amount_paise' => 10000],
            ['schedule_type' => 'bank_account', 'row_key' => 'BANK-OPERATING', 'non_sensitive_label' => 'Operating bank alias', 'ledger_account_id' => $fixture['accounts']['bank']->id, 'direction' => 'debit', 'amount_paise' => 20000],
            ['schedule_type' => 'member_receivables', 'row_key' => 'MEMBER-RECEIVABLES', 'non_sensitive_label' => 'Member receivables control', 'ledger_account_id' => $fixture['accounts']['receivable']->id, 'direction' => 'debit', 'amount_paise' => 30000],
            ['schedule_type' => 'other_asset', 'row_key' => 'CLUB-PROPERTY', 'non_sensitive_label' => 'Approved club property', 'ledger_account_id' => $fixture['accounts']['other_asset']->id, 'direction' => 'debit', 'amount_paise' => 0],
            ['schedule_type' => 'liability', 'row_key' => 'LIABILITY-GENERAL', 'non_sensitive_label' => 'Club liabilities', 'ledger_account_id' => $fixture['accounts']['liability']->id, 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'external_restriction', 'row_key' => 'RESTRICTED-BALANCE', 'non_sensitive_label' => 'Externally restricted balance', 'ledger_account_id' => $fixture['accounts']['restricted']->id, 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'general_club_fund', 'row_key' => 'GENERAL-FUND', 'non_sensitive_label' => 'General Club Fund presentation', 'presentation_group' => 'GENERAL-CLUB-FUND', 'direction' => 'credit', 'amount_paise' => 20000],
            ['schedule_type' => 'service_activity_fund', 'row_key' => 'SERVICE-FUND', 'non_sensitive_label' => 'Service Activity Fund presentation', 'presentation_group' => 'SERVICE-ACTIVITY-FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'fellowship_member_activity_fund', 'row_key' => 'FELLOWSHIP-FUND', 'non_sensitive_label' => 'Fellowship and Member Activity presentation', 'presentation_group' => 'FELLOWSHIP-MEMBER-ACTIVITY-FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'equity_preview', 'row_key' => 'EQUITY-PREVIEW', 'non_sensitive_label' => 'Unresolved opening equity preview', 'ledger_account_id' => $equity, 'direction' => 'credit', 'amount_paise' => 40000],
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
        $package = $this->service()->createDraft($fixture['club']->id, $fixture['actor']->id, $version);
        $package = $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, 1, $this->rows($fixture));
        if ($withEvidence) {
            $package = $this->service()->replaceEvidenceReferences($fixture['club']->id, $package->id, $fixture['actor']->id, 2, $this->evidence());
        }

        return $package;
    }

    private function reviewablePackage(array $fixture, string $classification, int $version = 1): ClubAccountingCutoverPackage
    {
        $package = $this->service()->createDraft($fixture['club']->id, $fixture['actor']->id, $version);
        $package = $this->service()->replaceSchedules($fixture['club']->id, $package->id, $fixture['actor']->id, 1, $this->rows($fixture, $classification));

        return $this->service()->replaceEvidenceReferences($fixture['club']->id, $package->id, $fixture['actor']->id, 2, $this->evidence());
    }

    private function approvedPackage(array $fixture): ClubAccountingCutoverPackage
    {
        $package = $this->reviewablePackage($fixture, 'OPENING-BALANCE-EQUITY');
        $this->service()->recordAccountingReview($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, 'Independent CA reviewer', '2027-07-10', 'OPENING-BALANCE-EQUITY', 'Evidence establishes a one-time opening equity classification for this exact package.');
        $package = $package->fresh();
        $this->service()->recordClubApproval($fixture['club']->id, $package->id, $fixture['actor']->id, $package->lock_version, 'President and authorised club body', '2027-07-12', 'CLUB-APPROVAL-2027-001');

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
}
