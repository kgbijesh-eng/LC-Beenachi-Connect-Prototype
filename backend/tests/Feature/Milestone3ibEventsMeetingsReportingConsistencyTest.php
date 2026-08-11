<?php

namespace Tests\Feature;

use App\Models\{Club, ClubEvent, ClubMembership, Role, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3ibEventsMeetingsReportingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_operational_and_report_scopes_share_the_effective_end_boundary(): void
    {
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-07-05 10:00:00 UTC');
        $club = Club::create(['name' => '3IB Club', 'code' => '3IB-'.uniqid(), 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        $user = User::factory()->create();
        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => Role::where('slug', 'president-admin')->firstOrFail()->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $ongoing = $this->event($club, 'Ongoing Event', '2026-07-05 09:00:00', '2026-07-05 11:00:00');
        $ended = $this->event($club, 'Ended Meeting', '2026-07-05 08:00:00', '2026-07-05 09:00:00', 'meeting');

        $this->getJson("/api/v1/clubs/{$club->id}/events?scope=upcoming")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ongoing->id);
        $this->getJson("/api/v1/clubs/{$club->id}/events?scope=past")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ended->id);

        $this->getJson("/api/v1/clubs/{$club->id}/reports/events-register?scope=upcoming")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.rows.0.id', $ongoing->id);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/events-register?scope=past")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.rows.0.id', $ended->id);
    }

    public function test_report_scope_rejects_unknown_periods(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::create(['name' => '3IB Validation', 'code' => '3IB-V-'.uniqid(), 'timezone' => 'UTC', 'is_active' => true]);
        $user = User::factory()->create();
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $user->id, 'role_id' => Role::where('slug', 'secretary')->firstOrFail()->id, 'status' => 'active', 'joined_at' => now()]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/clubs/{$club->id}/reports/events-register?scope=tomorrow")->assertUnprocessable();
    }

    private function event(Club $club, string $title, string $start, string $end, string $type = 'event'): ClubEvent
    {
        return ClubEvent::create([
            'club_id' => $club->id, 'type' => $type, 'title' => $title, 'is_all_day' => false,
            'starts_at' => $start, 'ends_at' => $end, 'status' => 'published', 'visibility' => 'members', 'lock_version' => 1,
        ]);
    }
}
