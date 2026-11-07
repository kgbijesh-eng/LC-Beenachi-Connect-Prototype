<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Club;
use App\Models\ClubAccountingCutoverAuthorityConsumption;
use App\Models\ClubAccountingCutoverDecisionEvent;
use App\Models\ClubAccountingCutoverExecutionSuccess;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubMembership;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ClubAccountingCutoverPackageService;
use App\Services\Finance\ClubCutoverAuthorityAdministrationService;
use App\Services\Finance\ClubCutoverEvidenceVerificationService;
use App\Services\Finance\ClubCutoverLifecycleService;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\InteractsWithCutoverGovernance;
use Tests\Support\CutoverPostgresTestCase;

trait BuildsOpeningExecutionFixture
{
    protected function authorizedFixture(string $scenario, int $iteration, bool $authorize = true): array
    {
        foreach (['grantor', 'preparer', 'verifier', 'ca', 'president', 'operator'] as $key) {
            $$key = User::factory()->create();
        }
        $club = Club::create(['name' => "Concurrency {$scenario} {$iteration}", 'code' => 'EF'.str_pad((string) $iteration, 2, '0', STR_PAD_LEFT)]);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $president->id, 'role_id' => Role::where('slug', 'president-admin')->firstOrFail()->id, 'status' => 'active']);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $verifier->id, 'role_id' => Role::where('slug', 'secretary')->firstOrFail()->id, 'status' => 'active']);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $grantor->id, 'role_id' => Role::where('slug', 'treasurer')->firstOrFail()->id, 'status' => 'active']);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $preparer->id, 'role_id' => Role::where('slug', 'treasurer')->firstOrFail()->id, 'status' => 'active']);
        $accounts = [];
        foreach ([['cash', 'CASH-ON-HAND', 'asset', 'debit'], ['bank', 'BANK-OPERATING', 'asset', 'debit'], ['receivable', 'MEMBER-RECEIVABLES', 'asset', 'debit'], ['other_asset', 'CLUB-PROPERTY', 'asset', 'debit'], ['liability', 'LIABILITY-GENERAL', 'liability', 'credit'], ['restricted', 'LIABILITY-RESTRICTED-COLLECTION', 'liability', 'credit'], ['equity', 'OPENING-BALANCE-EQUITY', 'equity', 'credit'], ['surplus', 'ACCUMULATED-SURPLUS', 'equity', 'credit']] as [$key, $code, $type, $normal]) {
            $accounts[$key] = LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $grantor->id]);
        }
        ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $grantor->id]);
        $service = app(ClubAccountingCutoverPackageService::class);
        $package = $this->bootstrapCutoverPackage($club, $grantor, $president, $verifier, "BOOTSTRAP-{$scenario}-{$iteration}");
        $this->dualAssign($package, $grantor, 'finance.cutover.package.validate', $president, $verifier, "AUTHOR-VALIDATE-{$scenario}-{$iteration}");
        $rows = [
            ['schedule_type' => 'cash_on_hand', 'row_key' => 'CASH', 'non_sensitive_label' => 'Cash', 'ledger_account_id' => $accounts['cash']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 10000],
            ['schedule_type' => 'bank_account', 'row_key' => 'BANK', 'non_sensitive_label' => 'Bank', 'ledger_account_id' => $accounts['bank']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 20000],
            ['schedule_type' => 'member_receivables', 'row_key' => 'RECEIVABLES', 'non_sensitive_label' => 'Receivables', 'ledger_account_id' => $accounts['receivable']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 30000],
            ['schedule_type' => 'other_asset', 'row_key' => 'OTHER-ASSET', 'non_sensitive_label' => 'Other asset', 'ledger_account_id' => $accounts['other_asset']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'debit', 'amount_paise' => 0],
            ['schedule_type' => 'liability', 'row_key' => 'LIABILITY', 'non_sensitive_label' => 'Liability', 'ledger_account_id' => $accounts['liability']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'external_restriction', 'row_key' => 'RESTRICTED', 'non_sensitive_label' => 'Restricted', 'ledger_account_id' => $accounts['restricted']->id, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'general_club_fund', 'row_key' => 'GENERAL', 'non_sensitive_label' => 'General', 'presentation_group' => 'GENERAL-CLUB-FUND', 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'credit', 'amount_paise' => 20000],
            ['schedule_type' => 'service_activity_fund', 'row_key' => 'SERVICE', 'non_sensitive_label' => 'Service', 'presentation_group' => 'SERVICE-ACTIVITY-FUND', 'fund_code' => 'SERVICE_ACTIVITY_FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'fellowship_member_activity_fund', 'row_key' => 'FELLOWSHIP', 'non_sensitive_label' => 'Fellowship', 'presentation_group' => 'FELLOWSHIP-MEMBER-ACTIVITY-FUND', 'fund_code' => 'FELLOWSHIP_MEMBER_ACTIVITY_FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'equity_preview', 'row_key' => 'EQUITY', 'non_sensitive_label' => 'Equity', 'ledger_account_id' => null, 'fund_code' => 'GENERAL_CLUB_FUND', 'direction' => 'credit', 'amount_paise' => 40000],
        ];
        $package = $service->replaceSchedules($club->id, $package->id, $grantor->id, $package->lock_version, $rows);
        $evidence = collect(['cash_count', 'bank_reconciliation', 'member_receivables', 'liabilities_restrictions', 'reconciliation'])->map(fn ($type, $index) => ['evidence_type' => $type, 'opaque_reference' => 'EVIDENCE-'.strtoupper(str_replace('_', '-', $type)).'-'.$club->id, 'evidence_date' => '2027-07-01', 'evidence_version' => 'V1', 'digest_sha256' => str_repeat(dechex($index + 1), 64), 'verification_status' => 'verified'])->all();
        $package = $service->replaceEvidenceReferences($club->id, $package->id, $grantor->id, $package->lock_version, $evidence);
        $this->dualAssign($package, $verifier, 'finance.cutover.package.verify', $president, $verifier, "EVIDENCE-VERIFY-{$scenario}-{$iteration}");
        $verification = app(ClubCutoverEvidenceVerificationService::class);
        foreach ($package->evidenceReferences as $item) {
            $verification->decide($package->fresh(), $item, $verifier, 'verified', "VERIFY-{$scenario}-{$iteration}-{$item->id}", 'Metadata presence checked.', $package->fresh()->lock_version);
            $package = $package->fresh('evidenceReferences');
        }
        $package = $service->refreshValidation($club->id, $package->id, $grantor->id, $package->lock_version);
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        $caAppointment = $this->dualAppoint($package, $ca, 'independent_ca', $president, $verifier, "CA-{$scenario}-{$iteration}");
        $this->dualAttestEligibility($caAppointment, $president, $verifier, "ELIGIBILITY-{$scenario}-{$iteration}");
        $this->dualAppoint($package, $operator, 'technical_operator', $president, $verifier, "OP-{$scenario}-{$iteration}");
        foreach ([[$preparer, 'prepare'], [$ca, 'accounting-review'], [$ca, 'dry-run-accept'], [$ca, 'activate-authorize'], [$ca, 'rollback-authorize'], [$president, 'business-approve'], [$president, 'dry-run-accept'], [$president, 'activate-authorize'], [$operator, 'execute']] as $index => [$user, $permission]) {
            $this->dualAssign($package, $user, "finance.cutover.package.{$permission}", $president, $verifier, "{$scenario}-{$iteration}-{$index}");
        }
        $life = app(ClubCutoverLifecycleService::class);
        $package = $life->prepare($package, $preparer, $package->lock_version);
        $package = $life->verify($package, $verifier, $package->lock_version);
        $life->review($package, $ca, $package->lock_version, 'OPENING-BALANCE-EQUITY', "CA-REVIEW-{$scenario}-{$iteration}", '2027-07-02', 'Reconciled evidence substantiates opening balance equity.');
        $package = $package->fresh();
        $life->approve($package, $president, $package->lock_version, '2027-07-03', "APPROVAL-{$scenario}-{$iteration}");
        if ($authorize) {
            foreach ([['dry_run', 'president', $president], ['dry_run', 'independent_ca', $ca], ['activation', 'president', $president], ['activation', 'independent_ca', $ca]] as $index => [$stage, $authority, $actor]) {
                $package = $package->fresh();
                $life->decide($package, $actor, $package->lock_version, $stage, $authority, "DECISION-{$scenario}-{$iteration}-{$index}", 'Exact package decision accepted for concurrency verification.');
            }
        }
        $package = $package->fresh();

        return compact('club', 'package', 'operator', 'ca', 'president', 'verifier');
    }

}
