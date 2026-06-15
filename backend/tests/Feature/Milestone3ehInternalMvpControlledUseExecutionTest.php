<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ehInternalMvpControlledUseExecutionTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3eh_internal_mvp_controlled_use_execution.md';

    public function test_3eh_creates_controlled_use_execution_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EH — Internal MVP Controlled Use Execution',
            '3EH executes the first controlled internal MVP use checklist under the 3EG boundary.',
            '3EH uses local services, seeded/local personas, and synthetic/local/test data only.',
            '3EH does not deploy the app.',
            '3EH does not onboard real users.',
            '3EH does not use production data or real club member records.',
            '3EH does not implement runtime fixes.',
            'Overall 3EH Classification: **PASS**',
            'Relationship to 3EG',
            'Current Internal MVP Status before 3EH',
            'Known Blocking Defect Closure Summary',
            'Controlled-Use Boundary Used',
            'Data Policy Used',
            'Persona Policy Used',
            'Evidence Policy Used',
            'Local Service Startup Evidence',
            'Browser/Local Environment Used',
            'Module Checklist Table',
            'Login/session/logout',
            'Role-aware dashboard',
            'Member list/read',
            'Member create/edit',
            'Member edit post-save consistency',
            'Member finance summary',
            'Payment recording',
            'Receipt frontend/PDF lane',
            'Finance reports / balance sheet read view',
            'Payment history',
            'Member data import preview',
            'Member data import commit',
            'Member profile document metadata/read/create/update frontend',
            'MVP usability navigation',
            'Execution Summary',
            'Modules PASS',
            'Modules FAIL',
            'Modules BLOCKED',
            'Modules NOT RUN',
            'Stop Conditions',
            'no production data or real member records were used',
            'no real payment records were used',
            'no real users were onboarded',
            'no deployment occurred',
            'no runtime fixes were implemented',
            'no runtime code was edited',
            'no backend runtime code was edited',
            'no E2E/browser tooling was added',
            'no package/lock files were edited',
            'Overall 3EH Classification: PASS',
            'Controlled internal MVP execution: PASS.',
            'Controlled internal MVP use may proceed to a closure/review milestone.',
            'Production GO: No.',
            'SaaS GO: No.',
            'Deployment GO: No.',
            'Public launch GO: No.',
            'Expected Next Milestone: Milestone 3EI — Controlled Use Execution Closure and Pilot Readiness Review.',
            'Risk Notes',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3eh_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3eh-internal-mvp-controlled-use-execution') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3eh_internal_mvp_controlled_use_execution.md',
            'backend/tests/Feature/Milestone3ehInternalMvpControlledUseExecutionTest.php',
            'backend/tests/Feature/Milestone3egInternalMvpControlledUseExecutionScopeGateTest.php', // Allowed narrow exception if modified
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EH may change only approved 3EH docs/tests, plus the narrow 3EG lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EH.");
        }
    }

    public function test_3eh_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EH.");
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
