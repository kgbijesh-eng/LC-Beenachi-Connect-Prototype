<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3efInternalMvpControlledUseHandoffOperatingChecklistTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3ef_internal_mvp_controlled_use_handoff_operating_checklist.md';

    public function test_3ef_creates_handoff_operating_checklist_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EF — Internal MVP Controlled Use Handoff and Operating Checklist',
            '3EF is a docs/tests-only controlled-use handoff and operating checklist.',
            'Milestone 3EF prepares the controlled internal MVP handoff and operating checklist after 3EE.',
            '3EF does not start controlled-use execution.',
            '3EF does not deploy the app.',
            '3EF does not onboard real users.',
            '3EF does not use production data or real club member records.',
            'Internal MVP remains GO only for controlled internal MVP use / internal pilot review preparation.',
            'Production readiness, SaaS readiness, deployment readiness, and public launch readiness remain NO.',
            'Expected next milestone:',
            'Milestone 3EG — Internal MVP Controlled Use Execution Scope Gate',
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
            '1. Current MVP status',
            '2. Known blocking defect closure summary',
            '3. Controlled-use purpose',
            '4. Controlled-use boundary',
            '5. Not-allowed boundary',
            '6. Roles/personas checklist',
            '7. Data safety checklist',
            '8. Local environment checklist',
            '9. Login and authorization checklist',
            '10. Member management checklist',
            '11. Finance/payment/receipt/report checklist',
            '12. Member import checklist',
            '13. Profile document checklist',
            '14. Defect reporting checklist',
            '15. Evidence collection checklist',
            '16. Stop/rollback checklist',
            '17. Handoff owner/operator checklist',
            '18. Next milestone checklist',
            'Roles/personas checklist',
            'Data safety checklist',
            'Local environment checklist',
            'Login and authorization checklist',
            'Member management checklist',
            'Finance/payment/receipt/report checklist',
            'Member import checklist',
            'Profile document checklist',
            'Defect reporting checklist',
            'Evidence collection checklist',
            'Stop/rollback checklist',
            'no runtime code was edited',
            'no production data or real member records',
            'no deployment occurred',
            'Controlled internal MVP handoff preparation: GO.',
            'Controlled-use execution: Not started by 3EF.',
            'Production GO: No.',
            'SaaS GO: No.',
            'Deployment GO: No.',
            'Public launch GO: No.',
            'Current recommendation: proceed to a controlled-use execution scope gate before any real operational use.',
            'Risk Notes',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3ef_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3ef-internal-mvp-controlled-use-handoff-operating-checklist') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3ef_internal_mvp_controlled_use_handoff_operating_checklist.md',
            'backend/tests/Feature/Milestone3efInternalMvpControlledUseHandoffOperatingChecklistTest.php',
            'backend/tests/Feature/Milestone3eeInternalMvpDefectClosureGoNoGoReviewTest.php', // Allowed narrow exception if modified
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EF may change only approved 3EF docs/tests, plus the narrow 3EE lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EF.");
        }
    }

    public function test_3ef_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EF.");
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
