<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubAccountingSourceIntegration;
use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubCharge;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\MemberPayment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SourcePublicIdentitySqliteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
            throw new \RuntimeException('Requires disposable SQLite :memory:.');
        }
        parent::setUp();
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_sqlite_public_source_identity_guards_are_canonical_null_aware_and_immutable(): void
    {
        $f = $this->fixture();
        $chargeBase = ['club_id' => $f['club']->id, 'title' => 'Direct', 'category' => 'membership_dues', 'amount_paise' => 1, 'due_date' => '2027-07-31', 'financial_year' => '2027-28', 'status' => 'draft', 'assignment_member_ids' => '[]', 'created_by_user_id' => $f['actor']->id, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()];
        $paymentBase = ['club_id' => $f['club']->id, 'member_id' => $f['member']->id, 'amount_paise' => 1, 'payment_date' => '2027-07-05', 'payment_mode' => 'cash', 'request_hash' => str_repeat('a', 64), 'status' => 'recorded', 'recorded_by_user_id' => $f['actor']->id, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()];
        $invalid = [null, '-2345678-1234-1234-1234-123456789012', '1234567--1234-1234-1234-123456789012', '12345678-1234-1234-1234-12345678901-', '12345678-1234-1234-1234-12345678901G', '12345678-1234-1234-1234-12345678901A'];
        foreach (['club_charges' => $chargeBase, 'member_payments' => $paymentBase] as $table => $base) {
            foreach ($invalid as $publicId) {
                try {
                    $row = $base + ['public_id' => $publicId];
                    if ($table === 'member_payments') {
                        $row['idempotency_key'] = (string) Str::uuid();
                    }
                    DB::transaction(fn () => DB::table($table)->insert($row));
                    $this->fail('Invalid public identity insert must be rejected.');
                } catch (QueryException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
        $charge = $this->draftCharge($f);
        $payment = MemberPayment::create($paymentBase + ['idempotency_key' => (string) Str::uuid()]);
        foreach ([['club_charges', $charge->id], ['member_payments', $payment->id]] as [$table, $id]) {
            foreach ([(string) Str::uuid(), null] as $replacement) {
                try {
                    DB::transaction(fn () => DB::table($table)->where('id', $id)->update(['public_id' => $replacement]));
                    $this->fail('Public identity mutation must be rejected.');
                } catch (QueryException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
        $payment->delete();
        $this->integrationFixture($f, $charge);
        $copy = (array) DB::table('club_accounting_source_integrations')->first();
        unset($copy['id']);
        $copy['public_id'] = (string) Str::uuid();
        $copy['source_identifier'] = (string) Str::uuid();
        $copy['journal_idempotency_key'] = (string) Str::uuid();
        $copy['integration_checksum'] = str_repeat('A', 64);
        try {
            DB::transaction(fn () => DB::table('club_accounting_source_integrations')->insert($copy));
            $this->fail('Uppercase checksum content must be rejected.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    private function fixture(): array
    {
        $club = Club::create(['name' => 'SQLite identity fixture', 'code' => 'IDENTITY']);
        $actor = User::factory()->create();
        $member = Member::create(['club_id' => $club->id, 'member_code' => 'ID1', 'full_name' => 'Synthetic', 'status' => 'active']);

        return compact('club', 'actor', 'member');
    }

    private function draftCharge(array $f): ClubCharge
    {
        return ClubCharge::create(['club_id' => $f['club']->id, 'title' => 'Direct', 'category' => 'membership_dues', 'amount_paise' => 1, 'due_date' => '2027-07-31', 'financial_year' => '2027-28', 'status' => 'draft', 'assignment_member_ids' => [$f['member']->id], 'created_by_user_id' => $f['actor']->id, 'lock_version' => 1]);
    }

    private function integrationFixture(array $f, ClubCharge $charge): void
    {
        // Raw schema fixtures only: this test validates SQLite constraints, not posting APIs.
        $id = $f['club']->id;
        $actor = $f['actor']->id;
        $period = ClubAccountingPeriod::create(['club_id' => $id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $actor]);
        $account = LedgerAccount::create(['club_id' => $id, 'code' => 'IDENTITY-ASSET', 'name' => 'Synthetic asset', 'account_type' => 'asset', 'normal_balance' => 'debit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor]);
        $income = LedgerAccount::create(['club_id' => $id, 'code' => 'IDENTITY-INCOME', 'name' => 'Synthetic income', 'account_type' => 'income', 'normal_balance' => 'credit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor]);
        $journal = ClubJournalEntry::create(['club_id' => $id, 'accounting_period_id' => $period->id, 'sequence_number' => 1, 'reference' => 'IDENTITY/JRN/1', 'effective_date' => '2027-07-05', 'posting_date' => '2027-07-05', 'status' => 'draft', 'description' => 'Constraint fixture', 'currency' => 'INR', 'source_type' => 'identity_fixture', 'source_id' => 'one', 'source_event' => 'fixture', 'idempotency_key' => (string) Str::uuid(), 'created_by_user_id' => $actor]);
        foreach ([[$account, 'debit'], [$income, 'credit']] as $i => [$a, $direction]) {
            ClubJournalLine::create(['club_id' => $id, 'journal_entry_id' => $journal->id, 'ledger_account_id' => $a->id, 'line_number' => $i + 1, 'direction' => $direction, 'amount_paise' => 1, 'fund_code' => 'GENERAL_CLUB_FUND']);
        }
        $journal->update(['status' => 'posted', 'posted_at' => now(), 'posted_by_user_id' => $actor]);
        $package = ClubAccountingCutoverPackage::create(['club_id' => $id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01', 'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'draft', 'currency' => 'INR', 'canonical_checksum' => str_repeat('a', 64), 'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => false, 'created_by_user_id' => $actor, 'updated_by_user_id' => $actor]);
        $mapping = ClubAccountingSourceMapping::create(['club_id' => $id, 'source_type' => 'member_charge', 'canonical_source_key' => 'membership_dues', 'ledger_account_id' => $income->id, 'mapping_version' => 'MAP-V1', 'effective_date' => '2027-07-01', 'approval_reference' => 'IDENTITY-MAP', 'created_by_user_id' => $actor, 'approved_at' => now()]);
        $activation = ClubAccountingIntegrationActivation::create(['club_id' => $id, 'source_type' => 'member_charge', 'coverage_start_date' => '2027-07-01', 'mapping_version' => 'MAP-V1', 'opening_package_id' => $package->id, 'opening_journal_entry_id' => $journal->id, 'activation_reference' => 'IDENTITY-ACT', 'activated_by_user_id' => $actor, 'activated_at' => now()]);
        ClubAccountingSourceIntegration::create(['club_id' => $id, 'source_type' => 'member_charge', 'source_identifier' => $charge->public_id, 'integration_activation_id' => $activation->id, 'source_mapping_id' => $mapping->id, 'canonical_source_key' => 'membership_dues', 'source_amount_paise' => 1, 'source_binding' => [], 'source_version' => 1, 'accounting_date' => '2027-07-05', 'mapping_version' => 'MAP-V1', 'journal_entry_id' => $journal->id, 'journal_reference' => $journal->reference, 'journal_idempotency_key' => $journal->idempotency_key, 'integration_checksum' => str_repeat('a', 64), 'committed_by_user_id' => $actor, 'committed_at' => now()]);
    }
}
