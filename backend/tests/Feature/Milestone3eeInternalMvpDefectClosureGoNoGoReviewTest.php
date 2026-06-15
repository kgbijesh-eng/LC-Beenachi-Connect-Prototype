<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3eeInternalMvpDefectClosureGoNoGoReviewTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3ee_internal_mvp_defect_closure_go_no_go_review.md';

    public function test_3ee_creates_defect_closure_go_no_go_review_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EE — Internal MVP Defect Closure and Go/No-Go Review',
            '3EE is a docs/tests-only internal MVP defect closure and go/no-go review.',
            'DN-010 is FIXED by 3DQ',
            'DN-031 is FIXED by 3DS',
            'DN-047 is PASS after 3DU',
            'DN-020 is PASS after 3ED',
            'The known Internal MVP blocking defect list is closed as of 3EE.',
            'Internal MVP is GO for controlled internal use / internal pilot review.',
            'This GO decision does not certify production readiness, SaaS readiness, deployment readiness, or public launch readiness.',
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
            'Confirmation that 3EE did not retest defects.',
            'Confirmation that 3EE did not implement fixes.',
            'Confirmation that no runtime code was edited in 3EE.',
            'Confirmation that app/page.tsx was not edited in 3EE.',
            'Confirmation that no backend runtime code was edited in 3EE.',
            'Confirmation that no E2E/browser tooling was added.',
            'Confirmation that no package/lock files were edited.',
            'Confirmation that no production data or real member records were used.',
            'Confirmation that no deployment occurred.',
            'GO: Yes, for controlled internal MVP use / internal pilot review.',
            'NO-GO PENDING DEFECT FIXES: No, because the known blocking defects are closed.',
            'Production GO: No.',
            'SaaS GO: No.',
            'Deployment GO: No.',
            'Public launch GO: No.',
            'Current recommendation: proceed to controlled internal MVP handoff / operating checklist.',
            'Expected next milestone:',
            'Milestone 3EF — Internal MVP Controlled Use Handoff and Operating Checklist',
            'Risk Notes',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3ee_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3ee-internal-mvp-defect-closure-go-no-go-review') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3ee_internal_mvp_defect_closure_go_no_go_review.md',
            'backend/tests/Feature/Milestone3eeInternalMvpDefectClosureGoNoGoReviewTest.php',
            'backend/tests/Feature/Milestone3edMemberEditFlowFrontendPostSaveConsistencyFixTest.php', // Allowed narrow exception if modified
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EE may change only approved 3EE docs/tests, plus the narrow 3ED lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EE.");
        }
    }

    public function test_3ee_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EE.");
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
