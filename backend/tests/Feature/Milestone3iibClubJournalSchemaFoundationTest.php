<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\Finance\ClubAccountStatementController;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ClubJournalPostingService;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class Milestone3iibClubJournalSchemaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_foundation_tables_and_columns_exist_without_soft_deletes(): void
    {
        $expected = [
            'ledger_accounts' => [
                'id', 'public_id', 'club_id', 'code', 'name', 'account_type',
                'normal_balance', 'currency', 'is_active', 'created_by_user_id',
                'created_at', 'updated_at',
            ],
            'club_accounting_periods' => [
                'id', 'public_id', 'club_id', 'lions_year', 'start_date', 'end_date',
                'status', 'closed_at', 'closed_by_user_id', 'created_by_user_id',
                'created_at', 'updated_at',
            ],
            'club_journal_entries' => [
                'id', 'public_id', 'club_id', 'accounting_period_id', 'sequence_number',
                'reference', 'effective_date', 'posting_date', 'posted_at', 'status',
                'description', 'currency', 'source_type', 'source_id', 'source_event',
                'idempotency_key', 'reversal_of_entry_id', 'created_by_user_id',
                'posted_by_user_id', 'reversed_by_user_id', 'voided_at',
                'voided_by_user_id', 'metadata', 'created_at', 'updated_at',
            ],
            'club_journal_lines' => [
                'id', 'public_id', 'club_id', 'journal_entry_id', 'ledger_account_id',
                'line_number', 'direction', 'amount_paise', 'description',
                'created_at', 'updated_at',
            ],
        ];

        foreach ($expected as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}.");
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}.");
            }
            $this->assertFalse(Schema::hasColumn($table, 'deleted_at'));
        }
    }

    public function test_database_constraints_enforce_values_uniqueness_and_lions_year_boundaries(): void
    {
        [$club, $user] = $this->clubAndUser('A');
        $account = $this->account($club, $user, 'CASH');
        $period = $this->period($club, $user, '2026-27', '2026-07-01', '2027-06-30');

        $this->assertConstraintFails(fn () => DB::table('ledger_accounts')->insert([
            ...$this->accountRow($club, $user, 'BAD-CURRENCY'),
            'currency' => 'USD',
        ]));
        $this->assertConstraintFails(fn () => DB::table('ledger_accounts')->insert([
            ...$this->accountRow($club, $user, 'BAD-TYPE'),
            'account_type' => 'memo',
        ]));
        $this->assertConstraintFails(fn () => DB::table('ledger_accounts')->insert([
            ...$this->accountRow($club, $user, 'BAD-BALANCE'),
            'normal_balance' => 'neutral',
        ]));
        $this->assertConstraintFails(fn () => DB::table('ledger_accounts')->insert(
            $this->accountRow($club, $user, 'CASH')
        ));

        $this->assertConstraintFails(fn () => DB::table('club_accounting_periods')->insert(
            $this->periodRow($club, $user, '2027-28', '2027-04-01', '2028-03-31')
        ));
        $this->assertConstraintFails(fn () => DB::table('club_accounting_periods')->insert(
            $this->periodRow($club, $user, '2026-27', '2026-07-01', '2027-06-30')
        ));

        $entry = $this->entry($club, $period, $user, 1, 'LCB/2026-27/JRN/000001');
        $this->assertConstraintFails(fn () => DB::table('club_journal_entries')->insert([
            ...$this->entryRow($club, $period, $user, 2, 'LCB/2026-27/JRN/000002'),
            'currency' => 'USD',
        ]));
        $this->assertConstraintFails(fn () => DB::table('club_journal_entries')->insert([
            ...$this->entryRow($club, $period, $user, 2, 'LCB/2026-27/JRN/000002'),
            'status' => 'deleted',
        ]));
        $this->assertConstraintFails(fn () => DB::table('club_journal_entries')->insert(
            $this->entryRow($club, $period, $user, 1, 'LCB/2026-27/JRN/000001')
        ));
        $this->assertConstraintFails(fn () => DB::table('club_journal_entries')->insert(
            $this->entryRow($club, $period, $user, 2, 'LCB/2026-27/JRN/000001')
        ));

        $sourceKey = (string) Str::uuid();
        DB::table('club_journal_entries')->insert([
            ...$this->entryRow($club, $period, $user, 3, 'LCB/2026-27/JRN/000003'),
            'source_type' => 'fixture', 'source_id' => 'source-1',
            'source_event' => 'approved', 'idempotency_key' => $sourceKey,
        ]);
        $this->assertConstraintFails(fn () => DB::table('club_journal_entries')->insert([
            ...$this->entryRow($club, $period, $user, 4, 'LCB/2026-27/JRN/000004'),
            'source_type' => 'fixture', 'source_id' => 'source-1',
            'source_event' => 'approved', 'idempotency_key' => (string) Str::uuid(),
        ]));
        $this->assertConstraintFails(fn () => DB::table('club_journal_entries')->insert([
            ...$this->entryRow($club, $period, $user, 5, 'LCB/2026-27/JRN/000005'),
            'source_type' => 'fixture', 'source_id' => 'source-2',
            'source_event' => 'approved', 'idempotency_key' => $sourceKey,
        ]));
        $this->assertConstraintFails(fn () => DB::table('club_journal_entries')
            ->where('id', $entry->id)->update(['reversal_of_entry_id' => $entry->id]));

        DB::table('club_journal_lines')->insert($this->lineRow($club, $entry, $account, 1));
        $this->assertConstraintFails(fn () => DB::table('club_journal_lines')->insert([
            ...$this->lineRow($club, $entry, $account, 2),
            'direction' => 'increase',
        ]));
        $this->assertConstraintFails(fn () => DB::table('club_journal_lines')->insert([
            ...$this->lineRow($club, $entry, $account, 2),
            'amount_paise' => 0,
        ]));
    }

    public function test_composite_foreign_keys_reject_cross_club_period_account_and_reversal_links(): void
    {
        [$clubA, $userA] = $this->clubAndUser('A');
        [$clubB, $userB] = $this->clubAndUser('B');
        $periodA = $this->period($clubA, $userA, '2026-27', '2026-07-01', '2027-06-30');
        $periodB = $this->period($clubB, $userB, '2026-27', '2026-07-01', '2027-06-30');
        $accountB = $this->account($clubB, $userB, 'BANK');
        $entryA = $this->entry($clubA, $periodA, $userA, 1, 'A/2026-27/JRN/000001');

        $this->assertConstraintFails(fn () => DB::table('club_journal_entries')->insert(
            $this->entryRow($clubA, $periodB, $userA, 2, 'A/2026-27/JRN/000002')
        ));
        $this->assertConstraintFails(fn () => DB::table('club_journal_lines')->insert(
            $this->lineRow($clubA, $entryA, $accountB, 1)
        ));
        $this->assertConstraintFails(fn () => DB::table('club_journal_entries')->insert([
            ...$this->entryRow($clubB, $periodB, $userB, 2, 'B/2026-27/JRN/000002'),
            'reversal_of_entry_id' => $entryA->id,
        ]));
    }

    public function test_source_identity_reversal_structure_models_relationships_casts_and_scopes(): void
    {
        [$club, $user] = $this->clubAndUser('M');
        $account = $this->account($club, $user, 'BANK');
        $period = $this->period($club, $user, '2026-27', '2026-07-01', '2027-06-30');
        $entry = $this->entry($club, $period, $user, 1, 'M/2026-27/JRN/000001', [
            'source_type' => 'approved_fixture',
            'source_id' => 'fixture-1',
            'source_event' => 'issued',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => ['contract' => '3IIB'],
        ]);
        $line = ClubJournalLine::create([
            'public_id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'journal_entry_id' => $entry->id,
            'ledger_account_id' => $account->id,
            'line_number' => 1,
            'direction' => ClubJournalLine::DIRECTION_DEBIT,
            'amount_paise' => 12345,
            'description' => 'Isolated structural fixture',
        ]);
        $reversal = $this->entry($club, $period, $user, 2, 'M/2026-27/JRN/000002', [
            'reversal_of_entry_id' => $entry->id,
            'reversed_by_user_id' => $user->id,
        ]);

        $this->assertTrue($account->club->is($club));
        $this->assertTrue($period->club->is($club));
        $this->assertTrue($entry->accountingPeriod->is($period));
        $this->assertTrue($line->journalEntry->is($entry));
        $this->assertTrue($line->ledgerAccount->is($account));
        $this->assertTrue($reversal->reversalOf->is($entry));
        $this->assertTrue($entry->reversals->contains($reversal));
        $this->assertSame(12345, $line->amount_paise);
        $this->assertSame(['contract' => '3IIB'], $entry->metadata);
        $this->assertSame([$account->id], LedgerAccount::forClub($club->id)->active()->pluck('id')->all());
        $this->assertSame([$period->id], ClubAccountingPeriod::forClub($club->id)->open()->pluck('id')->all());
        $this->assertSame(
            [$entry->id, $reversal->id],
            ClubJournalEntry::forClub($club->id)->inPeriod($period->id)->statementOrder()->pluck('id')->all()
        );

        $entry->status = ClubJournalEntry::STATUS_POSTED;
        $entry->posted_at = now();
        $entry->posted_by_user_id = $user->id;
        $entry->save();
        $entry->description = 'Forbidden edit';
        $this->expectException(LogicException::class);
        $entry->save();
    }

    public function test_posted_lines_are_model_guarded_against_update_and_delete(): void
    {
        [$club, $user] = $this->clubAndUser('G');
        $account = $this->account($club, $user, 'CASH');
        $period = $this->period($club, $user, '2026-27', '2026-07-01', '2027-06-30');
        $entry = $this->entry($club, $period, $user, 1, 'G/2026-27/JRN/000001');
        $line = ClubJournalLine::create([
            'public_id' => (string) Str::uuid(), 'club_id' => $club->id,
            'journal_entry_id' => $entry->id, 'ledger_account_id' => $account->id,
            'line_number' => 1, 'direction' => ClubJournalLine::DIRECTION_DEBIT,
            'amount_paise' => 100, 'description' => null,
        ]);
        $entry->update([
            'status' => ClubJournalEntry::STATUS_POSTED,
            'posted_at' => now(),
            'posted_by_user_id' => $user->id,
        ]);

        try {
            $line->update(['amount_paise' => 200]);
            $this->fail('A posted journal line must not be editable.');
        } catch (LogicException) {
            $this->assertSame(100, $line->fresh()->amount_paise);
        }

        $this->expectException(LogicException::class);
        $line->delete();
    }

    public function test_statement_permission_is_officer_only_and_runtime_remains_inert(): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);

        foreach (['president-admin', 'secretary', 'treasurer'] as $slug) {
            $this->assertTrue(Role::where('slug', $slug)->firstOrFail()
                ->permissions->contains('slug', 'finance.club.statement.view'));
        }
        $this->assertFalse(Role::where('slug', 'member')->firstOrFail()
            ->permissions->contains('slug', 'finance.club.statement.view'));

        $financeUris = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_contains($uri, 'account-statement') || str_contains($uri, 'journal'));
        $this->assertSame([], $financeUris->values()->all());
        $this->assertTrue(class_exists(ClubJournalPostingService::class));
        $this->assertFalse(class_exists(ClubAccountStatementController::class));
    }

    private function clubAndUser(string $suffix): array
    {
        $club = Club::create(['name' => "Test Club {$suffix}", 'code' => "TST-3IIB-{$suffix}"]);
        $user = User::factory()->create(['email' => strtolower($suffix).'-3iib@example.test']);

        return [$club, $user];
    }

    private function account(Club $club, User $user, string $code): LedgerAccount
    {
        return LedgerAccount::create($this->accountRow($club, $user, $code));
    }

    private function accountRow(Club $club, User $user, string $code): array
    {
        return [
            'public_id' => (string) Str::uuid(), 'club_id' => $club->id, 'code' => $code,
            'name' => "Account {$code}", 'account_type' => 'asset', 'normal_balance' => 'debit',
            'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function period(Club $club, User $user, string $label, string $start, string $end): ClubAccountingPeriod
    {
        return ClubAccountingPeriod::create($this->periodRow($club, $user, $label, $start, $end));
    }

    private function periodRow(Club $club, User $user, string $label, string $start, string $end): array
    {
        return [
            'public_id' => (string) Str::uuid(), 'club_id' => $club->id, 'lions_year' => $label,
            'start_date' => $start, 'end_date' => $end, 'status' => 'open',
            'closed_at' => null, 'closed_by_user_id' => null, 'created_by_user_id' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function entry(
        Club $club,
        ClubAccountingPeriod $period,
        User $user,
        int $sequence,
        string $reference,
        array $overrides = [],
    ): ClubJournalEntry {
        return ClubJournalEntry::create([...$this->entryRow($club, $period, $user, $sequence, $reference), ...$overrides]);
    }

    private function entryRow(Club $club, ClubAccountingPeriod $period, User $user, int $sequence, string $reference): array
    {
        return [
            'public_id' => (string) Str::uuid(), 'club_id' => $club->id,
            'accounting_period_id' => $period->id, 'sequence_number' => $sequence,
            'reference' => $reference, 'effective_date' => '2026-07-02',
            'posting_date' => '2026-07-02', 'posted_at' => null, 'status' => 'draft',
            'description' => 'Isolated structural fixture', 'currency' => 'INR',
            'source_type' => null, 'source_id' => null, 'source_event' => null,
            'idempotency_key' => null, 'reversal_of_entry_id' => null,
            'created_by_user_id' => $user->id, 'posted_by_user_id' => null,
            'reversed_by_user_id' => null, 'voided_at' => null, 'voided_by_user_id' => null,
            'metadata' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function lineRow(Club $club, ClubJournalEntry $entry, LedgerAccount $account, int $line): array
    {
        return [
            'public_id' => (string) Str::uuid(), 'club_id' => $club->id,
            'journal_entry_id' => $entry->id, 'ledger_account_id' => $account->id,
            'line_number' => $line, 'direction' => 'debit', 'amount_paise' => 100,
            'description' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function assertConstraintFails(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected the database constraint to reject the row.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
