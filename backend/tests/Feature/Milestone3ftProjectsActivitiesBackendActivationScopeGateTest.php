<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ftProjectsActivitiesBackendActivationScopeGateTest extends TestCase
{
    private const BASE_COMMIT = '2ea81ac78790caaa81a926e3f568637e94613ae7';
    private const COMMIT_SUBJECT = 'Add projects activities backend scope gate';
    private const AUTHORIZED_FILES = [
        'backend/docs/milestone_3ft_projects_activities_backend_activation_scope_gate.md',
        'backend/tests/Feature/Milestone3ftProjectsActivitiesBackendActivationScopeGateTest.php',
    ];

    private string $docPath = __DIR__ . '/../../docs/milestone_3ft_projects_activities_backend_activation_scope_gate.md';
    private string $repoPath = __DIR__ . '/../../..';

    public function test_3ft_document_exists_and_is_scope_gate_only(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3FT - Projects & Activities Backend Activation Scope Gate',
            'milestone-3fs-events-meetings-closure-review',
            self::BASE_COMMIT,
            'milestone-3ft-projects-activities-backend-activation-scope-gate',
            'documentation-and-governance scope gate only',
            'No runtime Projects code, frontend integration, route, controller, request, resource, service, model, migration, factory, seeder, policy, middleware, config, package, database, environment, cache, log, or generated file is authorized in 3FT.',
            'backend/docs/milestone_3ft_projects_activities_backend_activation_scope_gate.md',
            'backend/tests/Feature/Milestone3ftProjectsActivitiesBackendActivationScopeGateTest.php',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3ft_records_required_repository_inspection_evidence(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'The Projects & Activities workflow is not connected to a verified operational source yet.',
            '`verifiedOperationalSourceConnected("projects")` remains false',
            'Legacy prototype arrays include `Activity` UI preview data',
            '`backend/routes/api.php`',
            'club.permission:<slug>,club',
            '`DefaultRolesAndPermissionsSeeder.php`',
            '`ClubPermissionService.php` grants permissions only through active club memberships',
            '`EnsureClubPermission.php` returns 401 for unauthenticated users and 403',
            '`AuditLog.php` supports auditable type, auditable id, action, actor, club, and JSON metadata.',
            'No backend `ClubProject`, `club_projects`, Projects controller, Projects request, Projects resource, Projects service, or `/projects` route exists yet.',
            'No stable backend committee assignment model was found',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3ft_selects_unified_aggregate_lifecycle_visibility_and_permissions(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Option A - unified Projects & Activities entity.',
            'Use `App\\Models\\ClubProject` backed by table `club_projects`',
            '`project`',
            '`activity`',
            'Option B - parent `ClubProject` plus child `ClubProjectActivity` - is rejected for the first release',
            'Draft -> Planned',
            'Planned -> Active',
            'Planned -> Completed',
            'Active -> Completed',
            'Draft -> Cancelled',
            'Completed and Cancelled are terminal.',
            'Generic update must not mutate status.',
            '`members`',
            '`management`',
            'Cancelled `members` records are member-readable only if they had previously reached Planned or Active.',
            'planned_at',
            '`projects.view`',
            '`projects.manage`',
            '| President/Admin (`president-admin`) | Yes | Yes |',
            '| Secretary (`secretary`) | Yes | Yes |',
            '| Treasurer (`treasurer`) | Yes | No |',
            '| Normal Member (`member`) | Yes | No |',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3ft_defines_fields_temporal_identity_api_audit_and_deferred_features(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            '`id` | stable numeric backend identity',
            '`club_id` | required same-club scope',
            '`record_type` | `project` or `activity`',
            '`lead_member_id` | optional stable same-club member FK',
            '`expected_beneficiary_count` | optional nonnegative integer',
            '`actual_beneficiary_count` | optional nonnegative integer',
            '`lock_version` | optimistic concurrency integer starting at 1',
            'Do not store beneficiary names, beneficiary contact details, sensitive health, financial, or personal information in the first release.',
            'date-only representation for Projects & Activities',
            '`planned_start_date`',
            '`planned_end_date`',
            '`actual_start_date`',
            '`actual_end_date`',
            'Stable backend numeric IDs identify records.',
            'Title, category, date, location, list index, card order, frontend generated key, or duplicate title must never identify a record.',
            'GET /api/v1/clubs/{club}/projects',
            'POST /api/v1/clubs/{club}/projects/{project}/complete',
            'No DELETE route is approved.',
            '`projects.created`',
            '`projects.cancelled`',
            'deterministic 409 stale-version responses',
            'beneficiary identities;',
            'Events linkage;',
            'deletion.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3ft_defers_committee_management_and_frontend_connection(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Committee-chair management is deferred.',
            'must not be inferred from display name, leadership roster text, member name matching, frontend label, committee card text, or prototype UI.',
            'The Projects & Activities truthful not-connected state remains correct until a later approved frontend integration milestone connects the UI to verified backend APIs.',
            'Do not restore hardcoded Projects or Activities operational cards as real data.',
            'fake persistence, local-only saves, false success, dummy beneficiary counts, failure-to-empty conversion, or backend error masking',
            '3FT authorizes 3FU only.',
            '3FT does not authorize Projects runtime implementation.',
            'GO - Milestone 3FU may open',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3ft_changed_file_scope_is_lifecycle_safe(): void
    {
        $commit = $this->findUniqueCommitBySubject(self::COMMIT_SUBJECT);

        if ($commit !== null) {
            $this->assertSameSorted(
                self::AUTHORIZED_FILES,
                $this->changedFilesInCommit($commit),
                'Historical 3FT commit changed files must remain limited to the authorized docs/test scope.'
            );

            $historicalDocument = $this->normalizeLineEndings($this->gitOutput(['show', $commit.':backend/docs/milestone_3ft_projects_activities_backend_activation_scope_gate.md']));
            $this->assertStringContainsString('3FT authorizes 3FU only.', $historicalDocument);
            $this->assertStringContainsString('No runtime Projects code', $historicalDocument);

            return;
        }

        $this->assertSame(self::BASE_COMMIT, trim($this->gitOutput(['rev-parse', 'HEAD'])));
        $this->assertSameSorted(
            self::AUTHORIZED_FILES,
            $this->currentChangedFiles(),
            'Before the 3FT commit exists, the working tree must contain only the two authorized 3FT files.'
        );
    }

    public function test_3ft_does_not_authorize_runtime_projects_files_or_delete_route(): void
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
        $this->assertStringNotContainsString("Route::delete('/clubs/{club}/projects/{project}'", $routes);
    }


    /** @param array<int, string> $expected @param array<int, string> $actual */
    private function assertSameSorted(array $expected, array $actual, string $message): void
    {
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual, $message);
    }
    private function scopeDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
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