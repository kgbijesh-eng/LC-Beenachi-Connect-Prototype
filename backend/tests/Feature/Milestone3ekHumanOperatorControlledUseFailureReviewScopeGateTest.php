<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ekHumanOperatorControlledUseFailureReviewScopeGateTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3ek_human_operator_controlled_use_failure_review_scope_gate.md';

    public function test_3ek_creates_failure_review_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EK — Human Operator Controlled Use Failure Review Scope Gate',
            '3EK is a docs/tests-only human operator controlled-use failure review scope gate.',
            '3EJ is closed, pushed, remote verified, and tracking clean.',
            '3EJ was true human/operator controlled-use execution.',
            '3EJ overall result was FAIL.',
            'Module 3 was FAIL.',
            'Modules 4 through 14 were NOT RUN due to stop condition.',
            'MEM-002',
            'MEM-003',
            'MEM-004',
            'MEM-001',
            'Lion Bijesh / MEM-001 - President',
            'Selected member was not found in backend member records.',
            'Controlled internal MVP remains blocked.',
            'Limited internal member pilot is not allowed.',
            'Production/SaaS/deployment/public launch remain NO-GO.',
            'No runtime fix is implemented in 3EK.',
            'No new human retest is executed in 3EK.',
            'Next milestone is Milestone 3EL — Member Directory Selected Member Read Failure Fix Authorization.',
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
            'Controlled internal MVP execution: FAIL',
            'Controlled internal MVP use: Must stop pending defect review.',
            'Production GO: No.',
            'SaaS GO: No.',
            'Deployment GO: No.',
            'Public launch GO: No.',
            'Expected Next Milestone: Milestone 3EL — Member Directory Selected Member Read Failure Fix Authorization',
            'Risk Notes',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3ek_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3ek-human-operator-controlled-use-failure-review-scope-gate') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3ek_human_operator_controlled_use_failure_review_scope_gate.md',
            'backend/tests/Feature/Milestone3ekHumanOperatorControlledUseFailureReviewScopeGateTest.php',
            'backend/tests/Feature/Milestone3ejHumanOperatorControlledUseExecutionTest.php', // Allowed narrow exception if modified
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EK may change only approved 3EK docs/tests, plus the narrow 3EJ lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EK.");
        }
    }

    public function test_3ek_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EK.");
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
