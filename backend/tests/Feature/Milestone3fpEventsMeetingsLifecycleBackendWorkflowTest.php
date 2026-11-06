<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubEvent;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3fpEventsMeetingsLifecycleBackendWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFIED_3FP_COMMIT = '9e27a7ab75d10cf2a9affab3b4d85fd576c43617';

    private string $docPath = __DIR__ . '/../../docs/milestone_3fp_events_meetings_lifecycle_backend_workflow.md';
    private string $repoPath = __DIR__ . '/../../..';


    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_3fp_document_records_lifecycle_scope_and_boundaries(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3FP - Events & Meetings Lifecycle Backend Workflow',
            '360c6f6a60f91bf406ce71101d17e23228ad3176',
            'POST /api/v1/clubs/{club}/events',
            'PATCH /api/v1/clubs/{club}/events/{event}',
            'POST /api/v1/clubs/{club}/events/{event}/publish',
            'POST /api/v1/clubs/{club}/events/{event}/cancel',
            'POST /api/v1/clubs/{club}/events/{event}/complete',
            'No DELETE route and no generic `PATCH status` workflow exists.',
            'Frontend remains fail-closed and not connected until 3FQ.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_president_and_secretary_can_create_draft_with_server_owned_fields_and_audit(): void
    {
        foreach (['president-admin', 'secretary'] as $roleSlug) {
            Carbon::setTestNow('2026-06-23 10:00:00');
            $club = $this->createClub('CREATE-'.$roleSlug.uniqid());
            $actor = $this->createUserWithRole($club, $roleSlug);

            Sanctum::actingAs($actor);

            config(['instance.organisation_code' => $club->code]);
            $this->postJson("/api/v1/clubs/{$club->id}/events", $this->validPayload())
                ->assertCreated()
                ->assertJsonPath('data.status', 'draft')
                ->assertJsonPath('data.lock_version', 1)
                ->assertJsonPath('data.created_by_user_id', $actor->id)
                ->assertJsonPath('data.updated_by_user_id', $actor->id);

            $this->assertDatabaseHas('club_events', [
                'club_id' => $club->id,
                'status' => 'draft',
                'title' => 'Synthetic Planning Meeting',
                'created_by_user_id' => $actor->id,
            ]);

            $this->assertDatabaseHas('audit_logs', [
                'club_id' => $club->id,
                'user_id' => $actor->id,
                'action' => 'events.created',
                'auditable_type' => ClubEvent::class,
            ]);
        }
    }

    public function test_create_blocks_unauthorized_roles_inactive_unauthenticated_invalid_payloads_and_club_override(): void
    {
        $club = $this->createClub();

        config(['instance.organisation_code' => $club->code]);
        $this->postJson("/api/v1/clubs/{$club->id}/events", $this->validPayload())->assertUnauthorized();

        foreach (['treasurer', 'member'] as $roleSlug) {
            Sanctum::actingAs($this->createUserWithRole($club, $roleSlug));
            config(['instance.organisation_code' => $club->code]);
            $this->postJson("/api/v1/clubs/{$club->id}/events", $this->validPayload())->assertForbidden();
        }

        Sanctum::actingAs($this->createUserWithRole($club, 'president-admin', 'inactive'));
        config(['instance.organisation_code' => $club->code]);
        $this->postJson("/api/v1/clubs/{$club->id}/events", $this->validPayload())->assertForbidden();


        Sanctum::actingAs($this->createUserWithRole($club, 'president-admin'));
        $this->postJson("/api/v1/clubs/{$club->id}/events", array_merge($this->validPayload(), ['type' => 'training']))->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/events", array_merge($this->validPayload(), ['visibility' => 'public']))->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/events", array_merge($this->validPayload(), ['club_id' => 999]))->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/events", array_merge($this->validPayload(), ['is_all_day' => true, 'start_at' => '2026-06-24T10:00:00+05:30']))->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/events", array_merge($this->validPayload(), ['end_at' => '2026-06-24T09:00:00+05:30']))->assertUnprocessable();

        $this->assertSame(0, ClubEvent::count());
        $this->assertSame(0, AuditLog::where('action', 'events.created')->count());
    }

    public function test_update_requires_stable_id_lock_version_editable_state_and_audit_before_after(): void
    {
        $club = $this->createClub();
        $actor = $this->createUserWithRole($club, 'secretary');
        $event = $this->createClubEvent($club, ['status' => 'draft', 'lock_version' => 1]);

        Sanctum::actingAs($actor);

        config(['instance.organisation_code' => $club->code]);
        $this->patchJson("/api/v1/clubs/{$club->id}/events/{$event->id}", [
            'lock_version' => 1,
            'title' => 'Updated Meeting Title',
            'visibility' => 'management',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated Meeting Title')
            ->assertJsonPath('data.visibility', 'management')
            ->assertJsonPath('data.lock_version', 2);

        $audit = AuditLog::where('action', 'events.updated')->firstOrFail();
        $this->assertSame('Synthetic Events Test Record', $audit->metadata['before']['title']);
        $this->assertSame('Updated Meeting Title', $audit->metadata['after']['title']);

        $this->patchJson("/api/v1/clubs/{$club->id}/events/{$event->id}", [
            'lock_version' => 1,
            'title' => 'Stale Update',
        ])->assertConflict();

        $this->patchJson("/api/v1/clubs/{$club->id}/events/{$event->id}", [
            'lock_version' => 2,
            'status' => 'published',
        ])->assertUnprocessable();
    }

    public function test_terminal_update_and_cross_club_update_fail_without_audit(): void
    {
        $club = $this->createClub('UPDATE-A');
        $otherClub = $this->createClub('UPDATE-B');
        $actor = $this->createUserWithRole($club, 'president-admin');
        $completed = $this->createClubEvent($club, ['status' => 'completed']);
        $otherEvent = $this->createClubEvent($otherClub, ['status' => 'draft']);

        Sanctum::actingAs($actor);

        config(['instance.organisation_code' => $club->code]);
        $this->patchJson("/api/v1/clubs/{$club->id}/events/{$completed->id}", [
            'lock_version' => 1,
            'title' => 'Nope',
        ])->assertConflict();

        $this->patchJson("/api/v1/clubs/{$club->id}/events/{$otherEvent->id}", [
            'lock_version' => 1,
            'title' => 'Nope',
        ])->assertNotFound();

        $this->assertSame(0, AuditLog::where('action', 'events.updated')->count());
    }

    public function test_publish_validates_state_lock_version_authorization_and_audit_idempotency(): void
    {
        Carbon::setTestNow('2026-06-23 10:00:00');
        $club = $this->createClub();
        $actor = $this->createUserWithRole($club, 'president-admin');
        $event = $this->createClubEvent($club, ['status' => 'draft']);

        Sanctum::actingAs($actor);

        config(['instance.organisation_code' => $club->code]);
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$event->id}/publish", ['lock_version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.lock_version', 2);

        $this->assertSame(1, AuditLog::where('action', 'events.published')->count());

        $this->postJson("/api/v1/clubs/{$club->id}/events/{$event->id}/publish", ['lock_version' => 2])->assertConflict();
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$event->id}/publish", ['lock_version' => 1])->assertConflict();
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$event->id}/publish", ['lock_version' => 2, 'cancellation_reason' => 'No'])->assertUnprocessable();
        $this->assertSame(1, AuditLog::where('action', 'events.published')->count());
    }

    public function test_cancel_draft_and_published_requires_reason_preserves_visibility_and_avoids_duplicates(): void
    {
        Carbon::setTestNow('2026-06-23 10:00:00');
        $club = $this->createClub();
        $actor = $this->createUserWithRole($club, 'secretary');
        $draft = $this->createClubEvent($club, ['status' => 'draft', 'visibility' => 'management']);
        $published = $this->createClubEvent($club, ['status' => 'published', 'visibility' => 'members']);

        Sanctum::actingAs($actor);

        config(['instance.organisation_code' => $club->code]);
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$draft->id}/cancel", ['lock_version' => 1])->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$draft->id}/cancel", ['lock_version' => 1, 'cancellation_reason' => 'Venue closed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.visibility', 'management')
            ->assertJsonPath('data.lock_version', 2);

        $this->postJson("/api/v1/clubs/{$club->id}/events/{$published->id}/cancel", ['lock_version' => 1, 'cancellation_reason' => 'Weather'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.visibility', 'members');

        $this->postJson("/api/v1/clubs/{$club->id}/events/{$published->id}/cancel", ['lock_version' => 2, 'cancellation_reason' => 'Again'])->assertConflict();
        $this->assertSame(2, AuditLog::where('action', 'events.cancelled')->count());
    }

    public function test_complete_enforces_published_state_and_early_completion_rules_for_timed_and_all_day(): void
    {
        Carbon::setTestNow('2026-06-23 10:00:00');
        $club = $this->createClub('COMPLETE', 'Asia/Kolkata');
        $actor = $this->createUserWithRole($club, 'president-admin');
        $timed = $this->createClubEvent($club, [
            'status' => 'published',
            'starts_at' => '2026-06-23 09:00:00',
            'ends_at' => '2026-06-23 11:00:00',
        ]);
        $allDay = $this->createClubEvent($club, [
            'status' => 'published',
            'is_all_day' => true,
            'starts_at' => '2026-06-23 18:30:00',
            'ends_at' => '2026-06-24 18:29:59',
            'start_date' => '2026-06-24',
            'end_date' => '2026-06-24',
        ]);
        $draft = $this->createClubEvent($club, ['status' => 'draft']);

        Sanctum::actingAs($actor);

        config(['instance.organisation_code' => $club->code]);
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$timed->id}/complete", ['lock_version' => 1])->assertConflict();
        Carbon::setTestNow('2026-06-23 11:00:00');
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$timed->id}/complete", ['lock_version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.lock_version', 2);

        Carbon::setTestNow('2026-06-24 17:00:00');
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$allDay->id}/complete", ['lock_version' => 1])->assertConflict();
        Carbon::setTestNow('2026-06-24 18:30:00');
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$allDay->id}/complete", ['lock_version' => 1])->assertOk();

        $this->postJson("/api/v1/clubs/{$club->id}/events/{$draft->id}/complete", ['lock_version' => 1])->assertConflict();
        $this->assertSame(2, AuditLog::where('action', 'events.completed')->count());
    }

    public function test_treasurer_member_and_cross_club_user_cannot_mutate_and_delete_route_absent(): void
    {
        $club = $this->createClub('AUTH-A');
        $otherClub = $this->createClub('AUTH-B');
        $event = $this->createClubEvent($club, ['status' => 'draft']);

        foreach (['treasurer', 'member'] as $roleSlug) {
            Sanctum::actingAs($this->createUserWithRole($club, $roleSlug));
            config(['instance.organisation_code' => $club->code]);
            $this->patchJson("/api/v1/clubs/{$club->id}/events/{$event->id}", ['lock_version' => 1, 'title' => 'No'])->assertForbidden();
            $this->postJson("/api/v1/clubs/{$club->id}/events/{$event->id}/publish", ['lock_version' => 1])->assertForbidden();
        }

        Sanctum::actingAs($this->createUserWithRole($otherClub, 'president-admin'));
        config(['instance.organisation_code' => $club->code]);
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$event->id}/publish", ['lock_version' => 1])->assertForbidden();

        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'clubs/{club}/events'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertNotContains('DELETE api/v1/clubs/{club}/events/{event}', $routes);
    }

    public function test_historical_3fp_frontend_remained_not_connected(): void
    {
        $frontend = $this->gitOutput(['show', self::VERIFIED_3FP_COMMIT.':app/page.tsx']);
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'meeting',
            'title' => 'Synthetic Planning Meeting',
            'description' => 'Synthetic lifecycle test record.',
            'venue' => 'Synthetic Hall',
            'visibility' => 'members',
            'is_all_day' => false,
            'start_at' => '2026-06-24T10:00:00+05:30',
            'end_at' => '2026-06-24T11:00:00+05:30',
        ], $overrides);
    }

    private function createClub(string $code = 'EVT-FP', string $timezone = 'Asia/Kolkata'): Club
    {
        $this->seed(DatabaseSeeder::class);

        return Club::query()->create([
            'name' => 'Events Lifecycle Test Club '.$code,
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
