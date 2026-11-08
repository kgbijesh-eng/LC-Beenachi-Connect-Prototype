<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingIntegrationProposalDecision;
use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubMembership;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\Exceptions\AccountingIntegrationConflictException;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FinancialPostgresTestCase;
use Tests\Support\IntegrationConfigurationFixture as Fixture;

class IntegrationConfigurationEnforcementTest extends FinancialPostgresTestCase
{
    public static function boundaries(): array
    {
        $cases = [];
        foreach (Fixture::actions() as $action) {
            foreach (['active', 'suspended', 'foreign', 'missing', 'invalid'] as $state) {
                $cases[$action.'-'.$state] = [$action, $state];
            }
        }

        return $cases;
    }

    #[DataProvider('boundaries')]
    public function test_standalone_mutations_and_replay_require_fresh_eligibility(string $action, string $state): void
    {
        $p = Fixture::prepare($action);
        $before = Fixture::snapshot($p['club']);
        if ($state === 'suspended') {
            Club::whereKey($p['club'])->update(['is_active' => false]);
        }
        if ($state === 'foreign') {
            $other = Club::create(['name' => 'Foreign', 'code' => 'FOREIGN', 'is_active' => true]);
            config(['instance.organisation_code' => $other->code]);
        }
        if ($state === 'missing') {
            config(['instance.organisation_code' => null]);
        }
        if ($state === 'invalid') {
            config(['instance.organisation_code' => 'UNKNOWN']);
        }
        if ($state === 'active') {
            $this->assertNotNull(Fixture::perform($p));
            if ($action === 'propose-replay') {
                $this->assertSame($before, Fixture::snapshot($p['club']));
            }
        } else {
            $this->rejected(fn () => Fixture::perform($p), match ($state) {
                'suspended' => 'ORGANISATION_SUSPENDED', 'foreign' => 'ORGANISATION_FOREIGN', default => 'ORGANISATION_CONFIGURATION_UNAVAILABLE'
            });
            $this->assertSame($before, Fixture::snapshot($p['club']));
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before['club_journal_entries'], Fixture::snapshot($p['club'])['club_journal_entries']);
    }

    public static function contexts(): array
    {
        $cases = [];
        foreach (['map', 'activate', 'revokeMapping', 'revokeActivation'] as $action) {
            foreach (['active', 'forged', 'committed', 'rolledback', 'foreign', 'ambient', 'nested-standalone'] as $state) {
                $cases[$action.'-'.$state] = [$action, $state];
            }
        }

        return $cases;
    }

    #[DataProvider('contexts')]
    public function test_explicit_context_and_savepoint_contract(string $action, string $state): void
    {
        $p = Fixture::prepare($action);
        $club = Club::findOrFail($p['club']);
        $before = Fixture::snapshot($p['club']);
        $guard = app(OrganisationTransactionGuard::class);
        if ($state === 'active') {
            $depths = [];
            DB::listen(function ($q) use (&$depths): void {
                if (str_contains($q->sql, 'for update')) {
                    $depths[] = DB::transactionLevel();
                }
            });
            $guard->run($club, fn ($ctx) => Fixture::perform($p, $ctx), 1);
            $this->assertSame(1, $depths[0]);
            $this->assertContains(2, $depths);
        } elseif ($state === 'ambient') {
            DB::transaction(fn () => $this->rejected(fn () => Fixture::perform($p), 'ORGANISATION_TRANSACTION_UNSUPPORTED'), 1);
        } elseif ($state === 'nested-standalone') {
            $guard->run($club, fn () => $this->rejected(fn () => Fixture::perform($p), 'ORGANISATION_TRANSACTION_UNSUPPORTED'), 1);
        } elseif ($state === 'foreign') {
            $other = Club::create(['name' => 'Other', 'code' => 'OTHER']);
            config(['instance.organisation_code' => $other->code]);
            $guard->run($other, fn ($ctx) => $this->rejected(fn () => Fixture::perform($p, $ctx), 'ORGANISATION_FOREIGN'), 1);
        } else {
            $context = new OrganisationTransactionContext($club->id, $guard);
            if ($state !== 'forged') {
                try {
                    $guard->run($club, function ($ctx) use (&$context, $state): void {
                        $context = $ctx;
                        if ($state === 'rolledback') {
                            throw new RuntimeException('controlled rollback');
                        }
                    }, 1);
                } catch (RuntimeException $e) {
                    $this->assertSame('controlled rollback', $e->getMessage());
                }
            }
            $this->rejected(fn () => Fixture::perform($p, $context), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
        }
        if ($state !== 'active') {
            $this->assertSame($before, Fixture::snapshot($p['club']));
        }
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function failurePoints(): array
    {
        return [['configuration'], ['decision'], ['audit']];
    }

    #[DataProvider('failurePoints')]
    public function test_approval_failure_rolls_back_configuration_decision_and_audit(string $point): void
    {
        $p = Fixture::prepare('approve');
        $before = Fixture::snapshot($p['club']);
        $class = match ($point) {
            'configuration' => ClubAccountingSourceMapping::class, 'decision' => ClubAccountingIntegrationProposalDecision::class, default => AuditLog::class
        };
        $fired = false;
        $class::created(function () use (&$fired): void {
            $fired = true;
            throw new RuntimeException('controlled B4 failure');
        });
        try {
            Fixture::perform($p);
            $this->fail('Injected failure must escape.');
        } catch (RuntimeException $e) {
            $this->assertSame('controlled B4 failure', $e->getMessage());
        }
        $this->assertTrue($fired);
        $this->assertSame($before, Fixture::snapshot($p['club']));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_approval_revalidates_proposer_authority_without_consuming_decision(): void
    {
        $p = Fixture::prepare('approve');
        $before = Fixture::snapshot($p['club']);
        ClubMembership::where('club_id', $p['club'])->where('user_id', $p['actor'])->update(['status' => 'inactive']);
        try {
            Fixture::perform($p);
            $this->fail('Changed proposer authority must conflict.');
        } catch (AccountingIntegrationConflictException $e) {
            $this->assertSame('The proposer appointment context changed.', $e->getMessage());
        }
        $this->assertSame($before, Fixture::snapshot($p['club']));
    }

    public static function oneAttempt(): array
    {
        return array_map(fn ($a) => [$a], array_values(array_diff(Fixture::actions(), ['propose-replay'])));
    }

    #[DataProvider('oneAttempt')]
    public function test_genuine_serialization_abort_never_broadens_one_attempt_budget(string $action): void
    {
        $p = Fixture::prepare($action);
        $before = Fixture::snapshot($p['club']);
        $roots = 0;
        $fired = false;
        Event::listen(TransactionBeginning::class, function () use (&$roots): void {
            if (DB::transactionLevel() === 1) {
                $roots++;
            }
        });
        DB::listen(function ($q) use (&$fired): void {
            if (! $fired && (str_starts_with($q->sql, 'insert into') || str_starts_with($q->sql, 'update '))) {
                $fired = true;
                DB::unprepared("DO $$ BEGIN RAISE EXCEPTION 'controlled B4 serialization' USING ERRCODE = '40001'; END $$");
            }
        });
        try {
            Fixture::perform($p);
            $this->fail('Native serialization abort must escape.');
        } catch (\Throwable $e) {
            $this->assertTrue($fired);
            $this->assertStringContainsString('controlled B4 serialization', $e->getMessage());
        }
        $this->assertSame(1, $roots);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, Fixture::snapshot($p['club']));
    }

    private function rejected(callable $operation, string $reason): void
    {
        try {
            $operation();
            $this->fail('Eligibility/context rejection required.');
        } catch (OrganisationAccessRejected $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}
