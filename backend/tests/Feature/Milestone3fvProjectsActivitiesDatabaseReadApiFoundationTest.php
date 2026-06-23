<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\ClubProject;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3fvProjectsActivitiesDatabaseReadApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_COMMIT = 'f584592e5c0873d4ab94a8b7a76167b0bf7ccef0';
    private const COMMIT_SUBJECT = 'Add projects activities read API foundation';
    private const AUTHORIZED_FILES = [
        'backend/app/Http/Controllers/Api/V1/Projects/ClubProjectController.php',
        'backend/app/Http/Requests/Projects/ListClubProjectsRequest.php',
        'backend/app/Http/Resources/Projects/ClubProjectResource.php',
        'backend/app/Models/ClubProject.php',
        'backend/app/Services/Projects/ClubProjectReadService.php',
        'backend/database/migrations/2026_06_24_000001_create_club_projects_table.php',
        'backend/database/seeders/DefaultRolesAndPermissionsSeeder.php',
        'backend/docs/milestone_3fv_projects_activities_database_read_api_foundation.md',
        'backend/routes/api.php',
        'backend/tests/Feature/DefaultRolesAndPermissionsSeederTest.php',
        'backend/tests/Feature/Milestone3ftProjectsActivitiesBackendActivationScopeGateTest.php',
        'backend/tests/Feature/Milestone3fuProjectsActivitiesAuthorizationContractApprovalTest.php',
        'backend/tests/Feature/Milestone3fvProjectsActivitiesDatabaseReadApiFoundationTest.php',
    ];

    private string $docPath = __DIR__ . '/../../docs/milestone_3fv_projects_activities_database_read_api_foundation.md';
    private string $repoPath = __DIR__ . '/../../..';

    public function test_document_records_3fv_scope_boundaries_and_changed_files(): void
    {
        $document = $this->document();

        foreach ([
            'Milestone 3FV - Projects & Activities Database and Read API Foundation',
            self::BASE_COMMIT,
            'implements only the Projects & Activities database and read API foundation',
            '3FV does not implement create, update, plan, start, complete, cancel',
            'backend/database/migrations/2026_06_24_000001_create_club_projects_table.php',
            'backend/app/Models/ClubProject.php',
            'backend/app/Services/Projects/ClubProjectReadService.php',
            'backend/app/Http/Controllers/Api/V1/Projects/ClubProjectController.php',
            'backend/tests/Feature/DefaultRolesAndPermissionsSeederTest.php',
            'GO - Milestone 3FW may open',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_club_projects_table_can_be_migrated_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('club_projects'));

        foreach ([
            'id',
            'club_id',
            'record_type',
            'title',
            'description',
            'objective',
            'service_category',
            'visibility',
            'status',
            'planned_start_date',
            'planned_end_date',
            'actual_start_date',
            'actual_end_date',
            'location',
            'lead_member_id',
            'expected_beneficiary_count',
            'actual_beneficiary_count',
            'impact_summary',
            'cancellation_reason',
            'planned_at',
            'started_at',
            'completed_at',
            'cancelled_at',
            'created_by_user_id',
            'updated_by_user_id',
            'planned_by_user_id',
            'started_by_user_id',
            'completed_by_user_id',
            'cancelled_by_user_id',
            'lock_version',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('club_projects', $column), "Missing column [{$column}].");
        }
    }

    public function test_model_relationships_fillable_and_controlled_values(): void
    {
        $club = $this->createClub();
        $creator = User::factory()->create();
        $updater = User::factory()->create();
        $lead = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'PA-LEAD',
            'full_name' => 'Projects Lead Member',
            'status' => 'active',
        ]);

        $project = $this->createProject($club, [
            'lead_member_id' => $lead->id,
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $updater->id,
        ]);

        $this->assertSame(['project', 'activity'], ClubProject::recordTypes());
        $this->assertSame(['draft', 'planned', 'active', 'completed', 'cancelled'], ClubProject::statuses());
        $this->assertSame(['members', 'management'], ClubProject::visibilities());
        $this->assertContains('lock_version', (new ClubProject())->getFillable());
        $this->assertTrue($project->club->is($club));
        $this->assertTrue($project->leadMember->is($lead));
        $this->assertTrue($project->creator->is($creator));
        $this->assertTrue($project->updater->is($updater));
    }

    public function test_seeded_permissions_match_3fu_role_matrix(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['president-admin', 'secretary'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $this->assertTrue($role->permissions->contains('slug', 'projects.view'));
            $this->assertTrue($role->permissions->contains('slug', 'projects.manage'));
        }

        foreach (['treasurer', 'member'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $this->assertTrue($role->permissions->contains('slug', 'projects.view'));
            $this->assertFalse($role->permissions->contains('slug', 'projects.manage'));
        }
    }

    public function test_management_roles_can_read_all_same_club_statuses_and_visibilities(): void
    {
        foreach (['president-admin', 'secretary'] as $roleSlug) {
            $club = $this->createClub('PA-MGMT-'.$roleSlug.uniqid());
            $user = $this->createUserWithRole($club, $roleSlug);
            $draft = $this->createProject($club, [
                'title' => 'Management Draft Project',
                'status' => 'draft',
                'visibility' => 'management',
            ]);

            Sanctum::actingAs($user);

            $this->getJson("/api/v1/clubs/{$club->id}/projects?scope=all")
                ->assertOk()
                ->assertJsonPath('data.0.id', $draft->id)
                ->assertJsonPath('data.0.visibility', 'management')
                ->assertJsonPath('data.0.lock_version', 1);

            $this->getJson("/api/v1/clubs/{$club->id}/projects/{$draft->id}")
                ->assertOk()
                ->assertJsonPath('data.id', $draft->id)
                ->assertJsonPath('data.status', 'draft');
        }
    }

    public function test_treasurer_and_normal_member_read_only_member_visible_records(): void
    {
        foreach (['treasurer', 'member'] as $roleSlug) {
            $club = $this->createClub('PA-READ-'.$roleSlug.uniqid());
            $user = $this->createUserWithRole($club, $roleSlug);
            $planned = $this->createProject($club, ['title' => 'Planned Members Project', 'status' => 'planned', 'visibility' => 'members', 'planned_at' => now()]);
            $active = $this->createProject($club, ['title' => 'Active Members Activity', 'record_type' => 'activity', 'status' => 'active', 'visibility' => 'members', 'planned_at' => now(), 'started_at' => now()]);
            $completed = $this->createProject($club, ['title' => 'Completed Members Activity', 'record_type' => 'activity', 'status' => 'completed', 'visibility' => 'members', 'planned_at' => now(), 'completed_at' => now()]);
            $hiddenDraft = $this->createProject($club, ['title' => 'Hidden Draft', 'status' => 'draft', 'visibility' => 'members']);
            $management = $this->createProject($club, ['title' => 'Management Only', 'status' => 'planned', 'visibility' => 'management', 'planned_at' => now()]);

            Sanctum::actingAs($user);

            $response = $this->getJson("/api/v1/clubs/{$club->id}/projects?scope=all&per_page=50")
                ->assertOk();

            $ids = array_column($response->json('data'), 'id');
            $this->assertContains($planned->id, $ids);
            $this->assertContains($active->id, $ids);
            $this->assertContains($completed->id, $ids);
            $this->assertNotContains($hiddenDraft->id, $ids);
            $this->assertNotContains($management->id, $ids);

            $payload = collect($response->json('data'))->firstWhere('id', $planned->id);
            $this->assertArrayNotHasKey('visibility', $payload);
            $this->assertArrayNotHasKey('lock_version', $payload);
            $this->assertArrayNotHasKey('cancellation_reason', $payload);

            $this->getJson("/api/v1/clubs/{$club->id}/projects/{$management->id}")
                ->assertNotFound();
        }
    }

    public function test_cancelled_previously_visible_record_is_visible_but_cancelled_draft_is_hidden(): void
    {
        $club = $this->createClub();
        $member = $this->createUserWithRole($club, 'member');
        $cancelledVisible = $this->createProject($club, [
            'status' => 'cancelled',
            'visibility' => 'members',
            'planned_at' => '2026-06-24 10:00:00',
            'cancelled_at' => '2026-06-25 10:00:00',
            'cancellation_reason' => 'Private cancellation reason.',
        ]);
        $cancelledDraft = $this->createProject($club, [
            'title' => 'Cancelled Draft Hidden',
            'status' => 'cancelled',
            'visibility' => 'members',
            'planned_at' => null,
            'cancelled_at' => '2026-06-25 10:00:00',
            'cancellation_reason' => 'Private draft reason.',
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson("/api/v1/clubs/{$club->id}/projects/{$cancelledVisible->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertArrayNotHasKey('cancellation_reason', $response->json('data'));
        $this->assertArrayNotHasKey('visibility', $response->json('data'));

        $this->getJson("/api/v1/clubs/{$club->id}/projects/{$cancelledDraft->id}")
            ->assertNotFound();
    }

    public function test_unauthenticated_inactive_cross_club_and_wrong_nested_access_fail_closed(): void
    {
        $club = $this->createClub('PA-A');
        $otherClub = $this->createClub('PA-B');
        $project = $this->createProject($club);

        $this->getJson("/api/v1/clubs/{$club->id}/projects")
            ->assertUnauthorized();

        $inactive = $this->createUserWithRole($club, 'president-admin', 'inactive');
        Sanctum::actingAs($inactive);
        $this->getJson("/api/v1/clubs/{$club->id}/projects")
            ->assertForbidden();

        $crossClubOfficer = $this->createUserWithRole($otherClub, 'president-admin');
        Sanctum::actingAs($crossClubOfficer);
        $this->getJson("/api/v1/clubs/{$club->id}/projects")
            ->assertForbidden();

        $dualOfficer = $this->createUserWithRole($club, 'president-admin');
        ClubMembership::query()->create([
            'club_id' => $otherClub->id,
            'user_id' => $dualOfficer->id,
            'role_id' => Role::where('slug', 'president-admin')->firstOrFail()->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        Sanctum::actingAs($dualOfficer);
        $this->getJson("/api/v1/clubs/{$otherClub->id}/projects/{$project->id}")
            ->assertNotFound();
    }

    public function test_filtering_ordering_pagination_duplicate_titles_and_stable_ids(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'president-admin');
        $second = $this->createProject($club, ['title' => 'Duplicate Service', 'record_type' => 'activity', 'status' => 'planned', 'planned_start_date' => '2026-07-02', 'service_category' => 'health', 'planned_at' => now()]);
        $first = $this->createProject($club, ['title' => 'Duplicate Service', 'record_type' => 'project', 'status' => 'planned', 'planned_start_date' => '2026-07-01', 'service_category' => 'health', 'planned_at' => now()]);
        $this->createProject($club, ['title' => 'Education Project', 'record_type' => 'project', 'status' => 'completed', 'planned_start_date' => '2026-06-01', 'service_category' => 'education', 'planned_at' => now(), 'completed_at' => now()]);

        Sanctum::actingAs($officer);

        $this->getJson("/api/v1/clubs/{$club->id}/projects?record_type=project&service_category=health&q=Duplicate")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id);

        $this->getJson("/api/v1/clubs/{$club->id}/projects?scope=planned&per_page=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        $list = $this->getJson("/api/v1/clubs/{$club->id}/projects?scope=planned&per_page=50")
            ->assertOk()
            ->json('data');

        $this->assertSame([$first->id, $second->id], array_column($list, 'id'));
    }

    public function test_empty_list_management_only_filters_and_no_mutation_routes(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createUserWithRole($club, 'member');

        Sanctum::actingAs($officer);
        $this->getJson("/api/v1/clubs/{$club->id}/projects")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/projects?status=draft")
            ->assertForbidden();
        $this->getJson("/api/v1/clubs/{$club->id}/projects?visibility=management")
            ->assertForbidden();

        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'clubs/{club}/projects'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertContains('GET|HEAD api/v1/clubs/{club}/projects', $routes);
        $this->assertContains('GET|HEAD api/v1/clubs/{club}/projects/{project}', $routes);

        foreach ($routes as $route) {
            $this->assertFalse(str_starts_with($route, 'POST '));
            $this->assertFalse(str_starts_with($route, 'PATCH '));
            $this->assertFalse(str_starts_with($route, 'DELETE '));
        }

        $this->postJson("/api/v1/clubs/{$club->id}/projects", [])->assertMethodNotAllowed();
        $this->patchJson("/api/v1/clubs/{$club->id}/projects/1", [])->assertMethodNotAllowed();
        $this->deleteJson("/api/v1/clubs/{$club->id}/projects/1")->assertMethodNotAllowed();
    }

    public function test_3fv_changed_file_scope_is_lifecycle_safe(): void
    {
        $commit = $this->findUniqueCommitBySubject(self::COMMIT_SUBJECT);

        if ($commit !== null) {
            $this->assertSameSorted(self::AUTHORIZED_FILES, $this->changedFilesInCommit($commit), 'Historical 3FV commit changed files must remain limited to the authorized read-foundation scope.');
            $historicalDocument = $this->normalizeLineEndings($this->gitOutput(['show', $commit.':backend/docs/milestone_3fv_projects_activities_database_read_api_foundation.md']));
            $this->assertStringContainsString('GO - Milestone 3FW may open', $historicalDocument);
            $this->assertStringContainsString('3FV does not implement create, update, plan, start, complete, cancel', $historicalDocument);

            return;
        }

        $this->assertSame(self::BASE_COMMIT, trim($this->gitOutput(['rev-parse', 'HEAD'])));
        $this->assertSameSorted(self::AUTHORIZED_FILES, $this->currentChangedFiles(), 'Before the 3FV commit exists, the working tree must contain only authorized 3FV files.');
    }

    private function document(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function createClub(string $code = 'PA-CLUB'): Club
    {
        $this->seed(DatabaseSeeder::class);

        return Club::query()->create([
            'name' => 'Projects Test Club '.$code,
            'code' => $code.uniqid(),
            'timezone' => 'Asia/Kolkata',
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

    private function createProject(Club $club, array $overrides = []): ClubProject
    {
        return ClubProject::query()->create(array_merge([
            'club_id' => $club->id,
            'record_type' => 'project',
            'title' => 'Synthetic Projects Test Record',
            'description' => 'Synthetic record for Projects and Activities read tests.',
            'objective' => 'Verify Projects read API foundation.',
            'service_category' => 'service',
            'visibility' => 'members',
            'status' => 'planned',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-02',
            'actual_start_date' => null,
            'actual_end_date' => null,
            'location' => 'Synthetic Service Hall',
            'lead_member_id' => null,
            'expected_beneficiary_count' => 25,
            'actual_beneficiary_count' => null,
            'impact_summary' => null,
            'cancellation_reason' => null,
            'planned_at' => '2026-06-24 10:00:00',
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

        $this->assertSame(0, $exitCode, sprintf(
            'Expected local Git command to succeed: %s. Output: %s',
            $escapedCommand,
            implode("\n", $output)
        ));

        return implode("\n", $output);
    }

    private function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}