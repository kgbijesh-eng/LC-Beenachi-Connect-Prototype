<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ecMemberEditFlowManualRetestFailureFixScopeGateTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3ec_member_edit_flow_manual_retest_failure_fix_scope_gate.md';

    public function test_3ec_creates_failure_fix_scope_gate_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EC — Member Edit Flow Manual Retest Failure Fix Scope Gate',
            '3EC is a docs/tests-only failure fix scope gate for DN-020.',
            '3EC does not retest DN-020.',
            '3EC does not fix DN-020.',
            '3EC does not edit runtime code.',
            '3EC does not edit frontend runtime code.',
            '3EC does not edit backend runtime code.',
            '3EC does not edit `app/page.tsx`.',
            '3EC does not add E2E/browser automation tooling.',
            '3EC does not edit package files or lock files.',
            '3EC does not perform a full manual acceptance execution.',
            '3EC does not deploy.',
            '3EC does not use production data.',
            '3EC does not use real club member records.',
            'Relationship to 3DN',
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
            'DN-010',
            'DN-031',
            'DN-047',
            'DN-020',
            'FAIL after 3EB',
            '3EB manual retest evidence summary',
            'PATCH/backend persistence evidence from 3EB',
            'Missing success/confirmation evidence from 3EB',
            'Stale refreshed UI evidence from 3EB',
            'Failure Classification',
            'Frontend post-save consistency / confirmation defect',
            'why this is not primarily',
            'Backend member PATCH API defect',
            'Authorization defect',
            'Database persistence defect',
            'Deployment blocker',
            'SaaS blocker',
            'E2E tooling blocker',
            'Available Fix Path Options Considered',
            'Recommended Narrow Fix Path',
            'Why a narrow frontend fix is preferred',
            'Expected 3ED Objective',
            'Expected 3ED Allowed Implementation Area',
            'Expected 3ED Fix Requirements',
            'Expected 3ED Validation/Retest Path',
            'PASS / FAIL / BLOCKED Rules for Post-Fix Retest after 3ED',
            'GO: No',
            'NO-GO: Yes',
            'Current recommendation: NO-GO PENDING DEFECT FIXES',
            'Expected next milestone',
            'Milestone 3ED — Member Edit Flow Frontend Post-Save Consistency Fix Implementation',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3ec_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3ec-member-edit-flow-manual-retest-failure-fix-scope-gate') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3ec_member_edit_flow_manual_retest_failure_fix_scope_gate.md',
            'backend/tests/Feature/Milestone3ecMemberEditFlowManualRetestFailureFixScopeGateTest.php',
            'backend/tests/Feature/Milestone3ebMemberEditFlowManualBrowserRetestExecutionTest.php', // Allowed narrow exception if modified
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EC may change only approved 3EC docs/tests, plus the narrow 3EB lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EC.");
        }
    }

    public function test_3ec_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EC.");
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
        ];
    }

    private function containsPathOrChild(array $changed, string $blockedPath): bool
    {
        return collect($changed)->contains(function (string $path) use ($blockedPath): bool {
            return $path === $blockedPath || str_starts_with($path, rtrim($blockedPath, '/').'/');
        });
    }
}
