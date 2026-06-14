<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3dxMemberEditFlowDefectImplementationScopeGateTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3dx_member_edit_flow_defect_implementation_scope_gate.md';

    public function test_3dx_creates_member_edit_flow_defect_implementation_scope_gate_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DX — Member Edit Flow Defect Implementation Scope Gate',
            '3DX scopes the future implementation fix for DN-020 only.',
            '3DX does not implement the frontend fix.',
            '3DX does not edit runtime code.',
            '3DX does not edit frontend runtime code.',
            '3DX does not edit app/page.tsx.',
            '3DX does not change backend runtime behavior.',
            '3DX does not edit member controllers, member requests, member resources, member services, routes, models, migrations, seeders, factories, policies, middleware, config, package files, or lock files.',
            '3DX does not execute a new member edit-flow retest.',
            '3DX does not perform a full manual acceptance execution.',
            '3DX does not deploy.',
            '3DX does not use production data.',
            '3DX does not use real club member records.',
            'Relationship to corrected 3DN',
            'Relationship to 3DO',
            'Relationship to 3DP',
            'Relationship to 3DQ',
            'Relationship to 3DR',
            'Relationship to 3DS',
            'Relationship to 3DT',
            'Relationship to 3DU',
            'Relationship to 3DV',
            'Relationship to 3DW',
            'Relationship to member module lanes',
            'Relationship to MVP frontend usability lanes',
            'Corrected 3DN result',
            'NO-GO PENDING DEFECT FIXES',
            'DN-010',
            'fixed by 3DQ',
            'DN-031',
            'fixed by 3DS',
            'DN-047',
            'PASS after 3DU',
            'DN-020',
            'FAIL after 3DW',
            'backend member create/read/update evidence from 3DW',
            'frontend gap confirmed by 3DW',
            'confirmed frontend/member edit-flow implementation defect',
            '3DY implementation objective',
            '3DY implementation boundary',
            '3DY allowed files/categories',
            '3DY blocked files/categories',
            '3DY validation requirements',
            'DN-020 was not fixed in 3DX',
            'no member edit-flow retest was executed in 3DX',
            'GO/NO-GO interpretation',
            'no new full manual acceptance execution was performed in 3DX',
            'no production data or real member records were used',
            'no deployment occurred',
            'unsupported feature exclusions',
            'receipt PDF clarification',
            'profile document upload/download/PDF/OCR exclusion',
            'risk notes',
            'scope gate decision',
            'expected next milestone',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3dx_current_file_changes_are_limited_to_approved_scope_gate_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3dx_member_edit_flow_defect_implementation_scope_gate.md',
            'backend/tests/Feature/Milestone3dxMemberEditFlowDefectImplementationScopeGateTest.php',
            'backend/tests/Feature/Milestone3dwMemberEditFlowInvestigationRetestExecutionTest.php',
        ];

        $repoRoot = dirname(__DIR__, 3);
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';
        $changed = collect(preg_split('/\R/', trim($status)))
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

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DX may change only approved 3DX docs/tests, plus the narrow 3DW lifecycle guard exception.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3DX.");
        }
    }

    public function test_3dx_app_page_tsx_is_not_edited(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $diff = shell_exec('git -C '.escapeshellarg($repoRoot).' diff --name-only HEAD') ?? '';
        $changed = collect(preg_split('/\R/', trim($diff)))->filter()->all();
        $this->assertNotContains('app/page.tsx', $changed);
    }

    private function scopeDocument(): string
    {
        $this->assertFileExists($this->docPath);
        return file_get_contents($this->docPath);
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
