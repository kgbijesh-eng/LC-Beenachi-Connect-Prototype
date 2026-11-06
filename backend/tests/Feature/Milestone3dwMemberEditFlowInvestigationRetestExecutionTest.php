<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone3dwMemberEditFlowInvestigationRetestExecutionTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__.'/../../docs/milestone_3dw_member_edit_flow_investigation_retest_execution.md';

    public function test_3dw_creates_member_edit_flow_investigation_retest_execution_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DW — Member Edit Flow Investigation and Retest Execution',
            '3DW investigates and retests DN-020 only.',
            '3DW does not implement fixes.',
            '3DW does not edit runtime code.',
            '3DW does not edit frontend runtime code.',
            '3DW does not edit app/page.tsx.',
            '3DW does not change backend runtime behavior.',
            '3DW does not edit member controllers, member requests, member resources, member services, routes, models, migrations, seeders, factories, policies, middleware, config, package files, or lock files.',
            '3DW does not perform a full manual acceptance execution.',
            '3DW does not deploy.',
            '3DW does not use production data.',
            '3DW does not use real club member records.',
            'Relationship to corrected 3DN',
            'Relationship to 3DO',
            'Relationship to 3DP',
            'Relationship to 3DQ',
            'Relationship to 3DR',
            'Relationship to 3DS',
            'Relationship to 3DT',
            'Relationship to 3DU',
            'Relationship to 3DV',
            'Relationship to member module lanes',
            'Relationship to MVP frontend usability lanes',
            'Corrected 3DN result before 3DW',
            'NO-GO PENDING DEFECT FIXES',
            'DN-010',
            'fixed by 3DQ',
            'DN-031',
            'fixed by 3DS',
            'DN-047',
            'PASS after 3DU',
            'DN-020 observed failure',
            'DN-020 classification before 3DW',
            'investigation/retest method used',
            'investigation/retest data used',
            'investigation/retest steps executed',
            'backend member create/update/read evidence',
            'frontend edit-flow investigation evidence',
            'DN-020 final classification after 3DW',
            'FAIL',
            'Explanation for DN-020 classification',
            'no runtime fix was implemented in 3DW',
            'GO/NO-GO interpretation',
            'no new full manual acceptance execution was performed in 3DW',
            'no production data or real member records were used',
            'no deployment occurred',
            'unsupported feature exclusions',
            'receipt PDF clarification',
            'profile document upload/download/PDF/OCR exclusion',
            'risk notes',
            'investigation/retest decision',
            'expected next milestone',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3dw_current_file_changes_are_limited_to_approved_investigation_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3dw_member_edit_flow_investigation_retest_execution.md',
            'backend/tests/Feature/Milestone3dwMemberEditFlowInvestigationRetestExecutionTest.php',
            'backend/tests/Feature/Milestone3dvMemberEditFlowDefectScopeGateTest.php',
            'backend/docs/milestone_3dx_member_edit_flow_defect_implementation_scope_gate.md',
            'backend/tests/Feature/Milestone3dxMemberEditFlowDefectImplementationScopeGateTest.php',
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

        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        $this->assertNotSame('', $branch, 'The repository branch must be readable.');

        // The original milestone owns this allowlist. Blocked paths below apply on every branch.
        if ($branch === 'milestone-3dw-member-edit-flow-investigation-retest-execution') {
            $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DW may change only approved 3DW docs/tests, plus the narrow 3DV lifecycle guard exception.');
        }

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3DW.");
        }
    }

    public function test_3dw_app_page_tsx_is_not_edited(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $diff = shell_exec('git -C '.escapeshellarg($repoRoot).' diff --name-only HEAD') ?? '';
        $changed = collect(preg_split('/\R/', trim($diff)))->filter()->all();
        $this->assertNotContains('app/page.tsx', $changed);
    }

    public function test_3dw_backend_member_create_update_read_works_through_api(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $president = User::where('email', 'president@lcbeenachi.test')->firstOrFail();
        $token = $president->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        // 1. Create member
        $createResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members", [
                'member_code' => 'LCB-999',
                'full_name' => 'Synthetic Local Test Member',
                'phone' => '1234567890',
                'status' => 'active',
            ]);
        $createResponse->assertCreated();
        $memberId = $createResponse->json('member.id');

        // 2. Read member
        $readResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$memberId}");
        $readResponse->assertOk()
            ->assertJsonPath('member.full_name', 'Synthetic Local Test Member');

        // 3. Update member
        $updateResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$memberId}", [
                'full_name' => 'Updated Synthetic Member',
            ]);
        $updateResponse->assertOk()
            ->assertJsonPath('member.full_name', 'Updated Synthetic Member');

        // 4. Read updated member
        $readUpdatedResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$memberId}");
        $readUpdatedResponse->assertOk()
            ->assertJsonPath('member.full_name', 'Updated Synthetic Member');
    }

    public function test_3dw_frontend_static_analysis_verifies_no_active_member_edit_api_call(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $frontendFile = $repoRoot.'/app/page.tsx';
        $this->assertFileExists($frontendFile);

        $content = file_get_contents($frontendFile);

        // Verify that PATCH or PUT is not called against direct member URLs
        $hasDirectMemberPatch = preg_match('/fetch\s*\(\s*API_BASE_URL\s*\+\s*["\']\/clubs\/["\']\s*\+\s*\w+\s*\+\s*["\']\/members\/["\']\s*\+\s*\w+\s*,[^}]*method:\s*["\']PATCH["\']/is', $content);
        $hasDirectMemberPut = preg_match('/fetch\s*\(\s*API_BASE_URL\s*\+\s*["\']\/clubs\/["\']\s*\+\s*\w+\s*\+\s*["\']\/members\/["\']\s*\+\s*\w+\s*,[^}]*method:\s*["\']PUT["\']/is', $content);

        $this->assertFalse((bool) $hasDirectMemberPatch, 'app/page.tsx should not call PATCH on member endpoints directly');
        $this->assertFalse((bool) $hasDirectMemberPut, 'app/page.tsx should not call PUT on member endpoints directly');
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
