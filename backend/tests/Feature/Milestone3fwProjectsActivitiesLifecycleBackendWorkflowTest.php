<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\ClubProject;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3fwProjectsActivitiesLifecycleBackendWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_COMMIT = 'c4c557a956bb082d2dbb842974bc5390a4a91426';
    private const COMMIT_SUBJECT = 'Add projects activities lifecycle workflows';
    private const AUTHORIZED_FILES = [
        'backend/app/Http/Controllers/Api/V1/Projects/ClubProjectController.php',
        'backend/app/Http/Requests/Projects/StoreClubProjectRequest.php',
        'backend/app/Http/Requests/Projects/TransitionClubProjectRequest.php',
        'backend/app/Http/Requests/Projects/UpdateClubProjectRequest.php',
        'backend/app/Services/Projects/ClubProjectWriteService.php',
        'backend/docs/milestone_3fw_projects_activities_lifecycle_backend_workflow.md',
        'backend/routes/api.php',
        'backend/tests/Feature/Milestone3fvProjectsActivitiesDatabaseReadApiFoundationTest.php',
        'backend/tests/Feature/Milestone3fwProjectsActivitiesLifecycleBackendWorkflowTest.php',
    ];

    private string $docPath = __DIR__ . '/../../docs/milestone_3fw_projects_activities_lifecycle_backend_workflow.md';
    private string $repoPath = __DIR__ . '/../../..';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_document_records_scope_workflows_routes_and_governance(): void
    {
        $document = $this->document();

        foreach ([
            'Milestone 3FW - Projects & Activities Create, Update and Lifecycle Backend',
            self::BASE_COMMIT,
            'Create uses route-derived club',
            'Update requires same-club ownership',
            'Plan transitions Draft -> Planned',
            'Start transitions Planned -> Active',
            'Complete transitions Planned -> Completed or Active -> Completed',
            'Cancel transitions Draft -> Cancelled, Planned -> Cancelled, or Active -> Cancelled',
            '`projects.created` audit',
            '`projects.updated` audit',
            '`projects.planned` audit',
            '`projects.started` audit',
            '`projects.completed` audit',
            '`projects.cancelled` audit',
            'No DELETE route exists.',
            'GO - Milestone 3FX may open',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_president_and_secretary_can_create_draft_with_audit_and_payload_guards(): void
    {
        foreach (['president-admin', 'secretary'] as $roleSlug) {
            $club = $this->createClub('FW-CREATE-'.$roleSlug.uniqid());
            $user = $this->createUserWithRole($club, $roleSlug);
            $lead = $this->createMember($club);
            Sanctum::actingAs($user);

            $response = $this->postJson("/api/v1/clubs/{$club->id}/projects", [
                'record_type' => 'project',
                'title' => '3FW Draft Project',
                'description' => 'Created by lifecycle test.',
                'objective' => 'Serve community.',
                'service_category' => 'health',
                'visibility' => 'members',
                'planned_start_date' => '2026-07-01',
                'planned_end_date' => '2026-07-02',
                'location' => '3FW Hall',
                'lead_member_id' => $lead->id,
                'expected_beneficiary_count' => 20,
            ])->assertCreated();

            $id = $response->json('data.id');
            $this->assertDatabaseHas('club_projects', [
                'id' => $id,
                'club_id' => $club->id,
                'status' => 'draft',
                'lock_version' => 1,
            ]);
            $this->assertDatabaseHas('audit_logs', [
                'action' => 'projects.created',
                'auditable_type' => ClubProject::class,
                'auditable_id' => $id,
            ]);
        }

        $club = $this->createClub('FW-GUARD');
        $president = $this->createUserWithRole($club, 'president-admin');
        Sanctum::actingAs($president);
        $this->postJson("/api/v1/clubs/{$club->id}/projects", [
            'record_type' => 'invalid',
            'title' => 'Invalid',
            'visibility' => 'members',
            'club_id' => 999,
            'status' => 'planned',
        ])->assertUnprocessable();
    }

    public function test_create_forbidden_for_read_only_unauthenticated_inactive_and_cross_club_users(): void
    {
        $club = $this->createClub('FW-AUTH');
        $otherClub = $this->createClub('FW-OTHER');

        $payload = ['record_type' => 'project', 'title' => 'Blocked', 'visibility' => 'members'];

        $this->postJson("/api/v1/clubs/{$club->id}/projects", $payload)->assertUnauthorized();

        foreach (['treasurer', 'member'] as $roleSlug) {
            Sanctum::actingAs($this->createUserWithRole($club, $roleSlug));
            $this->postJson("/api/v1/clubs/{$club->id}/projects", $payload)->assertForbidden();
        }

        Sanctum::actingAs($this->createUserWithRole($club, 'president-admin', 'inactive'));
        $this->postJson("/api/v1/clubs/{$club->id}/projects", $payload)->assertForbidden();

        Sanctum::actingAs($this->createUserWithRole($otherClub, 'president-admin'));
        $this->postJson("/api/v1/clubs/{$club->id}/projects", $payload)->assertForbidden();
    }

    public function test_update_allowed_by_state_rejects_terminal_arbitrary_status_stale_and_cross_club(): void
    {
        $club = $this->createClub('FW-UPD');
        $otherClub = $this->createClub('FW-UPD2');
        $president = $this->createUserWithRole($club, 'president-admin');
        $draft = $this->createProject($club, ['status' => 'draft']);
        $planned = $this->createProject($club, ['status' => 'planned', 'planned_at' => now()]);
        $active = $this->createProject($club, ['status' => 'active', 'planned_at' => now(), 'started_at' => now()]);
        $completed = $this->createProject($club, ['status' => 'completed', 'planned_at' => now(), 'completed_at' => now()]);
        $other = $this->createProject($otherClub);

        Sanctum::actingAs($president);

        $this->patchJson("/api/v1/clubs/{$club->id}/projects/{$draft->id}", ['lock_version' => 1, 'title' => 'Updated Draft'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Draft')
            ->assertJsonPath('data.lock_version', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'projects.updated', 'auditable_id' => $draft->id]);

        $this->patchJson("/api/v1/clubs/{$club->id}/projects/{$planned->id}", ['lock_version' => 1, 'location' => 'Updated Location'])
            ->assertOk();
        $this->patchJson("/api/v1/clubs/{$club->id}/projects/{$active->id}", ['lock_version' => 1, 'actual_beneficiary_count' => 5])
            ->assertOk();

        $this->patchJson("/api/v1/clubs/{$club->id}/projects/{$completed->id}", ['lock_version' => 1, 'title' => 'Nope'])
            ->assertStatus(409);
        $this->patchJson("/api/v1/clubs/{$club->id}/projects/{$planned->id}", ['lock_version' => 2, 'status' => 'completed'])
            ->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/{$club->id}/projects/{$planned->id}", ['lock_version' => 1, 'title' => 'Stale'])
            ->assertStatus(409);
        $this->patchJson("/api/v1/clubs/{$club->id}/projects/{$other->id}", ['lock_version' => 1, 'title' => 'Cross'])
            ->assertNotFound();
    }

    public function test_plan_start_complete_and_cancel_lifecycle_with_conflicts_and_audit(): void
    {
        Carbon::setTestNow('2026-07-10 10:00:00');
        $club = $this->createClub('FW-LIFE');
        $president = $this->createUserWithRole($club, 'president-admin');
        Sanctum::actingAs($president);

        $draft = $this->createProject($club, ['status' => 'draft', 'planned_start_date' => '2026-07-01']);
        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$draft->id}/plan", ['lock_version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.lock_version', 2);
        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$draft->id}/plan", ['lock_version' => 2])->assertStatus(409);

        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$draft->id}/start", ['lock_version' => 2, 'actual_start_date' => '2026-07-02'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.actual_start_date', '2026-07-02');

        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$draft->id}/complete", [
            'lock_version' => 3,
            'actual_start_date' => '2026-07-02',
            'actual_end_date' => '2026-07-09',
            'actual_beneficiary_count' => 12,
            'impact_summary' => 'Completed safely.',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        foreach (['projects.planned', 'projects.started', 'projects.completed'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'auditable_id' => $draft->id]);
        }

        $planned = $this->createProject($club, ['status' => 'planned', 'planned_at' => now(), 'planned_start_date' => '2026-07-01']);
        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$planned->id}/complete", ['lock_version' => 1, 'actual_end_date' => '2026-07-20'])->assertStatus(409);
        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$planned->id}/complete", ['lock_version' => 1, 'actual_start_date' => '2026-07-05', 'actual_end_date' => '2026-07-04'])->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$planned->id}/complete", ['lock_version' => 1, 'actual_beneficiary_count' => -1])->assertUnprocessable();

        $cancelDraft = $this->createProject($club, ['status' => 'draft', 'planned_at' => null]);
        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$cancelDraft->id}/cancel", ['lock_version' => 1])->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$cancelDraft->id}/cancel", ['lock_version' => 1, 'cancellation_reason' => 'Not needed now'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$cancelDraft->id}/cancel", ['lock_version' => 2, 'cancellation_reason' => 'Again'])->assertStatus(409);
        $this->assertDatabaseHas('audit_logs', ['action' => 'projects.cancelled', 'auditable_id' => $cancelDraft->id]);
    }

    public function test_cancelled_visibility_after_lifecycle_and_no_delete_route(): void
    {
        $club = $this->createClub('FW-VIS');
        $president = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createUserWithRole($club, 'member');
        $planned = $this->createProject($club, ['status' => 'planned', 'visibility' => 'members', 'planned_at' => now()]);
        $draft = $this->createProject($club, ['status' => 'draft', 'visibility' => 'members', 'planned_at' => null]);

        Sanctum::actingAs($president);
        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$planned->id}/cancel", ['lock_version' => 1, 'cancellation_reason' => 'Cancelled after planning'])->assertOk();
        $this->postJson("/api/v1/clubs/{$club->id}/projects/{$draft->id}/cancel", ['lock_version' => 1, 'cancellation_reason' => 'Cancelled before planning'])->assertOk();

        Sanctum::actingAs($member);
        $ids = array_column($this->getJson("/api/v1/clubs/{$club->id}/projects?scope=cancelled&per_page=50")->assertOk()->json('data'), 'id');
        $this->assertContains($planned->id, $ids);
        $this->assertNotContains($draft->id, $ids);

        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'clubs/{club}/projects'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->all();
        foreach ($routes as $route) {
            $this->assertFalse(str_starts_with($route, 'DELETE '));
        }
        $this->deleteJson("/api/v1/clubs/{$club->id}/projects/{$planned->id}")->assertMethodNotAllowed();
    }

    public function test_3fw_changed_file_scope_is_lifecycle_safe(): void
    {
        $commit = $this->findUniqueCommitBySubject(self::COMMIT_SUBJECT);

        if ($commit !== null) {
            $this->assertSameSorted(self::AUTHORIZED_FILES, $this->changedFilesInCommit($commit), 'Historical 3FW commit changed files must remain limited to lifecycle backend workflow scope.');
            $historicalDocument = $this->normalizeLineEndings($this->gitOutput(['show', $commit.':backend/docs/milestone_3fw_projects_activities_lifecycle_backend_workflow.md']));
            $this->assertStringContainsString('GO - Milestone 3FX may open', $historicalDocument);
            $this->assertStringContainsString('No DELETE route exists.', $historicalDocument);

            return;
        }

        $this->assertSame(self::BASE_COMMIT, trim($this->gitOutput(['rev-parse', 'HEAD'])));
        $this->assertSameSorted(self::AUTHORIZED_FILES, $this->currentChangedFiles(), 'Before the 3FW commit exists, the working tree must contain only authorized 3FW files.');
    }

    private function document(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function createClub(string $code = 'FW-CLUB'): Club
    {
        $this->seed(DatabaseSeeder::class);

        return Club::query()->create(['name' => '3FW Test Club '.$code, 'code' => $code.uniqid(), 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
    }

    private function createUserWithRole(Club $club, string $roleSlug, string $status = 'active'): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create();
        ClubMembership::query()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => $status, 'joined_at' => now()]);

        return $user;
    }

    private function createMember(Club $club): Member
    {
        return Member::query()->create(['club_id' => $club->id, 'member_code' => 'FW-MEM-'.uniqid(), 'full_name' => '3FW Member', 'status' => 'active']);
    }

    private function createProject(Club $club, array $overrides = []): ClubProject
    {
        return ClubProject::query()->create(array_merge([
            'club_id' => $club->id,
            'record_type' => 'project',
            'title' => '3FW Synthetic Project',
            'description' => 'Synthetic lifecycle project.',
            'objective' => 'Exercise lifecycle.',
            'service_category' => 'service',
            'visibility' => 'members',
            'status' => 'draft',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-02',
            'actual_start_date' => null,
            'actual_end_date' => null,
            'location' => '3FW Hall',
            'lead_member_id' => null,
            'expected_beneficiary_count' => 10,
            'actual_beneficiary_count' => null,
            'impact_summary' => null,
            'cancellation_reason' => null,
            'planned_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'planned_by_user_id' => null,
            'started_by_user_id' => null,
            'completed_by_user_id' => null,
            'cancelled_by_user_id' => null,
            'lock_version' => 1,
        ], $overrides));
    }

    /** @param array<int, string> $expected @param array<int, string> $actual */
    private function assertSameSorted(array $expected, array $actual, string $message): void
    {
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, $message);
    }

    private function findUniqueCommitBySubject(string $subject): ?string
    {
        $lines = array_values(array_filter(explode("\n", $this->gitOutput(['log', '--all', '--oneline']))));
        $matches = [];
        foreach ($lines as $line) {
            [$shortHash, $commitSubject] = array_pad(explode(' ', $line, 2), 2, '');
            if ($commitSubject === $subject) {
                $matches[] = trim($this->gitOutput(['rev-parse', $shortHash]));
            }
        }
        $this->assertLessThanOrEqual(1, count($matches), sprintf('Expected at most one commit with subject [%s].', $subject));
        return $matches[0] ?? null;
    }

    /** @return array<int, string> */
    private function changedFilesInCommit(string $commit): array
    {
        $files = array_values(array_filter(explode("\n", $this->gitOutput(['diff-tree', '--no-commit-id', '--name-only', '-r', $commit]))));
        sort($files);
        return $files;
    }

    /** @return array<int, string> */
    private function currentChangedFiles(): array
    {
        $lines = array_values(array_filter(explode("\n", $this->gitOutput(['status', '--porcelain=v1', '-uall']))));
        $files = [];
        foreach ($lines as $line) {
            $path = trim(substr($line, 3));
            if (str_contains($path, ' -> ')) {
                $path = substr($path, strpos($path, ' -> ') + 4);
            }
            $files[] = str_replace('\\', '/', $path);
        }
        sort($files);
        return $files;
    }

    private function gitOutput(array $arguments): string
    {
        $repoRoot = realpath($this->repoPath) ?: $this->repoPath;
        $command = array_merge(['git', '-C', $repoRoot], $arguments);
        $escapedCommand = implode(' ', array_map('escapeshellarg', $command));
        exec($escapedCommand . ' 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, sprintf('Expected local Git command to succeed: %s. Output: %s', $escapedCommand, implode("\n", $output)));
        return implode("\n", $output);
    }

    private function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}