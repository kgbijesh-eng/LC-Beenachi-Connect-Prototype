<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3eiControlledUseExecutionEvidenceCorrectionHumanOperatorRetestScopeGateTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3ei_controlled_use_execution_evidence_correction_human_operator_retest_scope_gate.md';

    public function test_3ei_creates_evidence_correction_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EI — Controlled Use Execution Evidence Correction and Human Operator Retest Scope Gate',
            '3EI is a docs/tests-only controlled use evidence correction and human operator retest scope gate.',
            'Milestone 3EI corrects the interpretation of the 3EH controlled-use execution.',
            '3EH is closed/pushed/remote verified.',
            '3EH evidence is now interpreted as Codex-assisted local technical verification only.',
            'Human/operator controlled-use execution was not completed.',
            'The user did not personally try the app.',
            'Internal MVP is not yet human-operator verified.',
            'Production/SaaS/deployment/public launch remain NO-GO.',
            'No runtime changes are made in 3EI.',
            'No retest is executed in 3EI.',
            'Next milestone must be Milestone 3EJ — Human Operator Controlled Use Execution.',
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
            'Controlled internal MVP execution: NO-GO',
            'Controlled-use execution: Not started/not completed by a human operator.',
            'Production GO: No.',
            'SaaS GO: No.',
            'Deployment GO: No.',
            'Public launch GO: No.',
            'Expected Next Milestone:',
            'Milestone 3EJ — Human Operator Controlled Use Execution',
            'Risk Notes',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3ei_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3ei-controlled-use-execution-evidence-correction-human-operator-retest-scope-gate') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3ei_controlled_use_execution_evidence_correction_human_operator_retest_scope_gate.md',
            'backend/tests/Feature/Milestone3eiControlledUseExecutionEvidenceCorrectionHumanOperatorRetestScopeGateTest.php',
            'backend/tests/Feature/Milestone3ehInternalMvpControlledUseExecutionTest.php', // Allowed narrow exception if modified
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EI may change only approved 3EI docs/tests, plus the narrow 3EH lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EI.");
        }
    }

    public function test_3ei_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EI.");
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
