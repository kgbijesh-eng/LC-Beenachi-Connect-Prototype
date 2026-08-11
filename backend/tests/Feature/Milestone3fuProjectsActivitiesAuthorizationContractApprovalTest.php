<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3fuProjectsActivitiesAuthorizationContractApprovalTest extends TestCase
{
    private const BASE_COMMIT = 'a18c77616ff4ac806920e75474c4f1765e970e53';
    private const COMMIT_SUBJECT = 'Approve projects activities authorization contract';
    private const AUTHORIZED_FILES = [
        'backend/docs/milestone_3fu_projects_activities_authorization_contract_approval.md',
        'backend/tests/Feature/Milestone3fuProjectsActivitiesAuthorizationContractApprovalTest.php',
    ];

    private string $docPath = __DIR__ . '/../../docs/milestone_3fu_projects_activities_authorization_contract_approval.md';
    private string $repoPath = __DIR__ . '/../../..';

    public function test_3fu_document_exists_and_is_contract_only(): void
    {
        $document = $this->document();

        foreach ([
            'Milestone 3FU - Projects & Activities Authorization and API Contract Approval',
            'milestone-3ft-projects-activities-backend-activation-scope-gate',
            self::BASE_COMMIT,
            'milestone-3fu-projects-activities-authorization-contract-approval',
            'documentation-and-governance contract approval milestone',
            'No runtime Projects code may be added in 3FU.',
            'backend/docs/milestone_3fu_projects_activities_authorization_contract_approval.md',
            'backend/tests/Feature/Milestone3fuProjectsActivitiesAuthorizationContractApprovalTest.php',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fu_records_repository_conventions_and_permissions(): void
    {
        $document = $this->document();

        foreach ([
            '`backend/routes/api.php` uses `/api/v1/clubs/{club}/...` nested club routes.',
            '`club.permission:<slug>,club` middleware protects club-scoped route families.',
            '`ClubPermissionService.php` requires active same-club memberships before a permission counts.',
            '`EnsureClubPermission.php` returns 401 unauthenticated and 403',
            '`DefaultRolesAndPermissionsSeeder.php` is the correct future location',
            '`AuditLog.php` is the audit destination',
            'unified `ClubProject` with `record_type = project|activity`',
            '`projects.view`',
            '`projects.manage`',
            'Do not introduce separate permissions for plan, start, complete, cancel',
            '| President/Admin (`president-admin`) | Yes | Yes |',
            '| Secretary (`secretary`) | Yes | Yes |',
            '| Treasurer (`treasurer`) | Yes | No |',
            '| Normal Member (`member`) | Yes | No |',
            'Committee-chair management remains deferred',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fu_records_role_action_matrix_and_api_routes(): void
    {
        $document = $this->document();

        foreach ([
            '| List | Yes | Yes | Yes, member-visible only | Yes, member-visible only | 401 | 403 | 403 for target-club list |',
            '| Read Draft | Yes | Yes | No | No | No | No | No |',
            '| Create Draft | Yes | Yes | 403 | 403 | 401 | 403 | 403 |',
            '| Delete | No route | No route | No route | No route | No route | No route | No route |',
            '`GET /api/v1/clubs/{club}/projects`',
            '`GET /api/v1/clubs/{club}/projects/{project}`',
            '`POST /api/v1/clubs/{club}/projects`',
            '`PATCH /api/v1/clubs/{club}/projects/{project}`',
            '`POST /api/v1/clubs/{club}/projects/{project}/plan`',
            '`POST /api/v1/clubs/{club}/projects/{project}/start`',
            '`POST /api/v1/clubs/{club}/projects/{project}/complete`',
            '`POST /api/v1/clubs/{club}/projects/{project}/cancel`',
            'No DELETE route is approved.',
            'No generic `PATCH status` contract is approved.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fu_records_create_update_lifecycle_and_validation_contracts(): void
    {
        $document = $this->document();

        foreach ([
            'Create always produces Draft.',
            '`record_type`: required, `project` or `activity`.',
            '`planned_end_date`: nullable ISO `YYYY-MM-DD`, not before planned start.',
            '`lead_member_id`: nullable stable same-club member ID.',
            'Payload must not control `club_id`, `status`, actor IDs, lifecycle timestamps, audit fields, `lock_version`',
            'Generic update requires `projects.manage`, same-club ownership, active membership, exact stable ID, and `lock_version`.',
            'Generic update must not accept arbitrary status.',
            'Draft: all approved descriptive and planning fields.',
            'Planned: limited corrections',
            'Active: limited operational corrections',
            'Completed and Cancelled: terminal and non-editable in the first release.',
            'Unknown/client-owned fields are rejected with 422.',
            'Draft -> Planned',
            'Planned -> Active',
            'Planned -> Completed or Active -> Completed',
            'Draft -> Cancelled, Planned -> Cancelled, or Active -> Cancelled',
            'Repeat lifecycle requests and invalid state transitions return deterministic 409 conflicts',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fu_records_read_filter_response_club_scope_error_audit_and_concurrency_contracts(): void
    {
        $document = $this->document();

        foreach ([
            'Read-only users see only:',
            'Cancelled `members` records only when `planned_at` is not null.',
            '`scope=planned|active|completed|cancelled|all`',
            '`record_type=project|activity`.',
            '`per_page`, default 20, max 50.',
            'List returns top-level `data` array plus pagination metadata.',
            'Detail returns top-level `data` object.',
            'Member-facing responses do not expose private cancellation reasons or private audit metadata.',
            'Payload cannot override club.',
            'Route binding must not disclose another club\'s record.',
            '401 unauthenticated.',
            '403 authenticated but missing route/action permission',
            '404 nonexistent, wrong-club, undisclosed, or unauthorized object identity',
            '405 for DELETE/no route.',
            '422 validation errors and unknown/client-owned fields.',
            '409 stale lock version',
            'Laravel Form Requests own request shape',
            'Services own lifecycle state',
            '`projects.created`',
            '`projects.updated`',
            '`projects.planned`',
            '`projects.started`',
            '`projects.completed`',
            '`projects.cancelled`',
            'Require atomic mutation and audit persistence in one database transaction.',
            'Rejected requests create no success audit.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fu_records_3fv_3fw_boundaries_and_frontend_guard(): void
    {
        $document = $this->document();

        foreach ([
            '3FV may implement only:',
            '`club_projects` migration;',
            '`App\\Models\\ClubProject`;',
            '`projects.view` and `projects.manage` seeded permissions and role assignments;',
            'list endpoint;',
            'detail endpoint;',
            '3FV must not implement create, update, plan, start, complete, cancel',
            '3FW may implement only after 3FV is committed, pushed, remotely verified, and clean.',
            '3FW may implement create Draft, update, plan, start, complete, cancel',
            '3FW must not implement frontend integration',
            'The Projects & Activities frontend remains truthful not-connected until 3FX.',
            'No dummy fallback, local-only save, false success, fake beneficiary count, or failure-to-empty conversion is authorized.',
            'GO - Milestone 3FV may open',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fu_changed_file_scope_is_lifecycle_safe(): void
    {
        $commit = $this->findUniqueCommitBySubject(self::COMMIT_SUBJECT);

        if ($commit !== null) {
            $this->assertSameSorted(self::AUTHORIZED_FILES, $this->changedFilesInCommit($commit), 'Historical 3FU commit changed files must remain limited to the authorized docs/test scope.');
            $historicalDocument = $this->normalizeLineEndings($this->gitOutput(['show', $commit.':backend/docs/milestone_3fu_projects_activities_authorization_contract_approval.md']));
            $this->assertStringContainsString('GO - Milestone 3FV may open', $historicalDocument);
            $this->assertStringContainsString('No runtime Projects code may be added in 3FU.', $historicalDocument);

            return;
        }

        $this->assertSame(self::BASE_COMMIT, trim($this->gitOutput(['rev-parse', 'HEAD'])));
        $this->assertSameSorted(self::AUTHORIZED_FILES, $this->currentChangedFiles(), 'Before the 3FU commit exists, the working tree must contain only the two authorized 3FU files.');
    }

    public function test_3fu_does_not_authorize_runtime_projects_files_or_routes(): void
    {
        $commit = $this->findUniqueCommitBySubject(self::COMMIT_SUBJECT);

        if ($commit !== null) {
            $files = $this->changedFilesInCommit($commit);

            foreach ($files as $file) {
                $this->assertFalse(str_starts_with($file, 'backend/app/'));
                $this->assertFalse(str_starts_with($file, 'backend/database/migrations/'));
                $this->assertNotSame('backend/routes/api.php', $file);
                $this->assertNotSame('app/page.tsx', $file);
            }

            return;
        }

        foreach ([
            app_path('Models/ClubProject.php'),
            app_path('Services/Projects/ClubProjectReadService.php'),
            app_path('Services/Projects/ClubProjectWriteService.php'),
            app_path('Http/Controllers/Api/V1/Projects/ClubProjectController.php'),
            app_path('Http/Resources/Projects/ClubProjectResource.php'),
            app_path('Http/Requests/Projects/StoreClubProjectRequest.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }

        $routes = file_get_contents(base_path('routes/api.php'));
        $this->assertStringNotContainsString("Route::get('/clubs/{club}/projects'", $routes);
        $this->assertStringNotContainsString("Route::delete('/clubs/{club}/projects/{project}'", $routes);
    }

    private function document(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
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
        $lines = array_values(array_filter(explode("\n", $this->gitOutput(['status', '--porcelain=v1']))));
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