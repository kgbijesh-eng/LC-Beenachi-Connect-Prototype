<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ejHumanOperatorControlledUseExecutionTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3ej_human_operator_controlled_use_execution.md';

    public function test_3ej_creates_controlled_use_execution_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EJ — Human Operator Controlled Use Execution',
            '3EJ executes the first controlled internal MVP use checklist under the 3EG boundary.',
            '3EJ uses local services, seeded/local personas, and synthetic/local/test data only.',
            '3EJ does not deploy the app.',
            '3EJ does not onboard real users.',
            '3EJ does not use production data or real club member records.',
            '3EJ does not implement runtime fixes.',
            'Overall 3EJ Classification: **FAIL**',
            'Relationship to 3EI',
            'Current Internal MVP Status before 3EJ',
            'Known Blocking Defect Closure Summary',
            'Controlled-Use Boundary Used',
            'Data Policy Used',
            'Persona Policy Used',
            'Evidence Policy Used',
            'Local Service Startup Evidence',
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
            'Overall 3EJ Classification: FAIL',
            'Controlled internal MVP execution: FAIL',
            'Controlled internal MVP use: Must stop pending defect review.',
            'Production GO: No.',
            'SaaS GO: No.',
            'Deployment GO: No.',
            'Public launch GO: No.',
            'Expected Next Milestone: Milestone 3EK — Human Operator Controlled Use Failure Review Scope Gate.',
            'Risk Notes',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3ej_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3ej-human-operator-controlled-use-execution') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3ej_human_operator_controlled_use_execution.md',
            'backend/tests/Feature/Milestone3ejHumanOperatorControlledUseExecutionTest.php',
            'backend/tests/Feature/Milestone3eiControlledUseExecutionEvidenceCorrectionHumanOperatorRetestScopeGateTest.php', // Allowed narrow exception if modified
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EJ may change only approved 3EJ docs/tests, plus the narrow 3EI lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EJ.");
        }
    }

    public function test_3ej_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EJ.");
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
