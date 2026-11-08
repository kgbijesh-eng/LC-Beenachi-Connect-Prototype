<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\DisposableCorruptionFixture;
use Tests\Support\FinancialPostgresTestCase;

class DisposableCorruptionFixtureUncertainTest extends FinancialPostgresTestCase
{
    public function test_unprovable_restoration_discards_the_cluster_without_business_assertions(): void
    {
        [$club, $one] = $this->fixture();
        $fired = false;
        DB::listen(function ($q) use (&$fired): void {
            if (! $fired && str_contains($q->sql, ' ENABLE TRIGGER ')) {
                $fired = true;
                throw new RuntimeException('controlled enable acknowledgement loss');
            }
        });
        DB::connection()->beforeExecuting(function ($sql) use (&$fired): void {
            if ($fired && str_contains($sql, 'b3 fixture trigger snapshot')) {
                throw new RuntimeException('controlled catalogue verification failure');
            }
        });
        try {
            (new DisposableCorruptionFixture)->corrupt($club, [$this->deletion($one)]);
            $this->fail('Unproven restoration must abort.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fixture cluster discarded: restoration/outcome uncertain.', $e->getMessage());
        }
        $this->assertTrue($fired);
        $this->assertFileExists(getenv('B3_FIXTURE_POISON_FILE'));
        $this->assertTrue(json_decode(file_get_contents(getenv('B3_FIXTURE_POISON_FILE')), true, flags: JSON_THROW_ON_ERROR)['discard']);
        // Deliberately no more database or business-service operations in this process.
    }

    public function test_post_commit_failure_discards_instead_of_claiming_rollback(): void
    {
        [$club, $one] = $this->fixture();
        Event::listen(TransactionCommitted::class, function (): void {
            if (DB::transactionLevel() === 0) {
                throw new RuntimeException('controlled committed acknowledgement loss');
            }
        });
        try {
            (new DisposableCorruptionFixture)->corrupt($club, [$this->deletion($one)]);
            $this->fail('A committed fixture failure must not claim rollback.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fixture cluster discarded: restoration/outcome uncertain.', $e->getMessage());
        }
        $this->assertFileExists(getenv('B3_FIXTURE_POISON_FILE'));
        $this->assertTrue(json_decode(file_get_contents(getenv('B3_FIXTURE_POISON_FILE')), true, flags: JSON_THROW_ON_ERROR)['discard']);
        // The cluster is retired; no further database/business operations are permitted.
    }

    private function fixture(): array
    {
        $club = Club::create(['name' => 'Disposable corruption', 'code' => 'CORRUPT', 'is_active' => true]);
        config(['instance.organisation_code' => $club->code]);
        $actor = User::factory()->create();
        $row = fn () => DB::table('club_accounting_source_coverage_decisions')->insertGetId(['public_id' => (string) Str::uuid(), 'club_id' => $club->id, 'source_type' => 'member_charge', 'source_identifier' => (string) Str::uuid(), 'accounting_date' => '2027-07-05', 'integration_required' => false, 'integration_activation_id' => null, 'terminal_activation_event_id' => null, 'decision_reason' => 'not_activated', 'decided_by_user_id' => $actor->id, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return [$club, $row(), $row()];
    }

    private function deletion(int $id): array
    {
        return ['table' => 'club_accounting_source_coverage_decisions', 'id' => $id, 'delete' => true];
    }
}
