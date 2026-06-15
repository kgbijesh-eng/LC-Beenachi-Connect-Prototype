<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3egInternalMvpControlledUseExecutionScopeGateTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3eg_internal_mvp_controlled_use_execution_scope_gate.md';

    public function test_3eg_creates_controlled_use_execution_scope_gate_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EG — Internal MVP Controlled Use Execution Scope Gate',
            '3EG is a docs/tests-only controlled-use execution scope gate.',
            'Milestone 3EG scopes the first controlled-use execution boundary after the 3EF handoff checklist.',
            '3EG does not execute controlled use.',
            '3EG does not onboard real users.',
            '3EG does not use production data or real club member records.',
            '3EG does not deploy the app.',
            'Controlled-use execution remains NOT STARTED in 3EG.',
            'Internal MVP remains GO only for controlled internal MVP use / internal pilot review.',
            'Production readiness, SaaS readiness, deployment readiness, and public launch readiness remain NO.',
            'Expected next milestone:',
            'Milestone 3EH — Internal MVP Controlled Use Execution',
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
            '1. Current MVP Status',
            '2. Known Blocking Defect Closure Summary',
            '3. 3EF Handoff Checklist Summary',
            '4. Controlled-Use Execution Status',
            '5. Controlled-Use Execution Boundary for 3EH',
            '6. Not-Allowed Execution Boundary',
            '7. 3EH Recommended Execution Objective',
            '8. 3EH Recommended Execution Sequence',
            '9. 3EH Module Coverage',
            '10. 3EH Allowed Data Policy',
            '11. 3EH Evidence Policy',
            '12. 3EH PASS / FAIL / BLOCKED Classification Rules',
            '13. 3EH Stop Conditions',
            'no runtime code was edited in 3EG',
            'no production data or real member records were used',
            'no deployment occurred',
            'Controlled-use execution scope: GO for next milestone.',
            'Controlled-use execution: Not started by 3EG.',
            'Production GO: No.',
            'SaaS GO: No.',
            'Deployment GO: No.',
            'Public launch GO: No.',
            'Current recommendation: proceed to controlled-use execution under the 3EH boundary.',
            'Risk Notes',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3eg_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3eg-internal-mvp-controlled-use-execution-scope-gate') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3eg_internal_mvp_controlled_use_execution_scope_gate.md',
            'backend/tests/Feature/Milestone3egInternalMvpControlledUseExecutionScopeGateTest.php',
            'backend/tests/Feature/Milestone3efInternalMvpControlledUseHandoffOperatingChecklistTest.php', // Allowed narrow exception if modified
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EG may change only approved 3EG docs/tests, plus the narrow 3EF lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EG.");
        }
    }

    public function test_3eg_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EG.");
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
