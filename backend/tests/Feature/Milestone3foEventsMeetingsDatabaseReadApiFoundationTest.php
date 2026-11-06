<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubEvent;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3foEventsMeetingsDatabaseReadApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFIED_3FO_COMMIT = '360c6f6a60f91bf406ce71101d17e23228ad3176';

    private string $docPath = __DIR__ . '/../../docs/milestone_3fo_events_meetings_database_read_api_foundation.md';
    private string $repoPath = __DIR__ . '/../../..';


    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_document_records_3fo_scope_and_boundaries(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3FO - Events & Meetings Database and Read API Foundation',
            '02206652d88317b94c025b29d5ed0a205f107ff8',
            'implements only the Events & Meetings database foundation and read API foundation',
            'It does not implement create, update, publish, cancel, complete',
            '`GET /api/v1/clubs/{club}/events`',
            '`GET /api/v1/clubs/{club}/events/{event}`',
            'No frontend runtime file is edited.',
            'Milestone 3FP may open only after 3FO is committed, pushed, remotely verified, clean',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_club_events_table_can_be_migrated_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('club_events'));

        foreach ([
            'id',
            'club_id',
            'type',
            'title',
            'description',
            'is_all_day',
            'starts_at',
            'ends_at',
            'start_date',
            'end_date',
            'venue',
            'status',
            'visibility',
            'published_at',
            'cancelled_at',
            'cancellation_reason',
            'completed_at',
            'created_by_user_id',
            'updated_by_user_id',
            'lock_version',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('club_events', $column), "Missing column [{$column}].");
        }
    }

    public function test_model_relationships_fillable_and_controlled_values(): void
    {
        $club = $this->createClub();
        $creator = User::factory()->create();
        $updater = User::factory()->create();
        $event = $this->createClubEvent($club, [
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $updater->id,
        ]);

        $this->assertSame([
            'event',
            'meeting',
        ], ClubEvent::types());

        $this->assertSame([
            'draft',
            'published',
            'completed',
            'cancelled',
        ], ClubEvent::statuses());

        $this->assertSame([
            'members',
            'management',
        ], ClubEvent::visibilities());

        $this->assertContains('lock_version', (new ClubEvent())->getFillable());
        $this->assertTrue($event->club->is($club));
        $this->assertTrue($event->creator->is($creator));
        $this->assertTrue($event->updater->is($updater));
    }

    public function test_seeded_permissions_match_3fn_role_matrix(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['president-admin', 'secretary'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $this->assertTrue($role->permissions->contains('slug', 'events.view'));
            $this->assertTrue($role->permissions->contains('slug', 'events.manage'));
        }

        foreach (['treasurer', 'member'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $this->assertTrue($role->permissions->contains('slug', 'events.view'));
            $this->assertFalse($role->permissions->contains('slug', 'events.manage'));
        }
    }

    public function test_timed_and_all_day_representations_are_returned_truthfully(): void
    {
        Carbon::setTestNow('2026-06-23 00:00:00');

        $club = $this->createClub('EVT-TIME', 'Asia/Kolkata');
        $officer = $this->createUserWithRole($club, 'president-admin');
        $timed = $this->createClubEvent($club, [
            'title' => 'Timed Meeting',
            'is_all_day' => false,
            'starts_at' => '2026-06-24 04:30:00',
            'ends_at' => '2026-06-24 05:30:00',
            'start_date' => null,
            'end_date' => null,
        ]);
        $allDay = $this->createClubEvent($club, [
            'title' => 'All Day Event',
            'is_all_day' => true,
            'starts_at' => '2026-06-25 18:30:00',
            'ends_at' => '2026-06-26 18:29:59',
            'start_date' => '2026-06-26',
            'end_date' => '2026-06-26',
        ]);

        Sanctum::actingAs($officer);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/events/{$timed->id}")
            ->assertOk()
            ->assertJsonPath('data.is_all_day', false)
            ->assertJsonPath('data.start_date', null)
            ->assertJsonPath('data.end_date', null)
            ->assertJsonPath('data.timezone', 'Asia/Kolkata');

        $this->getJson("/api/v1/clubs/{$club->id}/events/{$allDay->id}")
            ->assertOk()
            ->assertJsonPath('data.is_all_day', true)
            ->assertJsonPath('data.start_date', '2026-06-26')
            ->assertJsonPath('data.end_date', '2026-06-26')
            ->assertJsonPath('data.timezone', 'Asia/Kolkata');
    }

    public function test_management_roles_can_read_all_same_club_statuses_and_visibilities(): void
    {
        foreach (['president-admin', 'secretary'] as $roleSlug) {
            Carbon::setTestNow('2026-06-23 00:00:00');
            $club = $this->createClub('EVT-MGMT-'.$roleSlug.uniqid());
            $user = $this->createUserWithRole($club, $roleSlug);
            $draft = $this->createClubEvent($club, [
                'title' => 'Management Draft',
                'status' => 'draft',
                'visibility' => 'management',
            ]);

            Sanctum::actingAs($user);

            config(['instance.organisation_code' => $club->code]);
            $this->getJson("/api/v1/clubs/{$club->id}/events?scope=all")
                ->assertOk()
                ->assertJsonPath('data.0.id', $draft->id)
                ->assertJsonPath('data.0.visibility', 'management')
                ->assertJsonPath('data.0.lock_version', 1);

            $this->getJson("/api/v1/clubs/{$club->id}/events/{$draft->id}")
                ->assertOk()
                ->assertJsonPath('data.id', $draft->id)
                ->assertJsonPath('data.status', 'draft');
        }
    }

    public function test_treasurer_and_normal_member_read_only_published_member_visible_records(): void
    {
        foreach (['treasurer', 'member'] as $roleSlug) {
            Carbon::setTestNow('2026-06-23 00:00:00');
            $club = $this->createClub('EVT-READ-'.$roleSlug.uniqid());
            $user = $this->createUserWithRole($club, $roleSlug);
            $published = $this->createClubEvent($club, [
                'title' => 'Published Members Meeting',
                'status' => 'published',
                'visibility' => 'members',
            ]);
            $this->createClubEvent($club, [
                'title' => 'Hidden Draft',
                'status' => 'draft',
                'visibility' => 'members',
            ]);
            $management = $this->createClubEvent($club, [
                'title' => 'Management Only',
                'status' => 'published',
                'visibility' => 'management',
            ]);

            Sanctum::actingAs($user);

            config(['instance.organisation_code' => $club->code]);
            $response = $this->getJson("/api/v1/clubs/{$club->id}/events")
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $published->id);

            $payload = $response->json('data.0');
            $this->assertArrayNotHasKey('visibility', $payload);
            $this->assertArrayNotHasKey('lock_version', $payload);
            $this->assertArrayNotHasKey('cancellation_reason', $payload);

            $this->getJson("/api/v1/clubs/{$club->id}/events/{$management->id}")
                ->assertNotFound();
        }
    }

    public function test_cancelled_published_member_record_is_visible_without_cancellation_reason(): void
    {
        Carbon::setTestNow('2026-06-23 00:00:00');
        $club = $this->createClub();
        $member = $this->createUserWithRole($club, 'member');
        $cancelled = $this->createClubEvent($club, [
            'status' => 'cancelled',
            'visibility' => 'members',
            'published_at' => '2026-06-23 00:30:00',
            'cancelled_at' => '2026-06-23 01:00:00',
            'cancellation_reason' => 'Private management reason.',
        ]);
        $cancelledDraft = $this->createClubEvent($club, [
            'title' => 'Cancelled Draft Hidden From Members',
            'status' => 'cancelled',
            'visibility' => 'members',
            'published_at' => null,
            'cancelled_at' => '2026-06-23 01:00:00',
            'cancellation_reason' => 'Private draft cancellation reason.',
        ]);

        Sanctum::actingAs($member);

        config(['instance.organisation_code' => $club->code]);
        $response = $this->getJson("/api/v1/clubs/{$club->id}/events/{$cancelled->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertArrayNotHasKey('cancellation_reason', $response->json('data'));

        $this->getJson("/api/v1/clubs/{$club->id}/events/{$cancelledDraft->id}")
            ->assertNotFound();

        $list = $this->getJson("/api/v1/clubs/{$club->id}/events?per_page=50")
            ->assertOk()
            ->json('data');

        $this->assertContains($cancelled->id, array_column($list, 'id'));
        $this->assertNotContains($cancelledDraft->id, array_column($list, 'id'));
    }

    public function test_unauthenticated_inactive_cross_club_and_wrong_club_access_fail_closed(): void
    {
        $club = $this->createClub('EVT-A');
        $otherClub = $this->createClub('EVT-B');
        $event = $this->createClubEvent($club);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/events")
            ->assertUnauthorized();

        $inactive = $this->createUserWithRole($club, 'president-admin', 'inactive');
        Sanctum::actingAs($inactive);
        $this->getJson("/api/v1/clubs/{$club->id}/events")
            ->assertForbidden();

        $crossClubOfficer = $this->createUserWithRole($otherClub, 'president-admin');
        Sanctum::actingAs($crossClubOfficer);
        $this->getJson("/api/v1/clubs/{$club->id}/events")
            ->assertForbidden();

        $sameClubOfficer = $this->createUserWithRole($club, 'president-admin');
        Sanctum::actingAs($sameClubOfficer);
        config(['instance.organisation_code' => $otherClub->code]);
        $this->getJson("/api/v1/clubs/{$otherClub->id}/events/{$event->id}")
            ->assertForbidden();
        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/events/999999")
            ->assertNotFound();
    }

    public function test_cross_club_list_isolation_and_nested_detail_ownership(): void
    {
        Carbon::setTestNow('2026-06-23 00:00:00');
        $club = $this->createClub('EVT-LIST-A');
        $otherClub = $this->createClub('EVT-LIST-B');
        $officer = $this->createUserWithRole($club, 'president-admin');
        $own = $this->createClubEvent($club, ['title' => 'Own Club Event']);
        $other = $this->createClubEvent($otherClub, ['title' => 'Other Club Event']);

        Sanctum::actingAs($officer);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/events?scope=all")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->getJson("/api/v1/clubs/{$club->id}/events/{$other->id}")
            ->assertNotFound();
    }

    public function test_empty_filtering_ordering_and_pagination_behaviour(): void
    {
        Carbon::setTestNow('2026-06-23 00:00:00');
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'president-admin');

        Sanctum::actingAs($officer);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/events")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $second = $this->createClubEvent($club, [
            'title' => 'Board Meeting',
            'type' => 'meeting',
            'starts_at' => '2026-06-25 10:00:00',
        ]);
        $first = $this->createClubEvent($club, [
            'title' => 'Community Event',
            'type' => 'event',
            'starts_at' => '2026-06-24 10:00:00',
        ]);
        $this->createClubEvent($club, [
            'title' => 'Past Meeting',
            'type' => 'meeting',
            'starts_at' => '2026-06-20 10:00:00',
            'ends_at' => '2026-06-20 11:00:00',
        ]);

        $this->getJson("/api/v1/clubs/{$club->id}/events?type=meeting&q=Board")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $second->id);

        $this->getJson("/api/v1/clubs/{$club->id}/events?per_page=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        $this->getJson("/api/v1/clubs/{$club->id}/events?scope=past")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Past Meeting');
    }

    public function test_non_management_filters_are_forbidden_and_delete_route_does_not_exist(): void
    {
        $club = $this->createClub();
        $member = $this->createUserWithRole($club, 'member');
        $event = $this->createClubEvent($club);

        Sanctum::actingAs($member);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/events?scope=all")
            ->assertForbidden();
        $this->getJson("/api/v1/clubs/{$club->id}/events?status=draft")
            ->assertForbidden();
        $this->getJson("/api/v1/clubs/{$club->id}/events?visibility=management")
            ->assertForbidden();

        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'clubs/{club}/events'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        foreach ($routes as $route) {
            $this->assertFalse(str_starts_with($route, 'DELETE '));
        }

        config(['instance.organisation_code' => $club->code]);
        $this->deleteJson("/api/v1/clubs/{$club->id}/events/{$event->id}")->assertMethodNotAllowed();
    }

    public function test_historical_3fo_frontend_remained_fail_closed_and_not_connected(): void
    {
        $frontend = $this->gitOutput(['show', self::VERIFIED_3FO_COMMIT.':app/page.tsx']);
        $frontend = $this->normalizeLineEndings($frontend);

        $this->assertStringContainsString('The Events & Meetings workflow is not connected to a verified operational source yet.', $frontend);
        $this->assertStringContainsString('Real events and meeting records will appear here only after a verified source is connected.', $frontend);
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function gitOutput(array $arguments): string
    {
        $repoRoot = realpath($this->repoPath) ?: $this->repoPath;
        $command = array_merge(['git', '-C', $repoRoot], $arguments);
        $escapedCommand = implode(' ', array_map('escapeshellarg', $command));

        $output = [];
        $exitCode = 0;
        exec($escapedCommand.' 2>&1', $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            'Expected local Git command to succeed: '.implode(' ', $command)."\n".implode("\n", $output)
        );

        return implode("\n", $output);
    }

    private function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    private function createClub(string $code = 'EVT-CLUB', string $timezone = 'Asia/Kolkata'): Club
    {
        $this->seed(DatabaseSeeder::class);

        return Club::query()->create([
            'name' => 'Events Test Club '.$code,
            'code' => $code.uniqid(),
            'timezone' => $timezone,
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(Club $club, string $roleSlug, string $status = 'active'): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create();

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => $status,
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function createClubEvent(Club $club, array $overrides = []): ClubEvent
    {
        return ClubEvent::query()->create(array_merge([
            'club_id' => $club->id,
            'type' => 'event',
            'title' => 'Synthetic Events Test Record',
            'description' => 'Synthetic record for isolated feature tests.',
            'is_all_day' => false,
            'starts_at' => '2026-06-24 10:00:00',
            'ends_at' => '2026-06-24 11:00:00',
            'start_date' => null,
            'end_date' => null,
            'venue' => 'Synthetic Hall',
            'status' => 'published',
            'visibility' => 'members',
            'published_at' => '2026-06-23 09:00:00',
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'completed_at' => null,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'lock_version' => 1,
        ], $overrides));
    }
}
