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
use Illuminate\Support\Str;
use LogicException;
use App\Services\Access\{OrganisationAccessRejected, OrganisationTransactionGuard, OrganisationTransactionContext};
use Illuminate\Support\Facades\DB;
use App\Services\Finance\{JournalReversalService, JournalReversalRequest};
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FinancialPostgresTestCase;
use TypeError;


class FinancialOrganisationEnforcementTest extends FinancialPostgresTestCase
{
    public static function rejectedStates(): array
    {
        return [['suspended', 'ORGANISATION_SUSPENDED'], ['foreign', 'ORGANISATION_FOREIGN'], ['missing', 'ORGANISATION_CONFIGURATION_UNAVAILABLE'], ['invalid', 'ORGANISATION_CONFIGURATION_UNAVAILABLE']];
    }

    #[DataProvider('rejectedStates')]
    public function test_standalone_and_replay_recheck_authoritative_eligibility(string $state, string $reason): void
    {
        $f = $this->fixture(); $request = $this->request($f);
        $entry = app(ClubJournalPostingService::class)->post($request);
        if ($state === 'suspended') Club::whereKey($f['club']->id)->update(['is_active' => false]);
        if ($state === 'foreign') { $other = Club::create(['name' => 'Other synthetic', 'code' => 'OTHER']); config(['instance.organisation_code' => $other->code]); }
        if ($state === 'missing') config(['instance.organisation_code' => 'ABSENT']);
        if ($state === 'invalid') config(['instance.organisation_code' => null]);
        $this->rejected(fn () => app(ClubJournalPostingService::class)->post($request), $reason);
        $this->rejected(fn () => app(JournalReversalService::class)->reverse(new JournalReversalRequest($f['club']->id, $entry->id, $f['period']->id, '2026-07-16', $f['actor']->id, 'Synthetic reversal', 'test', 'one', 'reverse', (string) Str::uuid())), $reason);
        $this->assertSame(1, ClubJournalEntry::count()); $this->assertSame(2, ClubJournalLine::count());
        $this->assertSame('posted', $entry->fresh()->status); $this->assertSame(0, DB::transactionLevel());
    }

    public function test_nested_post_and_reversal_keep_savepoints_and_outer_atomicity(): void
    {
        $f = $this->fixture(); $request = $this->request($f); $issued = null; $depths = [];
        DB::listen(function ($query) use (&$depths) { if (str_contains($query->sql, 'insert into "club_journal_entries"')) $depths[] = DB::transactionLevel(); });
        try {
            app(OrganisationTransactionGuard::class)->run($f['club'], function ($context) use ($f, $request, &$issued) {
                $issued = $context;
                $first = app(ClubJournalPostingService::class)->postWithin($context, $request);
                $this->assertSame($first->id, app(ClubJournalPostingService::class)->postWithin($context, $request)->id);
                $reverse = app(JournalReversalService::class)->reverseWithin($context, new JournalReversalRequest($f['club']->id, $first->id, $f['period']->id, '2026-07-16', $f['actor']->id, 'Synthetic rollback', 'test', 'one', 'reverse', (string) Str::uuid()));
                $this->assertSame($first->id, $reverse->reversal_of_entry_id);
                $this->assertSame(1, DB::transactionLevel());
                throw new RuntimeException('Controlled root rollback');
            });
            $this->fail('Rollback must propagate');
        } catch (RuntimeException $e) { $this->assertSame('Controlled root rollback', $e->getMessage()); }
        $this->assertSame([2, 3], $depths);
        $this->assertSame(0, ClubJournalEntry::count()); $this->assertSame(0, ClubJournalLine::count());
        $this->rejected(fn () => app(ClubJournalPostingService::class)->postWithin($issued, $request), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
    }

    public function test_forged_expired_foreign_and_ambient_contexts_are_rejected(): void
    {
        $f = $this->fixture(); $request = $this->request($f); $guard = app(OrganisationTransactionGuard::class);
        $forged = new OrganisationTransactionContext($f['club']->id, $guard);
        $this->rejected(fn () => app(ClubJournalPostingService::class)->postWithin($forged, $request), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
        $issued = null;
        $guard->run($f['club'], function ($context) use (&$issued, $request) {
            $issued = $context;
            $this->rejected(fn () => app(ClubJournalPostingService::class)->post($request), 'ORGANISATION_TRANSACTION_UNSUPPORTED');
            $foreign = new JournalPostingRequest(PHP_INT_MAX, $request->accountingPeriodId, $request->effectiveDate, $request->description, $request->currency, $request->sourceType, $request->sourceId, $request->sourceEvent, $request->idempotencyKey, $request->postingActorUserId, $request->lines);
            $this->rejected(fn () => app(ClubJournalPostingService::class)->postWithin($context, $foreign), 'ORGANISATION_FOREIGN');
        });
        $this->rejected(fn () => app(ClubJournalPostingService::class)->postWithin($issued, $request), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
        DB::transaction(fn () => $this->rejected(fn () => app(ClubJournalPostingService::class)->post($request), 'ORGANISATION_TRANSACTION_UNSUPPORTED'));
        $this->assertSame(0, ClubJournalEntry::count());
    }

    private function rejected(callable $call, string $reason): void
    {
        try { $call(); $this->fail('Expected organisation rejection'); }
        catch (OrganisationAccessRejected $e) { $this->assertSame($reason, $e->reason); }
    }
    private function fixture(string $clubCode = 'LCB'): array
    {
        $club = Club::create(['name' => "Posting Test {$clubCode}", 'code' => $clubCode.'-'.Str::lower(Str::random(6))]);
        config(['instance.organisation_code' => $club->code]);
        $club->update(['code' => $clubCode]);
        config(['instance.organisation_code' => $club->code]);
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
                new JournalLineInput($fixture['cash']->id, 'debit', 12550, 'Cash received', 'GENERAL_CLUB_FUND'),
                new JournalLineInput($fixture['income']->id, 'credit', 12550, 'Income recognized', 'GENERAL_CLUB_FUND'),
            ],
        );
    }
}
