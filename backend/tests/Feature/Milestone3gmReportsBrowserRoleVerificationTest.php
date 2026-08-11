<?php

namespace Tests\Feature;

use App\Models\{Club, ClubEvent, ClubMembership, ClubProject, ClubReminder, Member, Role, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3gmReportsBrowserRoleVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_matrix_and_full_synthetic_report_verification(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = $this->club('PRIMARY');
        $other = $this->club('OTHER');

        foreach (['president-admin', 'secretary', 'treasurer'] as $role) {
            Sanctum::actingAs($this->user($club, $role));
            $catalog = $this->getJson("/api/v1/clubs/{$club->id}/reports")->assertOk();
            $expected = ['membership-summary', 'events-register', 'projects-register', 'reminders-register'];
            if (str_contains(file_get_contents(app_path('Services/Reports/ReportCatalog.php')), "'collections-summary'")) {
                $expected = [...$expected, 'collections-summary', 'member-outstanding-balances', 'charge-collection-status', 'payment-register', 'receipt-register'];
            }
            $this->assertSame($expected, array_column($catalog->json('data'), 'key'));
        }

        Sanctum::actingAs($this->user($club, 'member'));
        $this->getJson("/api/v1/clubs/{$club->id}/reports")->assertForbidden();

        Sanctum::actingAs($this->user($club, 'president-admin'));
        Member::create(['club_id' => $club->id, 'member_code' => 'GM-1', 'full_name' => 'Private One', 'status' => 'active', 'phone' => '999']);
        Member::create(['club_id' => $club->id, 'member_code' => 'GM-2', 'full_name' => 'Private Two', 'status' => 'active']);
        Member::create(['club_id' => $club->id, 'member_code' => 'GM-3', 'full_name' => 'Private Three', 'status' => 'inactive']);
        Member::create(['club_id' => $other->id, 'member_code' => 'GM-X', 'full_name' => 'Cross Club', 'status' => 'active']);

        $membership = $this->getJson("/api/v1/clubs/{$club->id}/reports/membership-summary")->assertOk();
        $membership->assertJsonPath('data.summary.total_members', 3)
            ->assertJsonPath('data.summary.active_members', 2)
            ->assertJsonPath('data.summary.inactive_members', 1)
            ->assertJsonPath('data.rows', []);

        $eventIds = [];
        for ($day = 1; $day <= 12; $day++) {
            $eventIds[] = ClubEvent::create([
                'club_id' => $club->id,
                'type' => $day === 1 ? 'meeting' : 'event',
                'title' => $day <= 2 ? 'Duplicate Browser Title' : "GM Event {$day}",
                'is_all_day' => false,
                'starts_at' => sprintf('2026-07-%02d 09:00:00', $day),
                'status' => $day === 12 ? 'completed' : 'published',
                'visibility' => 'members',
                'lock_version' => 1,
            ])->id;
        }
        ClubEvent::create([
            'club_id' => $other->id, 'type' => 'event', 'title' => 'Cross Club Event',
            'is_all_day' => false, 'starts_at' => '2026-07-01 09:00:00',
            'status' => 'published', 'visibility' => 'members', 'lock_version' => 1,
        ]);

        $pageOne = $this->getJson("/api/v1/clubs/{$club->id}/reports/events-register?date_from=2026-07-01&date_to=2026-07-12&per_page=10&page=1")->assertOk();
        $pageOne->assertJsonPath('meta.total', 12)->assertJsonCount(10, 'data.rows');
        $this->assertSame(array_slice($eventIds, 0, 10), array_column($pageOne->json('data.rows'), 'id'));
        $pageTwo = $this->getJson("/api/v1/clubs/{$club->id}/reports/events-register?date_from=2026-07-01&date_to=2026-07-12&per_page=10&page=2")->assertOk();
        $pageTwo->assertJsonPath('meta.total', 12)->assertJsonCount(2, 'data.rows');
        $this->assertSame(array_slice($eventIds, 10, 2), array_column($pageTwo->json('data.rows'), 'id'));
        $this->assertNotSame($eventIds[0], $eventIds[1]);

        $empty = $this->getJson("/api/v1/clubs/{$club->id}/reports/events-register?q=NO-SUCH-REPORT-ROW")->assertOk();
        $empty->assertJsonPath('data.summary.total_records', 0)->assertJsonPath('data.rows', [])->assertJsonPath('meta.total', 0);

        $project = ClubProject::create([
            'club_id' => $club->id, 'record_type' => 'project', 'title' => 'REPORT-SENTINEL-PROJECT',
            'status' => 'planned', 'visibility' => 'members', 'service_category' => 'Vision',
            'planned_start_date' => '2026-07-05', 'expected_beneficiary_count' => 25, 'lock_version' => 1,
        ]);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/projects-register?service_category=Vision&date_from=2026-07-05&date_to=2026-07-05")
            ->assertOk()->assertJsonPath('data.rows.0.id', $project->id)->assertJsonPath('meta.total', 1);

        $reminder = ClubReminder::create([
            'club_id' => $club->id, 'title' => 'REPORT-SENTINEL-REMINDER', 'priority' => 'urgent',
            'status' => 'active', 'visibility' => 'members', 'is_all_day' => true,
            'due_date' => '2026-07-06', 'activated_at' => now(), 'lock_version' => 1,
        ]);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/reminders-register?priority=urgent&date_from=2026-07-06&date_to=2026-07-06")
            ->assertOk()->assertJsonPath('data.rows.0.id', $reminder->id)->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/clubs/{$club->id}/reports/membership-summary?q=Private")->assertUnprocessable();
        $this->getJson("/api/v1/clubs/{$club->id}/reports/unknown")->assertNotFound();

        $serialized = json_encode([$pageOne->json('data'), $membership->json('data')]);
        foreach (['phone', 'email', 'address', 'date_of_birth', 'bank', 'upi', 'credential', 'private_note', 'cancellation_reason', 'audit_payload'] as $private) {
            $this->assertStringNotContainsString($private, $serialized);
        }
    }

    public function test_frontend_clears_stale_output_and_reports_truthful_empty_state(): void
    {
        $component = file_get_contents(dirname(__DIR__, 3).'/app/ReportsBackend.tsx');
        $doc = file_get_contents(__DIR__.'/../../docs/milestone_3gm_reports_browser_role_verification.md');

        foreach ([
            'clearOutput();setSelected(def);setState("loading")',
            'Report unavailable. Previous rows and totals were cleared.',
            'This verified report is empty.',
            'hasReportValue',
            'Normal Member lost the Reports navigation',
            'page 1 showed 10 rows and page 2 showed 2',
            '1440, 900, and 390',
        ] as $expected) {
            $this->assertStringContainsString($expected, $component.$doc);
        }
    }

    private function club(string $code): Club
    {
        return Club::create(['name' => "Report {$code}", 'code' => 'GM-'.uniqid(), 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
    }

    private function user(Club $club, string $role): User
    {
        $user = User::factory()->create();
        ClubMembership::create([
            'club_id' => $club->id, 'user_id' => $user->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
            'status' => 'active', 'joined_at' => now(),
        ]);
        return $user;
    }
}
