<?php

namespace Tests\Support;

use App\Models\{Club, ClubAccountingCutoverPackage, ClubAccountingCutoverScheduleRow, ClubAccountingPeriod, ClubMembership, LedgerAccount, Permission, Role, User};
use App\Services\Finance\{ClubJournalPostingService, JournalLineInput, JournalPostingRequest};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait BuildsMilestone3ijaeFixture
{
    private function fixture3ijae(string $suffix, string $role = 'treasurer', array $permissionSlugs = ['finance.club.ordinary_income_receipts.create', 'finance.club.ordinary_income_receipts.view', 'finance.club.ordinary_income_receipts.reverse', 'finance.club.statement.view'], bool $includePriorPeriod = false): array
    {
        $club = Club::create(['name' => "3IJAE {$suffix}", 'code' => substr("AE{$suffix}", 0, 10), 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        config(['instance.organisation_code' => $club->code]); $actor = User::factory()->create();
        $permissions = collect($permissionSlugs)->map(fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'finance', 'description' => $slug])); $roleModel = Role::create(['name' => "{$role} {$suffix}", 'slug' => strtolower("{$role}-{$suffix}"), 'description' => $role]); $roleModel->permissions()->attach($permissions->pluck('id')); ClubMembership::create(['club_id' => $club->id, 'user_id' => $actor->id, 'role_id' => $roleModel->id, 'status' => 'active']);
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]);
        $priorPeriod = $includePriorPeriod ? ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2026-27', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]) : null;
        $bank = $this->account3ijae($club, $actor, "BANK-{$suffix}", 'asset', 'debit', 'CASH_AND_BANK'); $income = $this->account3ijae($club, $actor, "INCOME-{$suffix}", 'income', 'credit', 'INCOME'); $equity = $this->account3ijae($club, $actor, "EQUITY-{$suffix}", 'equity', 'credit', 'FUND_BALANCE_EQUITY');
        $package = ClubAccountingCutoverPackage::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01', 'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'draft', 'currency' => 'INR', 'canonical_checksum' => str_repeat('a', 64), 'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => false, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]);
        $rows = collect([[$bank, 'debit'], [$equity, 'credit']])->map(fn ($row) => ClubAccountingCutoverScheduleRow::create(['club_id' => $club->id, 'cutover_package_id' => $package->id, 'schedule_type' => $row[0]->account_type === 'equity' ? 'equity_preview' : 'cash_on_hand', 'row_key' => 'OPENING-'.$row[0]->id, 'non_sensitive_label' => $row[0]->name, 'ledger_account_id' => $row[0]->id, 'direction' => $row[1], 'amount_paise' => 1000000, 'currency' => 'INR', 'fund_code' => 'GENERAL_CLUB_FUND']));
        $opening = app(ClubJournalPostingService::class)->post(new JournalPostingRequest($club->id, $period->id, '2027-07-01', 'Opening', 'INR', 'opening_balance_activation', $package->public_id, 'activated', (string) Str::uuid(), $actor->id, [new JournalLineInput($bank->id, 'debit', 1000000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[0]->id), new JournalLineInput($equity->id, 'credit', 1000000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[1]->id)]));
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'activation_authorized', 'activation_ready' => true]); DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'posted', 'opening_journal_entry_id' => $opening->id, 'posted_at' => now(), 'posted_by_user_id' => $actor->id]);
        return compact('club', 'actor', 'period', 'priorPeriod', 'bank', 'income', 'package');
    }
    private function account3ijae(Club $club, User $actor, string $code, string $type, string $normal, string $classification): LedgerAccount { return LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id, 'origin_kind' => 'custom', 'statement_classification' => $classification]); }
    private function receiptPayload3ijae(array $f, ?string $key = null): array { return ['subtype' => 'sponsorship', 'accounting_date' => '2027-08-10', 'receiving_account' => $f['bank']->public_id, 'income_account' => $f['income']->public_id, 'fund' => 'GENERAL_CLUB_FUND', 'amount_paise' => 2500000, 'description' => 'Earned sponsorship income', 'payer_name' => 'Example Sponsor', 'external_reference' => 'AGR-2027-001', 'event_label' => 'Community Event', 'idempotency_key' => $key ?? (string) Str::uuid()]; }
    private function correctionPayload3ijae(?string $key = null, ?array $replacement = null): array { return ['accounting_date' => '2027-08-12', 'reason' => 'Incorrect amount', 'idempotency_key' => $key ?? (string) Str::uuid(), 'replacement' => $replacement]; }
    private function receiptEndpoint3ijae(array $f): string { return "/api/v1/clubs/{$f['club']->id}/finance/ordinary-income-receipts"; }
}
