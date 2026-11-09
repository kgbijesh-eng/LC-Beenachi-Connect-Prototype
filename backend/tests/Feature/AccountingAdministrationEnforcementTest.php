<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\AccountingAdministrationFixture as Fixture;
use Tests\Support\FinancialPostgresTestCase;

class AccountingAdministrationEnforcementTest extends FinancialPostgresTestCase
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
            if (str_ends_with($action, '-replay')) {
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
        foreach (['account', 'period', 'close'] as $action) {
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
