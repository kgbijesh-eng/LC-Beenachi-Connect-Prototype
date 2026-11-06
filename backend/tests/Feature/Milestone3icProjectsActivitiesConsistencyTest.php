<?php

namespace Tests\Feature;

use App\Models\{Club, ClubMembership, ClubProject, Role, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3icProjectsActivitiesConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_and_report_date_filters_share_the_effective_start_date(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::create(['name' => '3IC Club', 'code' => '3IC-'.uniqid(), 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        $user = User::factory()->create();
        ClubMembership::create([
            'club_id' => $club->id, 'user_id' => $user->id,
            'role_id' => Role::where('slug', 'president-admin')->firstOrFail()->id,
            'status' => 'active', 'joined_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $active = $this->project($club, [
            'record_type' => 'activity', 'title' => 'Active July Activity', 'status' => 'active',
            'planned_start_date' => '2026-01-10', 'actual_start_date' => '2026-07-05',
        ]);
        $this->project($club, [
            'title' => 'Planned January Project', 'status' => 'planned', 'planned_start_date' => '2026-01-15',
        ]);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/projects?from=2026-07-01&to=2026-07-31")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $active->id);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/projects-register?date_from=2026-07-01&date_to=2026-07-31")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.rows.0.id', $active->id);
    }

    private function project(Club $club, array $overrides): ClubProject
    {
        return ClubProject::create(array_merge([
            'club_id' => $club->id, 'record_type' => 'project', 'title' => '3IC Record',
            'visibility' => 'members', 'status' => 'planned', 'planned_at' => now(), 'lock_version' => 1,
        ], $overrides));
    }
}
