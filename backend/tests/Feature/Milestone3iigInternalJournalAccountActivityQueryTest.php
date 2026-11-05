<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\ClubJournalAccountActivityService;
use App\Services\Finance\Exceptions\InvalidJournalActivityCursorException;
use App\Services\Finance\Exceptions\InvalidJournalActivityQueryException;
use App\Services\Finance\Exceptions\JournalActivityIntegrityException;
use App\Services\Finance\Exceptions\JournalActivityScopeException;
use App\Services\Finance\Exceptions\UnsupportedJournalActivityCurrencyException;
use App\Services\Finance\JournalAccountActivityQuery;
use App\Services\Finance\JournalAccountActivityResult;
use App\Services\Finance\JournalAccountActivityRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class Milestone3iigInternalJournalAccountActivityQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_valid_range_returns_truthful_identity_zero_movement_and_no_balance_contract(): void
    {
        $fixture = $this->fixture();
        $result = $this->queryActivity($fixture);

        $this->assertInstanceOf(JournalAccountActivityResult::class, $result);
        $this->assertSame($fixture['club']->id, $result->clubId);
        $this->assertSame($fixture['club']->code, $result->clubCode);
        $this->assertSame($fixture['account']->id, $result->accountId);
        $this->assertSame('CASH', $result->accountCode);
        $this->assertSame('asset', $result->accountType);
        $this->assertSame('debit', $result->normalBalance);
        $this->assertSame('2026-07-01', $result->fromEffectiveDate);
        $this->assertSame('2026-07-31', $result->toEffectiveDate);
        $this->assertSame([], $result->rows);
        $this->assertSame(0, $result->periodDebitPaise);
        $this->assertSame(0, $result->periodCreditPaise);
        $this->assertSame(0, $result->signedPeriodMovementPaise);
        $this->assertFalse($result->hasMore);
        $this->assertNull($result->nextCursor);

        foreach ([JournalAccountActivityResult::class, JournalAccountActivityRow::class] as $class) {
            $names = array_map(fn ($property) => strtolower($property->getName()), (new ReflectionClass($class))->getProperties());
            foreach (['opening', 'running', 'closing', 'available', 'reconciled', 'completeness', 'carried'] as $prohibited) {
                $this->assertFalse(collect($names)->contains(fn (string $name) => str_contains($name, $prohibited)));
            }
        }
    }

    public function test_posted_debit_credit_and_multiple_selected_lines_are_returned_in_stable_accounting_order(): void
    {
        $fixture = $this->fixture();
        $later = $this->entry($fixture, 2, '2026-07-10', '2026-07-12 10:00:00', [
            [$fixture['account'], 'credit', 300, 'Later credit'],
            [$fixture['other'], 'debit', 300, null],
        ]);
        $earlier = $this->entry($fixture, 1, '2026-07-10', '2026-07-11 10:00:00', [
            [$fixture['account'], 'debit', 100, 'First selected'],
            [$fixture['account'], 'debit', 200, 'Second selected'],
            [$fixture['other'], 'credit', 300, null],
        ]);

        $result = $this->queryActivity($fixture);

        $this->assertSame([$earlier->public_id, $earlier->public_id, $later->public_id], array_column($result->rows, 'journalEntryPublicId'));
        $this->assertSame([1, 2, 1], array_column($result->rows, 'lineNumber'));
        $this->assertSame(['debit', 'debit', 'credit'], array_column($result->rows, 'direction'));
        $this->assertSame([100, 200, 300], array_column($result->rows, 'amountPaise'));
        $this->assertSame(['First selected', 'Second selected', 'Later credit'], array_column($result->rows, 'lineDescription'));
        $this->assertSame(300, $result->periodDebitPaise);
        $this->assertSame(300, $result->periodCreditPaise);
        $this->assertSame(0, $result->signedPeriodMovementPaise);
    }

    public function test_every_reachable_ordering_key_is_deterministic_across_identical_queries(): void
    {
        $fixture = $this->fixture();
        $effectiveLater = $this->entry($fixture, 5, '2026-07-12', '2026-07-10 09:00:00', [
            [$fixture['account'], 'debit', 500, null],
            [$fixture['other'], 'credit', 500, null],
        ]);
        $postedLater = $this->entry($fixture, 4, '2026-07-10', '2026-07-12 09:00:00', [
            [$fixture['account'], 'debit', 400, null],
            [$fixture['other'], 'credit', 400, null],
        ]);
        $sequenceLater = $this->entry($fixture, 3, '2026-07-10', '2026-07-11 09:00:00', [
            [$fixture['account'], 'debit', 300, 'Sequence three line one'],
            [$fixture['account'], 'debit', 30, 'Sequence three line two'],
            [$fixture['other'], 'credit', 330, null],
        ]);
        $sequenceEarlier = $this->entry($fixture, 2, '2026-07-10', '2026-07-11 09:00:00', [
            [$fixture['account'], 'debit', 200, null],
            [$fixture['other'], 'credit', 200, null],
        ]);

        $first = $this->queryActivity($fixture);
        $repeated = $this->queryActivity($fixture);
        $expectedEntries = [
            $sequenceEarlier->public_id,
            $sequenceLater->public_id,
            $sequenceLater->public_id,
            $postedLater->public_id,
            $effectiveLater->public_id,
        ];

        $this->assertSame($expectedEntries, array_column($first->rows, 'journalEntryPublicId'));
        $this->assertSame([1, 1, 2, 1, 1], array_column($first->rows, 'lineNumber'));
        $this->assertSame(
            array_column($first->rows, 'journalLinePublicId'),
            array_column($repeated->rows, 'journalLinePublicId'),
        );
    }

    public function test_lifecycle_effective_date_and_inactive_account_history_rules_are_truthful(): void
    {
        $fixture = $this->fixture();
        $fixture['account']->update(['is_active' => false]);
        $this->entry($fixture, 1, '2026-07-01', '2026-08-20 10:00:00', [[$fixture['account'], 'debit', 100, null], [$fixture['other'], 'credit', 100, null]]);
        $this->entry($fixture, 2, '2026-07-31', '2026-06-20 10:00:00', [[$fixture['account'], 'credit', 40, null], [$fixture['other'], 'debit', 40, null]], ClubJournalEntry::STATUS_REVERSED);
        $this->entry($fixture, 3, '2026-07-15', null, [[$fixture['account'], 'debit', 999, null], [$fixture['other'], 'credit', 999, null]], ClubJournalEntry::STATUS_DRAFT);
        $this->entry($fixture, 4, '2026-08-01', '2026-07-15 10:00:00', [[$fixture['account'], 'debit', 888, null], [$fixture['other'], 'credit', 888, null]]);

        $result = $this->queryActivity($fixture);

        $this->assertCount(2, $result->rows);
        $this->assertSame([100, 40], array_column($result->rows, 'amountPaise'));
        $this->assertSame(100, $result->periodDebitPaise);
        $this->assertSame(40, $result->periodCreditPaise);
        $this->assertSame(60, $result->signedPeriodMovementPaise);
    }

    public function test_effective_date_alone_controls_filtering_while_posting_date_remains_audit_context(): void
    {
        $fixture = $this->fixture();
        $included = $this->entry(
            $fixture,
            1,
            '2026-07-15',
            '2026-08-10 10:00:00',
            [[$fixture['account'], 'debit', 100, null], [$fixture['other'], 'credit', 100, null]],
            postingDate: '2026-08-10',
        );
        $this->entry(
            $fixture,
            2,
            '2026-08-10',
            '2026-07-15 10:00:00',
            [[$fixture['account'], 'debit', 200, null], [$fixture['other'], 'credit', 200, null]],
            postingDate: '2026-07-15',
        );

        $result = $this->queryActivity($fixture);

        $this->assertCount(1, $result->rows);
        $this->assertSame($included->public_id, $result->rows[0]->journalEntryPublicId);
        $this->assertSame('2026-07-15', $result->rows[0]->effectiveDate);
        $this->assertSame('2026-08-10', $result->rows[0]->postingDate);
        $this->assertSame(100, $result->periodDebitPaise);
    }

    public function test_original_and_posted_reversal_are_visible_linked_and_counted_once_each(): void
    {
        $fixture = $this->fixture();
        $original = $this->entry($fixture, 1, '2026-07-10', '2026-07-10 10:00:00', [[$fixture['account'], 'debit', 500, null], [$fixture['other'], 'credit', 500, null]], ClubJournalEntry::STATUS_REVERSED);
        $reversal = $this->entry($fixture, 2, '2026-07-11', '2026-07-11 10:00:00', [[$fixture['account'], 'credit', 500, null], [$fixture['other'], 'debit', 500, null]], ClubJournalEntry::STATUS_POSTED, $original->id);

        $result = $this->queryActivity($fixture);

        $this->assertCount(2, $result->rows);
        $this->assertNull($result->rows[0]->reversalOfEntryPublicId);
        $this->assertSame($reversal->public_id, $result->rows[0]->reversedByEntryPublicId);
        $this->assertSame($original->public_id, $result->rows[1]->reversalOfEntryPublicId);
        $this->assertNull($result->rows[1]->reversedByEntryPublicId);
        $this->assertSame(500, $result->periodDebitPaise);
        $this->assertSame(500, $result->periodCreditPaise);
    }

    public function test_duplicate_same_club_posted_reversals_fail_closed_without_repair_or_mutation(): void
    {
        $fixture = $this->fixture();
        $original = $this->entry($fixture, 1, '2026-07-10', '2026-07-10 10:00:00', [
            [$fixture['account'], 'debit', 500, null],
            [$fixture['other'], 'credit', 500, null],
        ], ClubJournalEntry::STATUS_REVERSED);
        $this->entry($fixture, 2, '2026-07-11', '2026-07-11 10:00:00', [
            [$fixture['account'], 'credit', 500, null],
            [$fixture['other'], 'debit', 500, null],
        ], reversalOf: $original->id);
        $this->entry($fixture, 3, '2026-07-12', '2026-07-12 10:00:00', [
            [$fixture['account'], 'credit', 500, null],
            [$fixture['other'], 'debit', 500, null],
        ], reversalOf: $original->id);
        $before = $this->rawJournalState();

        try {
            $this->queryActivity($fixture);
            $this->fail('Duplicate posted reversals must fail before returning activity or movement totals.');
        } catch (JournalActivityIntegrityException $exception) {
            $this->assertSame('The journal activity is malformed.', $exception->getMessage());
            $this->assertInstanceOf(JournalActivityIntegrityException::class, $exception->getPrevious());
        }

        $this->assertSame($before, $this->rawJournalState());
        $this->assertSame(ClubJournalEntry::STATUS_REVERSED, $original->fresh()->status);
        $this->assertSame(2, $original->reversals()->count());
    }

    public function test_cursor_pagination_is_context_bound_and_has_no_duplicates_or_omissions(): void
    {
        $fixture = $this->fixture();
        foreach (range(1, 5) as $sequence) {
            $selectedDirection = $sequence % 2 === 0 ? ClubJournalLine::DIRECTION_CREDIT : ClubJournalLine::DIRECTION_DEBIT;
            $otherDirection = $selectedDirection === ClubJournalLine::DIRECTION_DEBIT ? ClubJournalLine::DIRECTION_CREDIT : ClubJournalLine::DIRECTION_DEBIT;
            $this->entry($fixture, $sequence, sprintf('2026-07-%02d', $sequence), sprintf('2026-07-%02d 10:00:00', $sequence), [
                [$fixture['account'], $selectedDirection, $sequence, null],
                [$fixture['other'], $otherDirection, $sequence, null],
            ]);
        }

        $first = $this->queryActivity($fixture, pageSize: 2);
        $second = $this->queryActivity($fixture, pageSize: 2, cursor: $first->nextCursor);
        $third = $this->queryActivity($fixture, pageSize: 2, cursor: $second->nextCursor);
        $ids = [...array_column($first->rows, 'journalLinePublicId'), ...array_column($second->rows, 'journalLinePublicId'), ...array_column($third->rows, 'journalLinePublicId')];

        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->assertCount(5, $ids);
        $this->assertTrue($first->hasMore);
        $this->assertTrue($second->hasMore);
        $this->assertFalse($third->hasMore);
        $this->assertSame([], array_values(array_intersect(
            array_column($first->rows, 'journalLinePublicId'),
            array_column($second->rows, 'journalLinePublicId'),
        )));
        foreach ([$first, $second] as $page) {
            $this->assertSame(9, $page->periodDebitPaise);
            $this->assertSame(6, $page->periodCreditPaise);
            $this->assertSame(3, $page->signedPeriodMovementPaise);
        }

        $foreign = $this->fixture('FOREIGN');
        foreach ([
            $this->request($fixture, cursor: 'invalid'),
            $this->request($fixture, accountId: $fixture['other']->id, cursor: $first->nextCursor),
            $this->request($foreign, cursor: $first->nextCursor),
            $this->request($fixture, from: '2026-07-02', cursor: $first->nextCursor),
        ] as $index => $request) {
            try {
                $this->service()->query($request);
                $this->fail('Invalid or replayed cursor should fail.');
            } catch (InvalidJournalActivityCursorException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_credit_normal_signed_movement_and_unrelated_accounts_are_handled_correctly(): void
    {
        $fixture = $this->fixture();
        $this->entry($fixture, 1, '2026-07-10', '2026-07-10 10:00:00', [[$fixture['account'], 'debit', 100, null], [$fixture['other'], 'credit', 100, null]]);
        $result = $this->service()->query($this->request($fixture, accountId: $fixture['other']->id));

        $this->assertCount(1, $result->rows);
        $this->assertSame('credit', $result->normalBalance);
        $this->assertSame(100, $result->periodCreditPaise);
        $this->assertSame(100, $result->signedPeriodMovementPaise);
    }

    public function test_invalid_identity_scope_dates_page_size_and_currency_fail_closed(): void
    {
        $fixture = $this->fixture();
        $foreign = $this->fixture('FOREIGN');

        $cases = [
            [InvalidJournalActivityQueryException::class, $this->request($fixture, clubId: 999999)],
            [InvalidJournalActivityQueryException::class, $this->request($fixture, accountId: 999999)],
            [JournalActivityScopeException::class, $this->request($fixture, accountId: $foreign['account']->id)],
            [InvalidJournalActivityQueryException::class, $this->request($fixture, from: '2026-02-30')],
            [InvalidJournalActivityQueryException::class, $this->request($fixture, from: '2026-08-01', to: '2026-07-01')],
            [InvalidJournalActivityQueryException::class, $this->request($fixture, from: '2026-06-30')],
            [InvalidJournalActivityQueryException::class, $this->request($fixture, pageSize: 0)],
            [InvalidJournalActivityQueryException::class, $this->request($fixture, pageSize: 101)],
        ];
        foreach ($cases as [$exception, $request]) {
            try {
                $this->service()->query($request);
                $this->fail('Invalid activity query should fail.');
            } catch (\Throwable $actual) {
                $this->assertInstanceOf($exception, $actual);
            }
        }

        DB::statement('DROP TRIGGER ledger_account_currency_check_update');
        DB::table('ledger_accounts')->where('id', $fixture['account']->id)->update(['currency' => 'USD']);
        $this->expectException(UnsupportedJournalActivityCurrencyException::class);
        $this->queryActivity($fixture);
    }

    public function test_malformed_selected_activity_fails_instead_of_being_omitted(): void
    {
        $fixture = $this->fixture();
        $entry = $this->entry($fixture, 1, '2026-07-10', '2026-07-10 10:00:00', [[$fixture['account'], 'debit', 100, null], [$fixture['other'], 'credit', 100, null]]);
        DB::statement('DROP TRIGGER journal_line_direction_check_update');
        DB::table('club_journal_lines')->where('journal_entry_id', $entry->id)->where('ledger_account_id', $fixture['account']->id)->update(['direction' => 'broken']);

        $this->expectException(JournalActivityIntegrityException::class);
        $this->queryActivity($fixture);
    }

    public function test_query_is_read_only_and_introduces_no_runtime_or_schema_surface(): void
    {
        $fixture = $this->fixture();
        $this->entry($fixture, 1, '2026-07-10', '2026-07-10 10:00:00', [[$fixture['account'], 'debit', 100, null], [$fixture['other'], 'credit', 100, null]]);
        $tables = ['ledger_accounts', 'club_accounting_periods', 'club_journal_entries', 'club_journal_lines'];
        $before = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();

        $this->queryActivity($fixture);

        $after = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();
        $this->assertSame($before, $after);
        $this->assertSame([], collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())->filter(fn (string $uri) => str_contains($uri, 'journal-account-activity'))->values()->all());
        $this->assertFileDoesNotExist(database_path('migrations/2026_07_19_000001_create_journal_activity.php'));
    }

    private function service(): ClubJournalAccountActivityService
    {
        return app(ClubJournalAccountActivityService::class);
    }

    private function queryActivity(array $fixture, int $pageSize = 50, ?string $cursor = null): JournalAccountActivityResult
    {
        return $this->service()->query($this->request($fixture, pageSize: $pageSize, cursor: $cursor));
    }

    private function request(array $fixture, ?int $clubId = null, ?int $accountId = null, string $from = '2026-07-01', string $to = '2026-07-31', int $pageSize = 50, ?string $cursor = null): JournalAccountActivityQuery
    {
        return new JournalAccountActivityQuery($clubId ?? $fixture['club']->id, $accountId ?? $fixture['account']->id, $from, $to, $pageSize, $cursor);
    }

    private function fixture(string $suffix = 'ACTIVITY'): array
    {
        $club = Club::create(['name' => "Activity {$suffix}", 'code' => substr($suffix, 0, 20)]);
        $actor = User::factory()->create();
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2026-27', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]);
        $account = $this->account($club, $actor, 'CASH', 'asset', 'debit');
        $other = $this->account($club, $actor, 'INCOME', 'income', 'credit');

        return compact('club', 'actor', 'period', 'account', 'other');
    }

    private function account(Club $club, User $actor, string $code, string $type, string $normalBalance): LedgerAccount
    {
        return LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normalBalance, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id]);
    }

    private function rawJournalState(): array
    {
        return collect(['ledger_accounts', 'club_accounting_periods', 'club_journal_entries', 'club_journal_lines'])
            ->mapWithKeys(fn (string $table) => [
                $table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            ])->all();
    }

    private function entry(array $fixture, int $sequence, string $effectiveDate, ?string $postedAt, array $lines, string $status = ClubJournalEntry::STATUS_POSTED, ?int $reversalOf = null, ?string $postingDate = null): ClubJournalEntry
    {
        $entry = ClubJournalEntry::create([
            'club_id' => $fixture['club']->id, 'accounting_period_id' => $fixture['period']->id,
            'sequence_number' => $sequence, 'reference' => sprintf('%s/2026-27/JRN/%06d', $fixture['club']->code, $sequence),
            'effective_date' => $effectiveDate, 'posting_date' => $postingDate ?? $effectiveDate, 'posted_at' => null,
            'status' => ClubJournalEntry::STATUS_DRAFT, 'description' => "Entry {$sequence}", 'currency' => 'INR',
            'source_type' => 'fixture', 'source_id' => (string) Str::uuid(), 'source_event' => 'posted',
            'idempotency_key' => (string) Str::uuid(), 'reversal_of_entry_id' => $reversalOf,
            'created_by_user_id' => $fixture['actor']->id,
        ]);
        foreach ($lines as $index => [$account, $direction, $amount, $description]) {
            ClubJournalLine::create(['club_id' => $fixture['club']->id, 'journal_entry_id' => $entry->id, 'ledger_account_id' => $account->id, 'line_number' => $index + 1, 'direction' => $direction, 'amount_paise' => $amount, 'description' => $description]);
        }

        if ($status !== ClubJournalEntry::STATUS_DRAFT) {
            DB::table('club_journal_entries')->where('id', $entry->id)->update([
                'status' => $status,
                'posted_at' => $postedAt,
                'posted_by_user_id' => $postedAt ? $fixture['actor']->id : null,
            ]);
        }

        return $entry->fresh();
    }
}
