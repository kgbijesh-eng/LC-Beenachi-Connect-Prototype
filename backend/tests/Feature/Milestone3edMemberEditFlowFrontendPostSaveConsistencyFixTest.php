<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3edMemberEditFlowFrontendPostSaveConsistencyFixTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3ed_member_edit_flow_frontend_post_save_consistency_fix.md';

    public function test_3ed_creates_frontend_post_save_consistency_fix_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3ED — Member Edit Flow Frontend Post-Save Consistency Fix Implementation',
            '3ED is a frontend runtime fix implementation milestone for DN-020.',
            'Milestone 3ED implements the narrow frontend fix authorized by 3EC for DN-020.',
            'The fix is limited to member edit post-save success feedback and backend-consistent refreshed member data.',
            '3ED does not change backend runtime behavior.',
            '3ED does not add E2E/browser automation tooling.',
            '3ED does not use production data or real club member records.',
            'DN-020 status before 3ED',
            'FAIL after 3EB',
            '3EB failure evidence summary',
            'PATCH/backend persistence evidence',
            'Missing success/confirmation evidence',
            'Stale refreshed UI evidence',
            '3EC Failure Classification',
            'Frontend post-save consistency / confirmation defect',
            'Fix Objective',
            'Successful member PATCH response produces a visible success/confirmation state',
            'Frontend member profile/detail state is updated from the successful backend response or an immediate backend re-fetch',
            'Browser refresh shows backend-consistent updated member data',
            'PATCH failure still shows a safe visible error state',
            'Authorization is not weakened',
            'Backend authorization remains authoritative',
            'Files Changed',
            'app/page.tsx',
            'Runtime Implementation Summary',
            'Static object synchronization on mount/refresh',
            'Success feedback visibility',
            'Stale message cleanup',
            'TypeScript compatibility',
            'Scope Confirmations',
            'Confirmation app/page.tsx was edited only for DN-020',
            'Confirmation no backend runtime code was edited',
            'Confirmation no package/lock files were edited',
            'Confirmation no E2E/browser tooling was added',
            'Confirmation no production data or real member records were used',
            'Confirmation no deployment occurred',
            'Manual Browser Retest Method',
            'Browser/local environment used',
            'Persona type used',
            'Synthetic/local member used',
            'Field changed',
            'PATCH route evidence',
            'Success/confirmation evidence',
            'Updated data after save evidence',
            'Updated data after refresh evidence',
            'Final DN-020 classification:',
            'PASS',
            'Reason for Classification',
            'PASS / FAIL / BLOCKED Rule Mapping',
            'Go/No-Go Interpretation',
            'GO: Not automatically declared by 3ED.',
            'NO-GO PENDING DEFECT FIXES: cleared for DN-020 only.',
            'Current recommendation: proceed to internal MVP defect closure and go/no-go review.',
            'Expected next milestone:',
            'Milestone 3EE — Internal MVP Defect Closure and Go/No-Go Review',
            'Risk Notes',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3ed_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3ed-member-edit-flow-frontend-post-save-consistency-fix') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'app/page.tsx',
            'backend/docs/milestone_3ed_member_edit_flow_frontend_post_save_consistency_fix.md',
            'backend/tests/Feature/Milestone3edMemberEditFlowFrontendPostSaveConsistencyFixTest.php',
            'backend/tests/Feature/Milestone3ecMemberEditFlowManualRetestFailureFixScopeGateTest.php', // Allowed narrow exception if modified
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3ED may change only approved 3ED docs/tests/app, plus the narrow 3EC lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3ED.");
        }
    }

    public function test_3ed_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3ED.");
        }
    }

    public function test_3ed_document_records_exactly_one_final_classification_and_next_milestone(): void
    {
        $document = $this->scopeDocument();

        $classificationMatches = [];
        preg_match('/Final DN-020 classification:\s*-\s*(PASS|FAIL|BLOCKED)/i', $document, $classificationMatches);

        $this->assertNotEmpty($classificationMatches, '3ED must record a final DN-020 classification.');
        $this->assertContains($classificationMatches[1], ['PASS', 'FAIL', 'BLOCKED']);
        $this->assertSame('PASS', strtoupper($classificationMatches[1]));

        foreach ([
            'Final DN-020 classification:',
            'PASS',
            'Expected next milestone:',
            'Milestone 3EE — Internal MVP Defect Closure and Go/No-Go Review',
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
