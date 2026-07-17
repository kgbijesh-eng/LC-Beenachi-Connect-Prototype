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
use App\Services\Finance\ClubAccountingPeriodProvisioningRequest;
use App\Services\Finance\ClubAccountingPeriodProvisioningService;
use App\Services\Finance\Exceptions\AccountingPeriodProvisioningConflictException;
use App\Services\Finance\Exceptions\InvalidLedgerAccountCodeException;
use App\Services\Finance\Exceptions\InvalidLedgerAccountNormalBalanceException;
use App\Services\Finance\Exceptions\InvalidLedgerAccountTypeException;
use App\Services\Finance\Exceptions\InvalidLionsYearException;
use App\Services\Finance\Exceptions\InvalidProvisioningActorException;
use App\Services\Finance\Exceptions\InvalidProvisioningClubException;
use App\Services\Finance\Exceptions\InvalidProvisioningCurrencyException;
use App\Services\Finance\Exceptions\InvalidProvisioningRequestException;
use App\Services\Finance\Exceptions\LedgerAccountProvisioningConflictException;
use App\Services\Finance\LedgerAccountProvisioningRequest;
use App\Services\Finance\LedgerAccountProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class Milestone3iidLedgerAccountPeriodProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_provisioning_normalizes_code_and_returns_the_canonical_active_account(): void
    {
        $fixture = $this->fixture('A');

        $account = $this->accounts()->provision($this->accountRequest($fixture, ['code' => ' cash-on-hand ']));

        $this->assertSame($fixture['club']->id, $account->club_id);
        $this->assertSame('CASH-ON-HAND', $account->code);
        $this->assertSame('Cash on hand', $account->name);
        $this->assertSame(LedgerAccount::TYPE_ASSET, $account->account_type);
        $this->assertSame(LedgerAccount::NORMAL_DEBIT, $account->normal_balance);
        $this->assertSame(LedgerAccount::CURRENCY_INR, $account->currency);
        $this->assertTrue($account->is_active);
        $this->assertSame($fixture['actor']->id, $account->created_by_user_id);
        $this->assertNotEmpty($account->public_id);
        $this->assertSame(1, LedgerAccount::count());
        $this->assertSame(0, ClubJournalEntry::count());
        $this->assertSame(0, ClubJournalLine::count());
    }

    public function test_equivalent_normalized_account_replay_returns_the_same_account_without_mutation(): void
    {
        $fixture = $this->fixture('B');
        $first = $this->accounts()->provision($this->accountRequest($fixture, ['code' => ' cash-on-hand ']));
        $replay = $this->accounts()->provision($this->accountRequest($fixture, ['code' => 'CASH-ON-HAND']));

        $this->assertTrue($first->is($replay));
        $this->assertSame(1, LedgerAccount::count());
    }

    public function test_account_replay_conflicts_when_any_material_field_changes(): void
    {
        $fixture = $this->fixture('C');
        $this->accounts()->provision($this->accountRequest($fixture));
        $otherActor = User::factory()->create();

        foreach ([
            ['name' => 'Changed account name'],
            ['accountType' => LedgerAccount::TYPE_LIABILITY, 'normalBalance' => LedgerAccount::NORMAL_CREDIT],
            ['createdByUserId' => $otherActor->id],
        ] as $overrides) {
            try {
                $this->accounts()->provision($this->accountRequest($fixture, $overrides));
                $this->fail('A materially changed account replay must fail closed.');
            } catch (LedgerAccountProvisioningConflictException) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $this->accounts()->provision($this->accountRequest($fixture, ['normalBalance' => LedgerAccount::NORMAL_CREDIT]));
            $this->fail('An incompatible normal balance must fail closed.');
        } catch (InvalidLedgerAccountNormalBalanceException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, LedgerAccount::count());
    }

    public function test_account_codes_are_isolated_by_club(): void
    {
        $fixtureA = $this->fixture('D');
        $fixtureB = $this->fixture('E');

        $accountA = $this->accounts()->provision($this->accountRequest($fixtureA));
        $accountB = $this->accounts()->provision($this->accountRequest($fixtureB));

        $this->assertSame('CASH-ON-HAND', $accountA->code);
        $this->assertSame('CASH-ON-HAND', $accountB->code);
        $this->assertNotSame($accountA->club_id, $accountB->club_id);
        $this->assertSame(2, LedgerAccount::count());
    }

    public function test_account_rejects_unknown_club_and_actor_without_partial_persistence(): void
    {
        $fixture = $this->fixture('F');

        try {
            $this->accounts()->provision($this->accountRequest($fixture, ['clubId' => PHP_INT_MAX]));
            $this->fail('Unknown club should fail.');
        } catch (InvalidProvisioningClubException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidProvisioningActorException::class);
        $this->accounts()->provision($this->accountRequest($fixture, ['createdByUserId' => PHP_INT_MAX]));
    }

    public function test_account_code_name_type_normal_balance_and_currency_validation_fail_closed(): void
    {
        $fixture = $this->fixture('G');
        $invalidCodes = ['', 'A', str_repeat('A', 41), '  CASH ON HAND  ', 'CASH__ON_HAND', 'CASH.ON-HAND', 'CASH/ON-HAND', 'CASH--ON-HAND', '-CASH', 'CASH-', 'CÄSH'];

        foreach ($invalidCodes as $code) {
            try {
                $this->accounts()->provision($this->accountRequest($fixture, ['code' => $code]));
                $this->fail('Invalid account code should fail.');
            } catch (InvalidLedgerAccountCodeException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidProvisioningRequestException::class);
        $this->accounts()->provision($this->accountRequest($fixture, ['name' => '   ']));
    }

    public function test_every_approved_account_type_has_its_approved_normal_balance(): void
    {
        $fixture = $this->fixture('GA');

        foreach ([
            [LedgerAccount::TYPE_ASSET, LedgerAccount::NORMAL_DEBIT],
            [LedgerAccount::TYPE_EXPENSE, LedgerAccount::NORMAL_DEBIT],
            [LedgerAccount::TYPE_LIABILITY, LedgerAccount::NORMAL_CREDIT],
            [LedgerAccount::TYPE_EQUITY, LedgerAccount::NORMAL_CREDIT],
            [LedgerAccount::TYPE_INCOME, LedgerAccount::NORMAL_CREDIT],
        ] as [$type, $normalBalance]) {
            $account = $this->accounts()->provision($this->accountRequest($fixture, [
                'code' => strtoupper($type).'-ACCOUNT',
                'accountType' => $type,
                'normalBalance' => $normalBalance,
            ]));
            $this->assertSame($type, $account->account_type);
            $this->assertSame($normalBalance, $account->normal_balance);
        }

        $this->assertSame(5, LedgerAccount::count());
    }

    public function test_account_rejects_invalid_type_normal_balance_currency_and_unapproved_pairing(): void
    {
        $fixture = $this->fixture('H');

        foreach ([
            [['accountType' => 'memo'], InvalidLedgerAccountTypeException::class],
            [['normalBalance' => 'neutral'], InvalidLedgerAccountNormalBalanceException::class],
            [['currency' => 'USD'], InvalidProvisioningCurrencyException::class],
            [['accountType' => LedgerAccount::TYPE_ASSET, 'normalBalance' => LedgerAccount::NORMAL_CREDIT], InvalidLedgerAccountNormalBalanceException::class],
        ] as [$overrides, $exception]) {
            try {
                $this->accounts()->provision($this->accountRequest($fixture, $overrides));
                $this->fail('Invalid account field should fail.');
            } catch (\Throwable $caught) {
                $this->assertInstanceOf($exception, $caught);
            }
        }

        $this->assertSame(0, LedgerAccount::count());
    }

    public function test_account_code_is_model_immutable_after_provisioning(): void
    {
        $fixture = $this->fixture('I');
        $account = $this->accounts()->provision($this->accountRequest($fixture));

        $this->expectException(LogicException::class);
        $account->update(['code' => 'CHANGED-CODE']);
    }

    public function test_account_transaction_rolls_back_when_model_creation_fails(): void
    {
        $fixture = $this->fixture('J');
        LedgerAccount::creating(fn () => throw new RuntimeException('Synthetic account failure.'));

        try {
            $this->accounts()->provision($this->accountRequest($fixture));
            $this->fail('Synthetic creation failure should escape.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic account failure.', $exception->getMessage());
        }

        $this->assertSame(0, LedgerAccount::count());
    }

    public function test_period_provisioning_derives_the_canonical_open_lions_year(): void
    {
        $fixture = $this->fixture('K');

        $period = $this->periods()->provision($this->periodRequest($fixture));

        $this->assertSame($fixture['club']->id, $period->club_id);
        $this->assertSame('2026-27', $period->lions_year);
        $this->assertSame('2026-07-01', $period->start_date->format('Y-m-d'));
        $this->assertSame('2027-06-30', $period->end_date->format('Y-m-d'));
        $this->assertSame(ClubAccountingPeriod::STATUS_OPEN, $period->status);
        $this->assertNull($period->closed_at);
        $this->assertNull($period->closed_by_user_id);
        $this->assertSame($fixture['actor']->id, $period->created_by_user_id);
        $this->assertNotEmpty($period->public_id);
        $this->assertSame(1, ClubAccountingPeriod::count());
        $this->assertSame(0, LedgerAccount::count());
        $this->assertSame(0, ClubJournalEntry::count());
    }

    public function test_exact_period_replay_returns_the_same_period_without_duplication(): void
    {
        $fixture = $this->fixture('L');
        $first = $this->periods()->provision($this->periodRequest($fixture));
        $replay = $this->periods()->provision($this->periodRequest($fixture));

        $this->assertTrue($first->is($replay));
        $this->assertSame(1, ClubAccountingPeriod::count());
    }

    public function test_period_replay_conflicts_for_a_different_actor_or_noncanonical_existing_state(): void
    {
        $fixture = $this->fixture('M');
        $this->periods()->provision($this->periodRequest($fixture));
        $otherActor = User::factory()->create();

        try {
            $this->periods()->provision($this->periodRequest($fixture, ['createdByUserId' => $otherActor->id]));
            $this->fail('Changed actor should fail closed.');
        } catch (AccountingPeriodProvisioningConflictException) {
            $this->addToAssertionCount(1);
        }

        $period = ClubAccountingPeriod::firstOrFail();
        $period->update(['status' => ClubAccountingPeriod::STATUS_CLOSED, 'closed_at' => now(), 'closed_by_user_id' => $fixture['actor']->id]);

        $this->expectException(AccountingPeriodProvisioningConflictException::class);
        $this->periods()->provision($this->periodRequest($fixture));
    }

    public function test_period_lions_years_are_isolated_by_club(): void
    {
        $fixtureA = $this->fixture('N');
        $fixtureB = $this->fixture('O');

        $periodA = $this->periods()->provision($this->periodRequest($fixtureA));
        $periodB = $this->periods()->provision($this->periodRequest($fixtureB));

        $this->assertSame('2026-27', $periodA->lions_year);
        $this->assertSame('2026-27', $periodB->lions_year);
        $this->assertNotSame($periodA->club_id, $periodB->club_id);
    }

    public function test_period_rejects_invalid_club_actor_and_lions_year_without_partial_persistence(): void
    {
        $fixture = $this->fixture('P');

        foreach ([
            [['clubId' => PHP_INT_MAX], InvalidProvisioningClubException::class],
            [['createdByUserId' => PHP_INT_MAX], InvalidProvisioningActorException::class],
            [['lionsYear' => '2026-28'], InvalidLionsYearException::class],
            [['lionsYear' => '2026'], InvalidLionsYearException::class],
            [['lionsYear' => ' 2026-27 '], InvalidLionsYearException::class],
        ] as [$overrides, $exception]) {
            try {
                $this->periods()->provision($this->periodRequest($fixture, $overrides));
                $this->fail('Invalid period request should fail.');
            } catch (\Throwable $caught) {
                $this->assertInstanceOf($exception, $caught);
            }
        }

        $this->assertSame(0, ClubAccountingPeriod::count());
    }

    public function test_period_transaction_rolls_back_when_model_creation_fails(): void
    {
        $fixture = $this->fixture('Q');
        ClubAccountingPeriod::creating(fn () => throw new RuntimeException('Synthetic period failure.'));

        try {
            $this->periods()->provision($this->periodRequest($fixture));
            $this->fail('Synthetic creation failure should escape.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic period failure.', $exception->getMessage());
        }

        $this->assertSame(0, ClubAccountingPeriod::count());
    }

    public function test_provisioning_exposes_no_route_or_existing_workflow_mutation_path(): void
    {
        $financeUris = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_contains($uri, 'ledger-account') || str_contains($uri, 'accounting-period'));

        $this->assertSame([], $financeUris->values()->all());
        $this->assertSame(0, AuditLog::count());
        $this->assertSame(0, ClubJournalEntry::count());
        $this->assertSame(0, ClubJournalLine::count());
    }

    private function accounts(): LedgerAccountProvisioningService
    {
        return app(LedgerAccountProvisioningService::class);
    }

    private function periods(): ClubAccountingPeriodProvisioningService
    {
        return app(ClubAccountingPeriodProvisioningService::class);
    }

    private function fixture(string $suffix): array
    {
        $club = Club::create(['name' => "Provisioning {$suffix}", 'code' => "P{$suffix}"]);
        $actor = User::factory()->create();

        return compact('club', 'actor');
    }

    private function accountRequest(array $fixture, array $overrides = []): LedgerAccountProvisioningRequest
    {
        return new LedgerAccountProvisioningRequest(
            clubId: $overrides['clubId'] ?? $fixture['club']->id,
            code: $overrides['code'] ?? 'CASH-ON-HAND',
            name: $overrides['name'] ?? 'Cash on hand',
            accountType: $overrides['accountType'] ?? LedgerAccount::TYPE_ASSET,
            normalBalance: $overrides['normalBalance'] ?? LedgerAccount::NORMAL_DEBIT,
            currency: $overrides['currency'] ?? LedgerAccount::CURRENCY_INR,
            createdByUserId: $overrides['createdByUserId'] ?? $fixture['actor']->id,
        );
    }

    private function periodRequest(array $fixture, array $overrides = []): ClubAccountingPeriodProvisioningRequest
    {
        return new ClubAccountingPeriodProvisioningRequest(
            clubId: $overrides['clubId'] ?? $fixture['club']->id,
            lionsYear: $overrides['lionsYear'] ?? '2026-27',
            createdByUserId: $overrides['createdByUserId'] ?? $fixture['actor']->id,
        );
    }
}
