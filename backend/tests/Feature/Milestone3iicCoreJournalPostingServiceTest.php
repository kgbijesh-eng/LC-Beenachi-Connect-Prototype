<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\MemberPayment;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\Exceptions\ClosedAccountingPeriodException;
use App\Services\Finance\Exceptions\InactiveLedgerAccountException;
use App\Services\Finance\Exceptions\InvalidAccountingPeriodException;
use App\Services\Finance\Exceptions\InvalidEffectiveDateException;
use App\Services\Finance\Exceptions\InvalidJournalRequestException;
use App\Services\Finance\Exceptions\InvalidMonetaryValueException;
use App\Services\Finance\Exceptions\JournalClubScopeViolationException;
use App\Services\Finance\Exceptions\JournalIdempotencyConflictException;
use App\Services\Finance\Exceptions\JournalSourceEventConflictException;
use App\Services\Finance\Exceptions\UnbalancedJournalException;
use App\Services\Finance\Exceptions\UnsupportedJournalCurrencyException;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;
use TypeError;

class Milestone3iicCoreJournalPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_request_posts_one_balanced_canonical_entry_in_integer_paise(): void
    {
        $fixture = $this->fixture();
        $request = $this->request($fixture);

        $entry = $this->service()->post($request);

        $this->assertSame(1, ClubJournalEntry::count());
        $this->assertSame(2, ClubJournalLine::count());
        $this->assertSame(ClubJournalEntry::STATUS_POSTED, $entry->status);
        $this->assertSame('INR', $entry->currency);
        $this->assertSame(1, $entry->sequence_number);
        $this->assertSame('LCB/2026-27/JRN/000001', $entry->reference);
        $this->assertSame('2026-07-15', $entry->effective_date->format('Y-m-d'));
        $this->assertSame('2026-07-15', $entry->posting_date->format('Y-m-d'));
        $this->assertSame($fixture['actor']->id, $entry->created_by_user_id);
        $this->assertSame($fixture['actor']->id, $entry->posted_by_user_id);
        $this->assertNotNull($entry->posted_at);
        $this->assertTrue($entry->relationLoaded('accountingPeriod'));
        $this->assertTrue($entry->relationLoaded('lines'));
        $this->assertSame([1, 2], $entry->lines->pluck('line_number')->all());
        $this->assertSame(['debit', 'credit'], $entry->lines->pluck('direction')->all());
        $this->assertSame([12550, 12550], $entry->lines->pluck('amount_paise')->all());
        $this->assertSame(12550, $entry->lines->where('direction', 'debit')->sum('amount_paise'));
        $this->assertSame(12550, $entry->lines->where('direction', 'credit')->sum('amount_paise'));
        $this->assertSame($request->idempotencyKey, $entry->idempotency_key);
        $this->assertSame($request->sourceType, $entry->source_type);
        $this->assertSame($request->sourceId, $entry->source_id);
        $this->assertSame($request->sourceEvent, $entry->source_event);
    }

    public function test_exact_replay_returns_the_same_entry_without_duplicate_lines(): void
    {
        $fixture = $this->fixture();
        $request = $this->request($fixture);

        $first = $this->service()->post($request);
        $replay = $this->service()->post($request);

        $this->assertTrue($replay->is($first));
        $this->assertSame(1, ClubJournalEntry::count());
        $this->assertSame(2, ClubJournalLine::count());
    }

    public function test_idempotency_key_replay_with_changed_financial_content_fails_closed(): void
    {
        $fixture = $this->fixture();
        $request = $this->request($fixture);
        $this->service()->post($request);

        $changed = $this->request($fixture, [
            'idempotencyKey' => $request->idempotencyKey,
            'lines' => [
                new JournalLineInput($fixture['cash']->id, 'debit', 20000),
                new JournalLineInput($fixture['income']->id, 'credit', 20000),
            ],
        ]);

        $this->expectException(JournalIdempotencyConflictException::class);
        $this->service()->post($changed);
    }

    public function test_source_event_replay_with_another_key_fails_closed(): void
    {
        $fixture = $this->fixture();
        $this->service()->post($this->request($fixture));

        $this->expectException(JournalSourceEventConflictException::class);
        $this->service()->post($this->request($fixture, ['idempotencyKey' => (string) Str::uuid()]));
    }

    public function test_replay_comparison_covers_every_material_header_and_ordered_line_field(): void
    {
        $fixture = $this->fixture();
        $request = $this->request($fixture);
        $this->service()->post($request);
        $otherActor = User::factory()->create();

        $changes = [
            ['effectiveDate' => '2026-07-16'],
            ['description' => 'Changed narration'],
            ['sourceType' => 'changed_fixture'],
            ['sourceId' => 'changed-source'],
            ['sourceEvent' => 'changed-event'],
            ['postingActorUserId' => $otherActor->id],
            ['lines' => [
                new JournalLineInput($fixture['income']->id, 'credit', 12550, 'Income recognized'),
                new JournalLineInput($fixture['cash']->id, 'debit', 12550, 'Cash received'),
            ]],
            ['lines' => [
                new JournalLineInput($fixture['cash']->id, 'debit', 12550, 'Changed line narration'),
                new JournalLineInput($fixture['income']->id, 'credit', 12550, 'Income recognized'),
            ]],
        ];

        foreach ($changes as $overrides) {
            try {
                $this->service()->post($this->request($fixture, [
                    ...$overrides,
                    'idempotencyKey' => $request->idempotencyKey,
                ]));
                $this->fail('A materially changed replay should fail closed.');
            } catch (JournalIdempotencyConflictException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('club_journal_entries', 1);
        $this->assertDatabaseCount('club_journal_lines', 2);
    }

    public function test_sequences_and_references_are_unique_and_monotonic_within_the_period(): void
    {
        $fixture = $this->fixture();
        $first = $this->service()->post($this->request($fixture));
        $second = $this->service()->post($this->request($fixture, [
            'sourceId' => 'source-2',
            'idempotencyKey' => (string) Str::uuid(),
        ]));

        $this->assertSame([1, 2], [$first->sequence_number, $second->sequence_number]);
        $this->assertSame(
            ['LCB/2026-27/JRN/000001', 'LCB/2026-27/JRN/000002'],
            [$first->reference, $second->reference],
        );
    }

    public function test_idempotency_and_sequences_are_club_scoped(): void
    {
        $fixtureA = $this->fixture('LCB');
        $fixtureB = $this->fixture('LCX');
        $key = (string) Str::uuid();

        $entryA = $this->service()->post($this->request($fixtureA, ['idempotencyKey' => $key]));
        $entryB = $this->service()->post($this->request($fixtureB, ['idempotencyKey' => $key]));

        $this->assertSame(1, $entryA->sequence_number);
        $this->assertSame(1, $entryB->sequence_number);
        $this->assertSame('LCB/2026-27/JRN/000001', $entryA->reference);
        $this->assertSame('LCX/2026-27/JRN/000001', $entryB->reference);
    }

    public function test_unbalanced_one_sided_and_too_few_lines_are_rejected_without_persistence(): void
    {
        $fixture = $this->fixture();
        $invalidLines = [
            [new JournalLineInput($fixture['cash']->id, 'debit', 100)],
            [
                new JournalLineInput($fixture['cash']->id, 'debit', 100),
                new JournalLineInput($fixture['income']->id, 'debit', 100),
            ],
            [
                new JournalLineInput($fixture['cash']->id, 'credit', 100),
                new JournalLineInput($fixture['income']->id, 'credit', 100),
            ],
            [
                new JournalLineInput($fixture['cash']->id, 'debit', 100),
                new JournalLineInput($fixture['income']->id, 'credit', 99),
            ],
        ];

        foreach ($invalidLines as $lines) {
            try {
                $this->service()->post($this->request($fixture, [
                    'lines' => $lines,
                    'idempotencyKey' => (string) Str::uuid(),
                ]));
                $this->fail('The invalid journal should have been rejected.');
            } catch (UnbalancedJournalException|InvalidJournalRequestException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('club_journal_entries', 0);
        $this->assertDatabaseCount('club_journal_lines', 0);
    }

    public function test_zero_negative_and_non_integer_amounts_are_rejected(): void
    {
        $this->expectException(TypeError::class);
        new JournalLineInput(1, 'debit', 1.25);
    }

    public function test_zero_and_negative_amounts_are_rejected_before_persistence(): void
    {
        $fixture = $this->fixture();

        foreach ([0, -1] as $amount) {
            try {
                $this->service()->post($this->request($fixture, [
                    'lines' => [
                        new JournalLineInput($fixture['cash']->id, 'debit', $amount),
                        new JournalLineInput($fixture['income']->id, 'credit', $amount),
                    ],
                    'idempotencyKey' => (string) Str::uuid(),
                ]));
                $this->fail('The invalid monetary amount should have been rejected.');
            } catch (InvalidJournalRequestException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('club_journal_entries', 0);
    }

    public function test_integer_paise_totals_cannot_overflow(): void
    {
        $fixture = $this->fixture();

        $this->expectException(InvalidMonetaryValueException::class);
        $this->service()->post($this->request($fixture, [
            'lines' => [
                new JournalLineInput($fixture['cash']->id, 'debit', PHP_INT_MAX),
                new JournalLineInput($fixture['cash']->id, 'debit', 1),
                new JournalLineInput($fixture['income']->id, 'credit', PHP_INT_MAX),
            ],
        ]));
    }

    public function test_non_inr_currency_is_rejected(): void
    {
        $fixture = $this->fixture();
        $this->expectException(UnsupportedJournalCurrencyException::class);
        $this->service()->post($this->request($fixture, ['currency' => 'USD']));
    }

    public function test_cross_club_period_and_accounts_are_rejected(): void
    {
        $fixtureA = $this->fixture('AAA');
        $fixtureB = $this->fixture('BBB');

        foreach ([
            ['accountingPeriodId' => $fixtureB['period']->id],
            ['lines' => [
                new JournalLineInput($fixtureA['cash']->id, 'debit', 12550),
                new JournalLineInput($fixtureB['income']->id, 'credit', 12550),
            ]],
        ] as $overrides) {
            try {
                $this->service()->post($this->request($fixtureA, [
                    ...$overrides,
                    'idempotencyKey' => (string) Str::uuid(),
                ]));
                $this->fail('Cross-club journal identity should fail closed.');
            } catch (JournalClubScopeViolationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('club_journal_entries', 0);
    }

    public function test_unknown_period_and_account_identities_are_rejected_without_persistence(): void
    {
        $fixture = $this->fixture();

        try {
            $this->service()->post($this->request($fixture, ['accountingPeriodId' => PHP_INT_MAX]));
            $this->fail('An unknown period should be rejected.');
        } catch (InvalidAccountingPeriodException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidJournalRequestException::class);
        $this->service()->post($this->request($fixture, [
            'lines' => [
                new JournalLineInput(PHP_INT_MAX, 'debit', 12550),
                new JournalLineInput($fixture['income']->id, 'credit', 12550),
            ],
        ]));
    }

    public function test_inactive_account_and_closed_period_are_rejected(): void
    {
        $fixture = $this->fixture();
        $fixture['cash']->update(['is_active' => false]);

        try {
            $this->service()->post($this->request($fixture));
            $this->fail('Inactive account should be rejected.');
        } catch (InactiveLedgerAccountException) {
            $this->addToAssertionCount(1);
        }

        $fixture['cash']->update(['is_active' => true]);
        $fixture['period']->update([
            'status' => ClubAccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => $fixture['actor']->id,
        ]);

        $this->expectException(ClosedAccountingPeriodException::class);
        $this->service()->post($this->request($fixture));
    }

    public function test_effective_date_must_be_valid_and_inside_the_lions_year(): void
    {
        $fixture = $this->fixture();

        foreach (['not-a-date', '2026-06-30', '2027-07-01'] as $date) {
            try {
                $this->service()->post($this->request($fixture, [
                    'effectiveDate' => $date,
                    'idempotencyKey' => (string) Str::uuid(),
                ]));
                $this->fail('Invalid or out-of-period date should be rejected.');
            } catch (InvalidEffectiveDateException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('club_journal_entries', 0);
    }

    public function test_missing_source_identity_idempotency_description_and_invalid_direction_are_rejected(): void
    {
        $fixture = $this->fixture();
        $requests = [
            ['sourceType' => ''],
            ['sourceId' => ''],
            ['sourceEvent' => ''],
            ['idempotencyKey' => ''],
            ['idempotencyKey' => 'not-a-uuid'],
            ['description' => '   '],
            ['lines' => [
                new JournalLineInput($fixture['cash']->id, 'increase', 12550),
                new JournalLineInput($fixture['income']->id, 'credit', 12550),
            ]],
        ];

        foreach ($requests as $overrides) {
            try {
                $this->service()->post($this->request($fixture, $overrides));
                $this->fail('Incomplete or invalid request should be rejected.');
            } catch (InvalidJournalRequestException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('club_journal_entries', 0);
    }

    public function test_failure_while_creating_lines_rolls_back_header_and_every_line(): void
    {
        $fixture = $this->fixture();
        ClubJournalLine::creating(function (ClubJournalLine $line): void {
            if ($line->line_number === 2) {
                throw new RuntimeException('Synthetic isolated-test failure.');
            }
        });

        try {
            $this->service()->post($this->request($fixture));
            $this->fail('Synthetic line failure should escape the service.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic isolated-test failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('club_journal_entries', 0);
        $this->assertDatabaseCount('club_journal_lines', 0);
    }

    public function test_posted_result_and_lines_remain_immutable(): void
    {
        $fixture = $this->fixture();
        $entry = $this->service()->post($this->request($fixture));

        try {
            $entry->update(['description' => 'Forbidden change']);
            $this->fail('Posted journal entry should be immutable.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(LogicException::class);
        $entry->lines->first()->delete();
    }

    public function test_service_adds_no_route_and_invokes_no_existing_finance_workflow(): void
    {
        $financeUris = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_contains($uri, 'account-statement') || str_contains($uri, 'journal'));

        $this->assertSame([], $financeUris->values()->all());
        $this->assertSame(0, MemberPayment::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, Receipt::count());
        $this->assertSame(0, AuditLog::count());
    }

    private function service(): ClubJournalPostingService
    {
        return app(ClubJournalPostingService::class);
    }

    private function fixture(string $clubCode = 'LCB'): array
    {
        $club = Club::create(['name' => "Posting Test {$clubCode}", 'code' => $clubCode.'-'.Str::lower(Str::random(6))]);
        $club->update(['code' => $clubCode]);
        $actor = User::factory()->create();
        $period = ClubAccountingPeriod::create([
            'club_id' => $club->id,
            'lions_year' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => ClubAccountingPeriod::STATUS_OPEN,
            'created_by_user_id' => $actor->id,
        ]);
        $cash = $this->account($club, $actor, 'CASH', 'asset', 'debit');
        $income = $this->account($club, $actor, 'DUES-INCOME', 'income', 'credit');

        return compact('club', 'actor', 'period', 'cash', 'income');
    }

    private function account(
        Club $club,
        User $actor,
        string $code,
        string $type,
        string $normalBalance,
    ): LedgerAccount {
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

    private function request(array $fixture, array $overrides = []): JournalPostingRequest
    {
        return new JournalPostingRequest(
            clubId: $overrides['clubId'] ?? $fixture['club']->id,
            accountingPeriodId: $overrides['accountingPeriodId'] ?? $fixture['period']->id,
            effectiveDate: $overrides['effectiveDate'] ?? '2026-07-15',
            description: $overrides['description'] ?? 'Approved isolated test posting',
            currency: $overrides['currency'] ?? 'INR',
            sourceType: $overrides['sourceType'] ?? 'isolated_fixture',
            sourceId: $overrides['sourceId'] ?? 'source-1',
            sourceEvent: $overrides['sourceEvent'] ?? 'approved',
            idempotencyKey: $overrides['idempotencyKey'] ?? (string) Str::uuid(),
            postingActorUserId: $overrides['postingActorUserId'] ?? $fixture['actor']->id,
            lines: $overrides['lines'] ?? [
                new JournalLineInput($fixture['cash']->id, 'debit', 12550, 'Cash received'),
                new JournalLineInput($fixture['income']->id, 'credit', 12550, 'Income recognized'),
            ],
        );
    }
}
