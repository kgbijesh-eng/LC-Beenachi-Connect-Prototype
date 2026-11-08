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
use Tests\Support\FinancialOperationFixture;
use Tests\Support\FinancialPostgresTestCase;
use Tests\Support\SourceContextFixture;

class SourceTransactionContextMatrixTest extends FinancialPostgresTestCase
{
    public static function contracts(): array
    {
        $cases = [];
        foreach (SourceContextFixture::methods() as $method) {
            foreach (['active', 'forged', 'committed', 'rolledback', 'foreign', 'ambient'] as $state) {
                $cases[$method.'-'.$state] = [$method, $state];
            }
        }

        return $cases;
    }

    #[DataProvider('contracts')]
    public function test_every_source_mutation_and_replay_requires_an_issued_live_context(string $method, string $state): void
    {
        $p = SourceContextFixture::prepare($method);
        $club = Club::findOrFail($p['club']);
        $before = FinancialOperationFixture::snapshot($p['club']);
        $guard = app(OrganisationTransactionGuard::class);
        if ($state === 'active') {
            $guard->run($club, fn ($context) => SourceContextFixture::invoke($method, $p, $context));
        } elseif ($state === 'ambient') {
            DB::transaction(function () use ($method, $p): void {
                $this->rejected(fn () => SourceContextFixture::legacy($method, $p), 'ORGANISATION_TRANSACTION_UNSUPPORTED');
                $this->assertSame(1, DB::transactionLevel());
            });
        } elseif ($state === 'foreign') {
            $foreign = Club::create(['name' => 'Foreign synthetic', 'code' => 'FOREIGN']);
            config(['instance.organisation_code' => $foreign->code]);
            $this->rejected(fn () => $guard->run($foreign, fn ($context) => SourceContextFixture::invoke($method, $p, $context)), 'ORGANISATION_FOREIGN');
        } else {
            $context = new OrganisationTransactionContext($club->id, $guard);
            if ($state !== 'forged') {
                try {
                    $guard->run($club, function ($issued) use (&$context, $state): void {
                        $context = $issued;
                        if ($state === 'rolledback') {
                            throw new RuntimeException('controlled context rollback');
                        }
                    });
                } catch (RuntimeException $e) {
                    $this->assertSame('controlled context rollback', $e->getMessage());
                }
            }
            $this->rejected(fn () => SourceContextFixture::invoke($method, $p, $context), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, FinancialOperationFixture::snapshot($p['club']));
    }

    private function rejected(callable $operation, string $reason): void
    {
        try {
            $operation();
            $this->fail('Unsupported/foreign/expired context must never be admitted.');
        } catch (OrganisationAccessRejected $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}
