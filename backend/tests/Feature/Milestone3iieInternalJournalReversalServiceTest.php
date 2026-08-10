<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\Exceptions\AlreadyReversedJournalException;
use App\Services\Finance\Exceptions\ClosedReversalPeriodException;
use App\Services\Finance\Exceptions\InvalidReversalActorException;
use App\Services\Finance\Exceptions\InvalidReversalDateException;
use App\Services\Finance\Exceptions\InvalidReversalReasonException;
use App\Services\Finance\Exceptions\InvalidReversalRequestException;
use App\Services\Finance\Exceptions\JournalIdempotencyConflictException;
use App\Services\Finance\Exceptions\JournalSourceEventConflictException;
use App\Services\Finance\Exceptions\OriginalEntryNotPostedException;
use App\Services\Finance\Exceptions\ReversalClubScopeViolationException;
use App\Services\Finance\Exceptions\ReversalOfReversalProhibitedException;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use App\Services\Finance\JournalReversalRequest;
use App\Services\Finance\JournalReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class Milestone3iieInternalJournalReversalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_entry_reverses_to_one_linked_balanced_opposite_entry(): void
    {
        $fixture = $this->fixture();
        $original = $this->postOriginal($fixture);
        $originalLines = $original->lines->map(fn (ClubJournalLine $line): array => $line->only(['ledger_account_id', 'line_number', 'direction', 'amount_paise']))->all();

        $reversal = $this->reversals()->reverse($this->request($fixture, $original));

        $this->assertSame(2, ClubJournalEntry::count());
        $this->assertSame(4, ClubJournalLine::count());
        $this->assertSame(ClubJournalEntry::STATUS_POSTED, $reversal->status);
        $this->assertSame('INR', $reversal->currency);
        $this->assertSame($original->id, $reversal->reversal_of_entry_id);
        $this->assertSame(2, $reversal->sequence_number);
        $this->assertSame('REV/2026-27/JRN/000002', $reversal->reference);
        $this->assertSame($fixture['actor']->id, $reversal->created_by_user_id);
        $this->assertSame($fixture['actor']->id, $reversal->posted_by_user_id);
        $this->assertSame('2026-07-20', $reversal->effective_date->format('Y-m-d'));
        $this->assertSame('Reversal of REV/2026-27/JRN/000001: Duplicate fixture posting', $reversal->description);
        $this->assertSame('Duplicate fixture posting', $reversal->metadata['reversal_reason']);
        $this->assertSame($original->public_id, $reversal->metadata['reversal_original_entry_public_id']);
        $this->assertSame([1, 2], $reversal->lines->pluck('line_number')->all());
        $this->assertSame([$fixture['cash']->id, $fixture['income']->id], $reversal->lines->pluck('ledger_account_id')->all());
        $this->assertSame(['credit', 'debit'], $reversal->lines->pluck('direction')->all());
        $this->assertSame([12550, 12550], $reversal->lines->pluck('amount_paise')->all());
        $this->assertSame(12550, $reversal->lines->where('direction', 'debit')->sum('amount_paise'));
        $this->assertSame(12550, $reversal->lines->where('direction', 'credit')->sum('amount_paise'));

        $original = $original->fresh()->load('lines');
        $this->assertSame(ClubJournalEntry::STATUS_REVERSED, $original->status);
        $this->assertSame($fixture['actor']->id, $original->reversed_by_user_id);
        $this->assertSame($originalLines, $original->lines->map(fn (ClubJournalLine $line): array => $line->only(['ledger_account_id', 'line_number', 'direction', 'amount_paise']))->all());
        $this->assertSame(1, AuditLog::count());
        $this->assertSame('finance.journal.reversed', AuditLog::firstOrFail()->action);
    }

    public function test_exact_replay_returns_the_same_reversal_without_duplicate_transition_or_lines(): void
    {
        $fixture = $this->fixture();
        $original = $this->postOriginal($fixture);
        $request = $this->request($fixture, $original);

        $first = $this->reversals()->reverse($request);
        $replay = $this->reversals()->reverse($request);

        $this->assertTrue($first->is($replay));
        $this->assertSame(2, ClubJournalEntry::count());
        $this->assertSame(4, ClubJournalLine::count());
        $this->assertSame(1, AuditLog::count());
    }

    public function test_changed_replay_and_duplicate_source_event_fail_closed(): void
    {
        $fixture = $this->fixture();
        $original = $this->postOriginal($fixture);
        $request = $this->request($fixture, $original);
        $this->reversals()->reverse($request);

        try {
            $this->reversals()->reverse($this->request($fixture, $original, ['reason' => 'Changed reason', 'idempotencyKey' => $request->idempotencyKey]));
            $this->fail('Changed idempotent replay should fail.');
        } catch (JournalIdempotencyConflictException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(JournalSourceEventConflictException::class);
        $this->reversals()->reverse($this->request($fixture, $original, ['idempotencyKey' => (string) Str::uuid()]));
    }

    public function test_second_unrelated_reversal_attempt_fails_and_reversal_of_reversal_is_prohibited(): void
    {
        $fixture = $this->fixture();
        $original = $this->postOriginal($fixture);
        $reversal = $this->reversals()->reverse($this->request($fixture, $original));

        try {
            $this->reversals()->reverse($this->request($fixture, $original, [
                'sourceId' => 'another-source',
                'idempotencyKey' => (string) Str::uuid(),
            ]));
            $this->fail('An unrelated second reversal should fail.');
        } catch (AlreadyReversedJournalException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(ReversalOfReversalProhibitedException::class);
        $this->reversals()->reverse($this->request($fixture, $reversal, [
            'sourceId' => 'reversal-of-reversal',
            'idempotencyKey' => (string) Str::uuid(),
        ]));
    }

    public function test_closed_original_period_can_reverse_only_into_an_explicit_open_next_period(): void
    {
        $fixture = $this->fixture();
        $nextPeriod = $this->period($fixture, '2027-28', '2027-07-01', '2028-06-30');
        $original = $this->postOriginal($fixture);
        $fixture['period']->update([
            'status' => ClubAccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => $fixture['actor']->id,
        ]);

        $reversal = $this->reversals()->reverse($this->request($fixture, $original, [
            'reversalAccountingPeriodId' => $nextPeriod->id,
            'effectiveDate' => '2027-07-02',
        ]));

        $this->assertSame($nextPeriod->id, $reversal->accounting_period_id);
        $this->assertSame('2027-07-02', $reversal->effective_date->format('Y-m-d'));
    }

    public function test_draft_voided_closed_or_out_of_period_requests_fail_without_changing_original(): void
    {
        $fixture = $this->fixture();
        $draft = $this->draftEntry($fixture, ClubJournalEntry::STATUS_DRAFT);
        $voided = $this->draftEntry($fixture, ClubJournalEntry::STATUS_VOIDED);
        foreach ([
            [$draft, OriginalEntryNotPostedException::class],
            [$voided, OriginalEntryNotPostedException::class],
        ] as [$entry, $exception]) {
            try {
                $this->reversals()->reverse($this->request($fixture, $entry));
                $this->fail('Ineligible original should fail.');
            } catch (\Throwable $caught) {
                $this->assertInstanceOf($exception, $caught);
            }
            $this->assertSame($entry->status, $entry->fresh()->status);
        }

        $original = $this->postOriginal($this->fixture('DATED'));
        $datedFixture = ['club' => $original->club, 'actor' => User::findOrFail($original->created_by_user_id), 'period' => $original->accountingPeriod, 'cash' => null, 'income' => null];
        $this->expectException(InvalidReversalDateException::class);
        $this->reversals()->reverse($this->request($datedFixture, $original, ['effectiveDate' => '2026-06-30']));
    }

    public function test_cross_club_original_or_period_and_invalid_actor_fail_closed(): void
    {
        $fixtureA = $this->fixture('A');
        $fixtureB = $this->fixture('B');
        $originalA = $this->postOriginal($fixtureA);
        $originalB = $this->postOriginal($fixtureB);

        foreach ([
            [$fixtureA, $originalB, [], ReversalClubScopeViolationException::class],
            [$fixtureA, $originalA, ['reversalAccountingPeriodId' => $fixtureB['period']->id], ReversalClubScopeViolationException::class],
            [$fixtureA, $originalA, ['reversalActorUserId' => PHP_INT_MAX], InvalidReversalActorException::class],
        ] as [$fixture, $original, $overrides, $exception]) {
            try {
                $this->reversals()->reverse($this->request($fixture, $original, $overrides));
                $this->fail('Invalid cross-club or actor request should fail.');
            } catch (\Throwable $caught) {
                $this->assertInstanceOf($exception, $caught);
            }
        }

        $this->assertSame(2, ClubJournalEntry::count());
    }

    public function test_closed_target_period_blank_reason_and_invalid_identity_are_rejected(): void
    {
        $fixture = $this->fixture('VALIDATION');
        $original = $this->postOriginal($fixture);
        $fixture['period']->update([
            'status' => ClubAccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => $fixture['actor']->id,
        ]);

        try {
            $this->reversals()->reverse($this->request($fixture, $original));
            $this->fail('Closed target period should fail.');
        } catch (ClosedReversalPeriodException) {
            $this->addToAssertionCount(1);
        }

        $openFixture = $this->fixture('VALIDATION-OPEN');
        $openOriginal = $this->postOriginal($openFixture);
        foreach ([['reason' => '   '], ['sourceType' => ''], ['sourceId' => ''], ['sourceEvent' => ''], ['idempotencyKey' => 'not-a-uuid']] as $overrides) {
            try {
                $this->reversals()->reverse($this->request($openFixture, $openOriginal, $overrides));
                $this->fail('Invalid reversal request should fail.');
            } catch (InvalidReversalReasonException|InvalidReversalRequestException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_late_failure_rolls_back_reversal_lines_link_audit_and_original_lifecycle(): void
    {
        $fixture = $this->fixture('ROLLBACK');
        $original = $this->postOriginal($fixture);
        AuditLog::creating(fn () => throw new RuntimeException('Synthetic audit failure.'));

        try {
            $this->reversals()->reverse($this->request($fixture, $original));
            $this->fail('Synthetic audit failure should escape.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure.', $exception->getMessage());
        }

        $this->assertSame(1, ClubJournalEntry::count());
        $this->assertSame(2, ClubJournalLine::count());
        $this->assertSame(0, AuditLog::count());
        $this->assertSame(ClubJournalEntry::STATUS_POSTED, $original->fresh()->status);
        $this->assertNull($original->fresh()->reversed_by_user_id);
    }

    public function test_original_entry_and_lines_remain_immutable_after_reversal(): void
    {
        $fixture = $this->fixture('IMMUTABLE');
        $original = $this->postOriginal($fixture);
        $this->reversals()->reverse($this->request($fixture, $original));

        try {
            $original->fresh()->update(['description' => 'Forbidden change']);
            $this->fail('Reversed original should remain immutable.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(LogicException::class);
        $original->fresh()->lines()->firstOrFail()->delete();
    }

    public function test_reversal_service_exposes_no_generic_journal_route(): void
    {
        $uris = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_contains($uri, 'journal-reversal') || str_contains($uri, '/journals/') && str_contains($uri, 'reversal'));

        $this->assertSame([], $uris->values()->all());
        $this->assertContains('api/v1/clubs/{club}/finance/expenses/{expense}/reversal', collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())->all());
        $this->assertSame(0, AuditLog::count());
    }

    private function reversals(): JournalReversalService
    {
        return app(JournalReversalService::class);
    }

    private function fixture(string $suffix = 'REV'): array
    {
        $club = Club::create(['name' => "Reversal {$suffix}", 'code' => $suffix]);
        $actor = User::factory()->create();
        $period = $this->period(['club' => $club, 'actor' => $actor], '2026-27', '2026-07-01', '2027-06-30');
        $cash = $this->account($club, $actor, 'CASH', LedgerAccount::TYPE_ASSET, LedgerAccount::NORMAL_DEBIT);
        $income = $this->account($club, $actor, 'INCOME', LedgerAccount::TYPE_INCOME, LedgerAccount::NORMAL_CREDIT);

        return compact('club', 'actor', 'period', 'cash', 'income');
    }

    private function period(array $fixture, string $lionsYear, string $startDate, string $endDate): ClubAccountingPeriod
    {
        return ClubAccountingPeriod::create([
            'club_id' => $fixture['club']->id,
            'lions_year' => $lionsYear,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => ClubAccountingPeriod::STATUS_OPEN,
            'created_by_user_id' => $fixture['actor']->id,
        ]);
    }

    private function account(Club $club, User $actor, string $code, string $type, string $normalBalance): LedgerAccount
    {
        return LedgerAccount::create([
            'club_id' => $club->id,
            'code' => $code,
            'name' => $code,
            'account_type' => $type,
            'normal_balance' => $normalBalance,
            'currency' => LedgerAccount::CURRENCY_INR,
            'is_active' => true,
            'created_by_user_id' => $actor->id,
        ]);
    }

    private function postOriginal(array $fixture): ClubJournalEntry
    {
        return app(ClubJournalPostingService::class)->post(new JournalPostingRequest(
            clubId: $fixture['club']->id,
            accountingPeriodId: $fixture['period']->id,
            effectiveDate: '2026-07-15',
            description: 'Original fixture posting',
            currency: 'INR',
            sourceType: 'isolated_original',
            sourceId: 'original-'.Str::uuid(),
            sourceEvent: 'posted',
            idempotencyKey: (string) Str::uuid(),
            postingActorUserId: $fixture['actor']->id,
            lines: [
                new JournalLineInput($fixture['cash']->id, ClubJournalLine::DIRECTION_DEBIT, 12550, 'Cash', 'GENERAL_CLUB_FUND'),
                new JournalLineInput($fixture['income']->id, ClubJournalLine::DIRECTION_CREDIT, 12550, 'Income', 'GENERAL_CLUB_FUND'),
            ],
        ));
    }

    private function draftEntry(array $fixture, string $status): ClubJournalEntry
    {
        $sequence = $status === ClubJournalEntry::STATUS_VOIDED ? 98 : 99;

        return ClubJournalEntry::create([
            'club_id' => $fixture['club']->id,
            'accounting_period_id' => $fixture['period']->id,
            'sequence_number' => $sequence,
            'reference' => sprintf('DRAFT/2026-27/JRN/%06d', $sequence),
            'effective_date' => '2026-07-15',
            'posting_date' => '2026-07-15',
            'status' => $status,
            'description' => 'Ineligible fixture',
            'currency' => 'INR',
            'created_by_user_id' => $fixture['actor']->id,
            'voided_at' => $status === ClubJournalEntry::STATUS_VOIDED ? now() : null,
            'voided_by_user_id' => $status === ClubJournalEntry::STATUS_VOIDED ? $fixture['actor']->id : null,
        ]);
    }

    private function request(array $fixture, ClubJournalEntry $original, array $overrides = []): JournalReversalRequest
    {
        return new JournalReversalRequest(
            clubId: $overrides['clubId'] ?? $fixture['club']->id,
            originalEntryId: $overrides['originalEntryId'] ?? $original->id,
            reversalAccountingPeriodId: $overrides['reversalAccountingPeriodId'] ?? $fixture['period']->id,
            effectiveDate: $overrides['effectiveDate'] ?? '2026-07-20',
            reversalActorUserId: $overrides['reversalActorUserId'] ?? $fixture['actor']->id,
            reason: $overrides['reason'] ?? 'Duplicate fixture posting',
            sourceType: $overrides['sourceType'] ?? 'isolated_reversal',
            sourceId: $overrides['sourceId'] ?? 'reversal-source',
            sourceEvent: $overrides['sourceEvent'] ?? 'reversed',
            idempotencyKey: $overrides['idempotencyKey'] ?? (string) Str::uuid(),
        );
    }
}
