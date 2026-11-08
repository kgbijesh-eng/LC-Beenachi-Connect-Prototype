<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use Illuminate\Support\Facades\DB;
use Tests\Support\FinancialPostgresTestCase;

class FinancialTransactionFoundationTest extends FinancialPostgresTestCase
{
    public function test_fixtures_are_committed_outside_business_roots_and_rollback_is_real(): void
    {
        $club = Club::create(['code' => 'B3-ROOT', 'name' => 'Synthetic finance root', 'is_active' => true]);
        config(['instance.organisation_code' => $club->code]);
        $this->assertSame(0, DB::transactionLevel());
        $issued = null;
        try {
            app(OrganisationTransactionGuard::class)->run($club, function (OrganisationTransactionContext $context) use (&$issued): void {
                $issued = $context;
                $this->assertSame(1, DB::transactionLevel());
                $context->assertUsable();
                User::factory()->create();
                throw new \RuntimeException('Controlled business rollback');
            });
            $this->fail('The root must propagate business failure.');
        } catch (\RuntimeException $e) { $this->assertSame('Controlled business rollback', $e->getMessage()); }
        $this->assertSame(1, Club::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::transactionLevel());
        $this->expectException(OrganisationAccessRejected::class);
        $issued->assertUsable();
    }
}
