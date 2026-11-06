<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganisationTransactionGuardBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_framework_managed_test_transaction_has_no_implicit_guard_bypass(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $depth = DB::transactionLevel();
        $this->assertGreaterThan(0, $depth);
        $club = Club::create(['name' => 'Synthetic', 'code' => 'SYN', 'is_active' => true]);
        config(['instance.organisation_code' => 'SYN']);
        try {
            app(OrganisationTransactionGuard::class)->run($club, fn () => $this->fail('No test-environment bypass'));
            $this->fail('Unknown framework transaction must be rejected.');
        } catch (OrganisationAccessRejected $exception) {
            $this->assertSame('ORGANISATION_TRANSACTION_UNSUPPORTED', $exception->reason);
        }
        $this->assertSame($depth, DB::transactionLevel());
        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
    }

    #[DataProvider('rejections')]
    public function test_new_typed_rejections_render_generic_api_contracts(string $factory, int $status, array $body): void
    {
        Route::get('api/b1-typed-rejection', fn () => throw OrganisationAccessRejected::$factory());
        $this->getJson('/api/b1-typed-rejection')->assertStatus($status)->assertExactJson($body);
    }

    public static function rejections(): array
    {
        return [
            ['suspended', 403, ['message' => 'Organisation access is suspended.', 'code' => 'ORGANISATION_SUSPENDED']],
            ['foreign', 404, ['message' => 'Not found.']],
            ['unavailable', 503, ['message' => 'Organisation configuration is unavailable.']],
            ['unsupported', 409, ['message' => 'A supported outer organisation transaction is required.', 'code' => 'ORGANISATION_TRANSACTION_UNSUPPORTED']],
            ['invalidContext', 409, ['message' => 'Organisation transaction context is no longer valid.', 'code' => 'ORGANISATION_TRANSACTION_CONTEXT_INVALID']],
        ];
    }

    public function test_sqlite_cannot_issue_a_postgres_transaction_proof_even_without_ambient_transaction(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.b1_sqlite_clean' => config('database.connections.sqlite')]);
        DB::setDefaultConnection('b1_sqlite_clean');
        try {
            $this->assertSame(0, DB::transactionLevel());
            app(OrganisationTransactionGuard::class)->run(1, fn () => $this->fail('SQLite is not row-lock proof'));
            $this->fail('Unsupported driver must reject.');
        } catch (OrganisationAccessRejected $exception) {
            $this->assertSame('ORGANISATION_TRANSACTION_UNSUPPORTED', $exception->reason);
            $this->assertSame(0, DB::transactionLevel());
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('b1_sqlite_clean');
        }
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
