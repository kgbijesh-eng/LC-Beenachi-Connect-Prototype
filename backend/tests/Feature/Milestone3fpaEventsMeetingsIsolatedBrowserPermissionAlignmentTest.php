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

class Milestone3fpaEventsMeetingsIsolatedBrowserPermissionAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFIED_3FPA_COMMIT = '3d8c9adb164815382171d5628548ea64023463ba';

    private const AUTHORIZED_3FPA_FILES = [
        'backend/docs/milestone_3fpa_events_meetings_isolated_browser_permission_alignment.md',
        'backend/tests/Feature/Milestone3fpaEventsMeetingsIsolatedBrowserPermissionAlignmentTest.php',
    ];

    private string $docPath = __DIR__ . '/../../docs/milestone_3fpa_events_meetings_isolated_browser_permission_alignment.md';
    private string $backendPath = __DIR__ . '/../..';
    private string $repoPath = __DIR__ . '/../../..';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_3fpa_document_records_isolated_scope_database_boundary_and_no_runtime_source_changes(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3FPA - Events & Meetings Isolated Browser Permission Alignment',
            '9e27a7ab75d10cf2a9affab3b4d85fd576c43617',
            'No frontend files, backend runtime files, migrations, seeders, controllers, services, models, routes, environment files, or generated files are changed by this milestone.',
            'DB_CONNECTION=pgsql',
            'DB_DATABASE=lc_beenachi_connect',
            '%TEMP%\\lc_beenachi_3fpa_isolated.sqlite',
            'http://127.0.0.1:8010/api/v1',
            '3FPA ISOLATED ALIGNMENT SENTINEL',
            'No delete route is introduced.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }

        $historicalFrontend = $this->gitOutput(['show', self::VERIFIED_3FPA_COMMIT.':app/page.tsx']);
        $this->assertStringContainsString(
            'The Events & Meetings workflow is not connected to a verified operational source yet.',
            $this->normalizeLineEndings($historicalFrontend),
            'The verified 3FPA commit must preserve the historical pre-frontend Events state.'
        );

        $changedFiles = collect(explode("\n", $this->normalizeLineEndings($this->gitOutput([
            'diff-tree',
            '--no-commit-id',
            '--name-only',
            '-r',
            self::VERIFIED_3FPA_COMMIT,
        ]))))
            ->filter()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            collect(self::AUTHORIZED_3FPA_FILES)->sort()->values()->all(),
            $changedFiles,
            'The verified 3FPA commit must contain only the authorized governance doc and test files.'
        );
    }

    public function test_isolated_synthetic_roster_preserves_event_permissions_for_browser_api_alignment(): void
    {
        $club = $this->createClub('3FPA-ISO');
        $event = $this->createClubEvent($club, [
            'title' => '3FPA ISOLATED ALIGNMENT SENTINEL',
            'status' => 'published',
            'visibility' => 'members',
        ]);

        foreach (['president-admin', 'secretary'] as $roleSlug) {
            $actor = $this->createUserWithRole($club, $roleSlug);
            Sanctum::actingAs($actor);

            config(['instance.organisation_code' => $club->code]);
            $memberships = $this->getJson("/api/v1/auth/me")
                ->assertOk()
                ->json('club_memberships');

            $matchingMembership = collect($memberships)
                ->first(fn (array $membership): bool => (int) $membership['club']['id'] === (int) $club->id);
            $this->assertNotNull($matchingMembership);
            $this->assertSame($roleSlug, $matchingMembership['role']['slug']);

            config(['instance.organisation_code' => $club->code]);
            $events = $this->getJson("/api/v1/clubs/{$club->id}/events?scope=all&per_page=50")
                ->assertOk()
                ->json('data');

            $this->assertContains($event->id, array_column($events, 'id'));
            $this->assertContains('3FPA ISOLATED ALIGNMENT SENTINEL', array_column($events, 'title'));

            $this->postJson("/api/v1/clubs/{$club->id}/events", $this->validPayload([
                'title' => '3FPA '.$roleSlug.' Browser Create',
            ]))->assertCreated()
                ->assertJsonPath('data.status', 'draft')
                ->assertJsonPath('data.created_by_user_id', $actor->id);
        }

        $this->assertSame(2, AuditLog::where('action', 'events.created')->count());
    }

    public function test_treasurer_member_inactive_and_cross_club_users_are_fail_closed_for_management_actions(): void
    {
        $club = $this->createClub('3FPA-AUTH-A');
        $otherClub = $this->createClub('3FPA-AUTH-B');
        $event = $this->createClubEvent($club, [
            'title' => '3FPA Authorization Sentinel',
            'status' => 'draft',
            'visibility' => 'management',
        ]);

        foreach (['treasurer', 'member'] as $roleSlug) {
            Sanctum::actingAs($this->createUserWithRole($club, $roleSlug));
            config(['instance.organisation_code' => $club->code]);
            $this->getJson("/api/v1/clubs/{$club->id}/events?per_page=50")->assertOk();
            $this->postJson("/api/v1/clubs/{$club->id}/events", $this->validPayload())->assertForbidden();
            $this->patchJson("/api/v1/clubs/{$club->id}/events/{$event->id}", ['lock_version' => 1, 'title' => 'No'])->assertForbidden();
            $this->postJson("/api/v1/clubs/{$club->id}/events/{$event->id}/publish", ['lock_version' => 1])->assertForbidden();
        }

        Sanctum::actingAs($this->createUserWithRole($club, 'secretary', 'inactive'));
        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/events?scope=all")->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/events", $this->validPayload())->assertForbidden();

        Sanctum::actingAs($this->createUserWithRole($otherClub, 'president-admin'));
        $this->getJson("/api/v1/clubs/{$club->id}/events?scope=all")->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/events/{$event->id}/publish", ['lock_version' => 1])->assertForbidden();

        $this->assertSame(0, AuditLog::whereIn('action', ['events.created', 'events.updated', 'events.published'])->count());
    }

    public function test_management_only_records_are_hidden_from_non_management_browser_readers(): void
    {
        Carbon::setTestNow('2026-06-23 00:00:00');

        $club = $this->createClub('3FPA-VIS');
        $managementEvent = $this->createClubEvent($club, [
            'title' => '3FPA Management Only Draft',
            'status' => 'draft',
            'visibility' => 'management',
        ]);
        $memberEvent = $this->createClubEvent($club, [
            'title' => '3FPA Member Visible Published',
            'status' => 'published',
            'visibility' => 'members',
        ]);

        Sanctum::actingAs($this->createUserWithRole($club, 'member'));
        config(['instance.organisation_code' => $club->code]);
        $memberResponse = $this->getJson("/api/v1/clubs/{$club->id}/events?per_page=50")
            ->assertOk()
            ->json('data');
        $this->assertContains($memberEvent->id, array_column($memberResponse, 'id'));
        $this->assertNotContains($managementEvent->id, array_column($memberResponse, 'id'));

        Sanctum::actingAs($this->createUserWithRole($club, 'president-admin'));
        $managementResponse = $this->getJson("/api/v1/clubs/{$club->id}/events?scope=all&per_page=50")
            ->assertOk()
            ->json('data');
        $this->assertContains($memberEvent->id, array_column($managementResponse, 'id'));
        $this->assertContains($managementEvent->id, array_column($managementResponse, 'id'));
    }

    public function test_3fpa_does_not_introduce_event_delete_or_runtime_implementation_files(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'clubs/{club}/events'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertNotContains('DELETE api/v1/clubs/{club}/events/{event}', $routes);

        $authorizedFiles = [
            realpath($this->docPath),
            realpath(__FILE__),
        ];

        foreach ($this->runtimeSourcePaths() as $path) {
            $realPath = realpath($path);

            if ($realPath === false || is_dir($realPath)) {
                continue;
            }

            $this->assertNotContains($realPath, $authorizedFiles);
            $this->assertStringNotContainsString('3FPA ISOLATED ALIGNMENT SENTINEL', file_get_contents($realPath));
        }
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
            'title' => '3FPA Synthetic Browser Meeting',
            'description' => 'Synthetic 3FPA isolated browser/API alignment record.',
            'venue' => '3FPA Synthetic Hall',
            'visibility' => 'members',
            'is_all_day' => false,
            'start_at' => '2026-06-24T10:00:00+05:30',
            'end_at' => '2026-06-24T11:00:00+05:30',
        ], $overrides);
    }

    private function createClub(string $code = '3FPA'): Club
    {
        $this->seed(DatabaseSeeder::class);

        return Club::query()->create([
            'name' => '3FPA Isolated Alignment Club '.$code,
            'code' => $code.uniqid(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(Club $club, string $roleSlug, string $status = 'active'): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email' => uniqid('3fpa-', true).'@3fpa.local.test',
        ]);

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
            'title' => '3FPA Synthetic Event',
            'description' => 'Synthetic record for isolated 3FPA permission alignment.',
            'is_all_day' => false,
            'starts_at' => '2026-06-24 10:00:00',
            'ends_at' => '2026-06-24 11:00:00',
            'start_date' => null,
            'end_date' => null,
            'venue' => '3FPA Synthetic Hall',
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

    private function runtimeSourcePaths(): array
    {
        return array_merge(
            glob($this->repoPath.'/app/*.tsx') ?: [],
            glob($this->backendPath.'/app/**/*.php') ?: [],
            glob($this->backendPath.'/routes/*.php') ?: [],
            glob($this->backendPath.'/database/migrations/*.php') ?: [],
            glob($this->backendPath.'/database/seeders/*.php') ?: []
        );
    }
}
