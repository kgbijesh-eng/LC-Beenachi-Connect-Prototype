<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ebMemberEditFlowManualBrowserRetestExecutionTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3eb_member_edit_flow_manual_browser_retest_execution.md';

    public function test_3eb_creates_manual_browser_retest_execution_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EB — Member Edit Flow Manual Browser Retest Execution',
            '3EB executes the manual local browser retest path defined by 3EA for DN-020.',
            '3EB uses the user\'s Windows GUI browser environment and synthetic local/test data only.',
            '3EB does not implement any runtime fix.',
            '3EB does not add E2E/browser automation tooling.',
            '3EB does not edit app/page.tsx or any frontend/backend runtime file.',
            '3EB classifies DN-020 as PASS, FAIL, or BLOCKED based on manual browser evidence.',
            'Relationship to 3EA',
            'DN-020 was BLOCKED before 3EB.',
            'Retest environment / evidence collection blocker',
            'Controlled manual local browser retest on the user\'s Windows GUI environment using synthetic local/test data.',
            'The 3DY frontend member edit/update flow.',
            'PATCH /api/v1/clubs/{club}/members/{member}',
            'full_name',
            'phone',
            'email',
            'blood_group',
            'status',
            'Local / Browser Retest Method',
            'Evidence Collection Rules',
            'Manual Browser Evidence Collected',
            'Seeded local Secretary / member-management persona.',
            'MEM-002 / Lion Secretary User',
            'Confirmation synthetic/local data only.',
            'Confirmation no production data.',
            'Confirmation no real member record.',
            'Confirmation no passwords/tokens recorded.',
            'Confirmation no deployment.',
            'Confirmation no runtime code was edited.',
            'Confirmation app/page.tsx was not edited.',
            'Confirmation no E2E/browser tooling was added.',
            'Confirmation no package/lock files were edited.',
            'Confirmation DN-020 was not fixed in 3EB.',
            'Final DN-020 classification:',
            'FAIL',
            'DN-020 — FAIL after 3EB manual browser retest.',
            'Reason for Classification',
            'PASS / FAIL / BLOCKED Rule Mapping',
            'GO: No.',
            'NO-GO: Yes, pending DN-020 failure fix scope gate and later fix/retest closure.',
            'NO-GO PENDING DEFECT FIXES',
            'Milestone 3EC — Member Edit Flow Manual Retest Failure Fix Scope Gate',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3eb_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3eb-member-edit-flow-manual-browser-retest-execution') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3eb_member_edit_flow_manual_browser_retest_execution.md',
            'backend/tests/Feature/Milestone3ebMemberEditFlowManualBrowserRetestExecutionTest.php',
            'backend/tests/Feature/Milestone3eaMemberEditFlowRetestBlockerResolutionScopeGateTest.php',
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EB may change only approved 3EB docs/tests, plus the narrow 3EA lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EB.");
        }
    }

    public function test_3eb_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EB.");
        }
    }

    public function test_3eb_document_records_exactly_one_final_classification_and_next_milestone(): void
    {
        $document = $this->scopeDocument();

        $classificationMatches = [];
        preg_match('/Final DN-020 classification:\s*-\s*(PASS|FAIL|BLOCKED)/i', $document, $classificationMatches);

        $this->assertNotEmpty($classificationMatches, '3EB must record a final DN-020 classification.');
        $this->assertContains($classificationMatches[1], ['PASS', 'FAIL', 'BLOCKED']);
        $this->assertSame('FAIL', strtoupper($classificationMatches[1]));

        foreach ([
            'FAIL applies because:',
            'Success state is missing or not visible after save.',
            'Updated data is stale/inconsistent after refresh.',
            'Expected next milestone based on FAIL classification:',
            'Milestone 3EC — Member Edit Flow Manual Retest Failure Fix Scope Gate',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
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
