<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class OfficerTermTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame(':memory:', DB::connection()->getDatabaseName());
            $this->assertEmpty(DB::connection()->getConfig('url'));
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        } else {
            FinancialWorkerSafety::assert(['lcb_p02b3_finance_test']);
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SET TIME ZONE 'UTC'");
        }
    }

    protected function club(bool $active = true): Club
    {
        return Club::create(['name' => 'Synthetic club', 'code' => (string) Str::uuid(), 'is_active' => $active]);
    }

    protected function membership(Club $club, string $role = 'member', string $status = 'active'): ClubMembership
    {
        return ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => User::factory()->create()->id,
            'role_id' => Role::firstOrCreate(['slug' => $role], ['name' => $role])->id,
            'status' => $status,
        ]);
    }

    protected function term(ClubMembership $holder, array $attributes = []): array
    {
        return $attributes + [
            'club_id' => $holder->club_id,
            'membership_id' => $holder->id,
            'office' => 'president',
            'starts_at' => '2027-01-01 00:00:00',
            'ends_at' => '2027-02-01 00:00:00',
            'recorded_at' => '2027-01-01 00:00:00',
            'appointment_reference' => (string) Str::uuid(),
            'approved_by_user_id' => $holder->user_id,
            'confirmed_by_user_id' => User::factory()->create()->id,
            'recorded_by_user_id' => $holder->user_id,
        ];
    }
}
