<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3elMemberDirectorySelectedMemberReadFailureFixAuthorizationTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3el_member_directory_selected_member_read_failure_fix_authorization.md';

    public function test_3el_creates_fix_authorization_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EL — Member Directory Selected Member Read Failure Fix Authorization',
            '3EL is a docs/tests-only authorization milestone.',
            '3EK is closed, pushed, remote verified, and tracking clean.',
            '3EK locked the 3EJ controlled-use failure record.',
            '3EJ overall result remains FAIL.',
            'The defect remains classified as a human operator controlled-use blocking defect.',
            'Area: Member Directory / selected member read / member profile context.',
            'MEM-002',
            'MEM-003',
            'MEM-004',
            'MEM-001',
            'Lion Secretary User',
            'Lion Treasurer User',
            'Lion Ajith Kumar',
            'Lion Bijesh',
            'Lion Bijesh / MEM-001 - President',
            'Selected member was not found in backend member records.',
            'MEM-002 opens Secretary\'s own read/detail page (Lion Secretary User).',
            'MEM-003 opens Treasurer\'s own read/detail page (Lion Treasurer User).',
            'MEM-004 opens Ajith Kumar\'s own read/detail page (Lion Ajith Kumar).',
            'MEM-001 opens Bijesh\'s own read/detail page (Lion Bijesh).',
            'Authorized Investigation Boundary',
            'Do not claim final root cause in 3EL.',
            'Do not implement the fix in 3EL.',
            'Controlled internal MVP remains blocked.',
            'Limited internal member pilot remains not allowed.',
            'Production/SaaS/deployment/public launch remain NO-GO.',
            'Expected Next Milestone: Milestone 3EM — Member Directory Selected Member Read Failure Narrow Implementation',
            'Relationship to 3DN',
            'Relationship to 3DO',
            'Relationship to 3DQ',
            'Relationship to 3DS',
            'Relationship to 3DU',
            'Relationship to 3DV',
            'Relationship to 3DW',
            'Relationship to 3DX',
            'Relationship to 3DY',
            'Relationship to 3DZ',
            'Relationship to 3EA',
            'Relationship to 3EB',
            'Relationship to 3EC',
            'Relationship to 3ED',
            'Relationship to 3EE',
            'Relationship to 3EF',
            'Relationship to 3EG',
            'Relationship to 3EH',
            'Relationship to 3EI',
            'Relationship to 3EJ',
            'Relationship to 3EK',
            'Controlled internal MVP execution: FAIL',
            'Controlled internal MVP use: Must stop pending defect review.',
            'Production GO: No.',
            'SaaS GO: No.',
            'Deployment GO: No.',
            'Public launch GO: No.',
            'Risk Notes',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3el_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3el-member-directory-selected-member-read-failure-fix-authorization') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3el_member_directory_selected_member_read_failure_fix_authorization.md',
            'backend/tests/Feature/Milestone3elMemberDirectorySelectedMemberReadFailureFixAuthorizationTest.php',
            'backend/tests/Feature/Milestone3ekHumanOperatorControlledUseFailureReviewScopeGateTest.php', // Allowed narrow exception if modified
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EL may change only approved 3EL docs/tests, plus the narrow 3EK lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EL.");
        }
    }

    public function test_3el_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EL.");
        }
    }

    private function scopeDocument(): string
    {
        $this->assertFileExists($this->docPath);
        return file_get_contents($this->docPath);
    }

    private function currentChangedFiles(): array
    {
        $repoRoot = dirname(__DIR__, 3);
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';

        return collect(preg_split('/\R/', trim($status)))
            ->filter()
            ->map(function (string $line): string {
                $path = preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line;
                $path = trim($path);
                if (str_contains($path, ' -> ')) {
                    $path = trim(substr($path, strrpos($path, ' -> ') + 4));
                }

                return str_replace('\\', '/', $path);
            })
            ->values()
            ->all();
    }

    private function blockedPaths(): array
    {
        return [
            'app/page.tsx',
            'backend/routes/api.php',
            'backend/app/Http/Controllers',
            'backend/app/Http/Requests',
            'backend/app/Http/Resources',
            'backend/app/Services',
            'backend/app/Models',
            'backend/database/migrations',
            'backend/database/seeders',
            'backend/database/factories',
            'backend/app/Policies',
            'backend/app/Providers',
            'backend/app/Http/Middleware',
            'backend/config',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'pnpm-lock.yaml',
            'yarn.lock',
            'task.md',
            'walkthrough.md',
        ];
    }

    private function containsPathOrChild(array $changed, string $blockedPath): bool
    {
        return collect($changed)->contains(function (string $path) use ($blockedPath): bool {
            return $path === $blockedPath || str_starts_with($path, rtrim($blockedPath, '/').'/');
        });
    }
}
