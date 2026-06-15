<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3eaMemberEditFlowRetestBlockerResolutionScopeGateTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3ea_member_edit_flow_retest_blocker_resolution_scope_gate.md';

    public function test_3ea_creates_member_edit_flow_retest_blocker_resolution_scope_gate_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EA — Member Edit Flow Retest Blocker Resolution Scope Gate',
            'Milestone 3EA is a docs/tests-only blocker resolution scope gate for DN-020.',
            '3EA does not retest DN-020.',
            '3EA does not fix DN-020.',
            '3EA does not edit runtime code.',
            '3EA does not edit frontend runtime code.',
            '3EA does not edit backend runtime code.',
            '3EA does not edit `app/page.tsx`.',
            '3EA does not add E2E tooling.',
            '3EA does not edit package files or lock files.',
            'DN-020 remains BLOCKED after 3DZ.',
            '3DY implemented the frontend edit/update flow.',
            '3DZ could not complete actual browser retest.',
            'DN-020 remains unresolved only because frontend/browser retest evidence is missing.',
            'Retest environment / evidence collection blocker',
            'not classified as:',
            'Application runtime defect',
            'Backend member API defect',
            'Frontend implementation defect',
            'The recommended unblocker is a controlled manual local browser retest on the user\'s Windows GUI environment using synthetic local/test data.',
            'Manual local browser retest is preferred over adding E2E tooling now',
            '3EB Objective',
            'Run a controlled manual local browser retest of the 3DY frontend member edit/update flow using synthetic local/test data and an authorized seeded President/Admin persona, then classify DN-020 as PASS, FAIL, or BLOCKED.',
            '3EB Evidence Collection Requirements',
            'Frontend and backend local services started.',
            'Login performed as seeded authorized President/Admin or member-management persona.',
            'People / Members area opened.',
            'Synthetic/local member record opened.',
            'Edit mode opened.',
            'At least one safe supported field changed.',
            'Save clicked.',
            'Update request sent to backend PATCH member route.',
            'Success/confirmation state visible.',
            'Updated data visible/consistent after save or refresh.',
            'Local services stopped after retest.',
            '3EB PASS / FAIL / BLOCKED Rules',
            'PASS:',
            'FAIL:',
            'BLOCKED:',
            'Confirmation that DN-020 was not retested in 3EA.',
            'Confirmation that DN-020 was not fixed in 3EA.',
            'Confirmation that no runtime code was edited in 3EA.',
            'Confirmation that no production data or real member records were used.',
            'Confirmation that no deployment occurred.',
            'Relationship to Milestone 3DN',
            'Relationship to Milestone 3DO',
            'Relationship to Milestone 3DQ',
            'Relationship to Milestone 3DS',
            'Relationship to Milestone 3DU',
            'Relationship to Milestone 3DV',
            'Relationship to Milestone 3DW',
            'Relationship to Milestone 3DX',
            'Relationship to Milestone 3DY',
            'Relationship to Milestone 3DZ',
            'DN-010 fixed by 3DQ.',
            'DN-031 fixed by 3DS.',
            'DN-047 PASS after 3DU.',
            'DN-020 remains BLOCKED pending manual browser retest execution.',
            'GO: No.',
            'NO-GO: Yes, pending DN-020 manual browser retest execution and closure.',
            'Current recommendation: NO-GO PENDING DEFECT FIXES.',
            'Milestone 3EA scopes the resolution path for the DN-020 frontend/browser retest blocker.',
            'DN-020 remains BLOCKED in 3EA because no retest or runtime fix is executed here.',
            'The blocker is classified as a retest environment / evidence collection blocker, not a backend or frontend runtime defect.',
            'Next work should be Milestone 3EB — Member Edit Flow Manual Browser Retest Execution.',
            'Expected Next Milestone',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3ea_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3ea-member-edit-flow-retest-blocker-resolution-scope-gate') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3ea_member_edit_flow_retest_blocker_resolution_scope_gate.md',
            'backend/tests/Feature/Milestone3eaMemberEditFlowRetestBlockerResolutionScopeGateTest.php',
            'backend/tests/Feature/Milestone3dzMemberEditFlowFrontendRetestClosureTest.php',
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EA may change only approved 3EA docs/tests, plus the narrow 3DZ lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EA.");
        }
    }

    public function test_3ea_app_page_frontend_backend_runtime_and_package_files_are_not_edited(): void
    {
        $changed = $this->currentChangedFiles();

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EA.");
        }
    }

    public function test_3ea_document_records_required_scope_decisions(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'DN-020 remains BLOCKED after 3DZ.',
            'Retest environment / evidence collection blocker',
            'The recommended unblocker is a controlled manual local browser retest on the user\'s Windows GUI environment using synthetic local/test data.',
            'Manual local browser retest is preferred over adding E2E tooling now',
            '3EB should collect evidence for:',
            'Authorized persona successfully edits a synthetic/local member through the frontend.',
            'Frontend edit flow is available but save fails due to application behavior.',
            'Manual browser retest cannot be completed due to local setup/tooling/operator environment issue outside application behavior.',
            'Confirmation that DN-020 was not retested in 3EA.',
            'Confirmation that DN-020 was not fixed in 3EA.',
            'Confirmation that no runtime code was edited in 3EA.',
            'Confirmation that no production data or real member records were used.',
            'Confirmation that no deployment occurred.',
            'GO: No.',
            'NO-GO: Yes, pending DN-020 manual browser retest execution and closure.',
            'Expected next milestone:',
            'Milestone 3EB — Member Edit Flow Manual Browser Retest Execution.',
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
