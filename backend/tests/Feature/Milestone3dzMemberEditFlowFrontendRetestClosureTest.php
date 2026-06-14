<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone3dzMemberEditFlowFrontendRetestClosureTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__.'/../../docs/milestone_3dz_member_edit_flow_frontend_retest_closure.md';

    public function test_3dz_creates_member_edit_flow_frontend_retest_closure_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DZ — Member Edit Flow Frontend Retest and Closure',
            '3DZ retests DN-020 only.',
            '3DZ classifies DN-020 as:',
            'BLOCKED',
            '3DZ does not implement new fixes.',
            '3DZ does not edit `app/page.tsx`.',
            '3DZ does not edit frontend runtime code.',
            '3DZ does not edit backend runtime code.',
            '3DZ does not change member backend runtime code.',
            '3DZ does not perform a full manual acceptance execution.',
            '3DZ does not deploy.',
            '3DZ does not use production data.',
            '3DZ does not use real club member records.',
            'DN-020 Status before 3DZ',
            '3DY Implementation Summary',
            'PATCH /api/v1/clubs/{club}/members/{member}',
            'Editable Fields Retested',
            'Retest Method Used',
            'Retest Data Used',
            'Retest Steps Executed',
            'Backend/API Regression Evidence',
            'Frontend/browser retest evidence',
            'DN-020 Final Classification',
            'Explanation for DN-020 classification',
            'Confirmation that no runtime fix was implemented',
            'Confirmation that `app/page.tsx` was not edited in 3DZ',
            'Confirmation that no production data or real member records were used',
            'Confirmation that no deployment occurred',
            'Relationship to corrected 3DN',
            'Relationship to 3DQ',
            'Relationship to 3DS',
            'Relationship to 3DU',
            'Relationship to 3DV',
            'Relationship to 3DW',
            'Relationship to 3DX',
            'Relationship to 3DY',
            'DN-010',
            'fixed by 3DQ',
            'DN-031',
            'fixed by 3DS',
            'DN-047',
            'PASS after 3DU',
            'DN-020 closure/retest status after 3DZ',
            'Go/No-Go Interpretation',
            'Milestone 3DZ attempted the targeted DN-020 member edit-flow frontend retest.',
            'Next work should be Milestone 3EA — Member Edit Flow Retest Blocker Resolution Scope Gate.',
            'Expected Next Milestone',
            'Milestone 3EA — Member Edit Flow Retest Blocker Resolution Scope Gate',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3dz_current_file_changes_are_limited_to_approved_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3dz_member_edit_flow_frontend_retest_closure.md',
            'backend/tests/Feature/Milestone3dzMemberEditFlowFrontendRetestClosureTest.php',
            'backend/tests/Feature/Milestone3dyMemberEditFlowFrontendImplementationTest.php',
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

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DZ may change only approved 3DZ files, plus the narrow 3DY lifecycle guard exception.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3DZ.");
        }
    }

    public function test_3dz_app_page_tsx_is_not_edited_in_3dz(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $diff = shell_exec('git -C '.escapeshellarg($repoRoot).' diff --name-only HEAD') ?? '';
        $changed = collect(preg_split('/\R/', trim($diff)))->filter()->all();
        $this->assertNotContains('app/page.tsx', $changed);
    }

    public function test_3dz_backend_member_create_read_update_remains_functional_with_synthetic_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $president = User::where('email', 'president@lcbeenachi.test')->firstOrFail();
        $token = $president->createToken('test-token')->plainTextToken;

        // 1. Create a synthetic member
        $createResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members", [
                'member_code' => 'LCB-888',
                'full_name' => 'Synthetic Retest Member',
                'phone' => '5551234',
                'status' => 'active',
            ]);
        $createResponse->assertCreated();
        $memberId = $createResponse->json('member.id');

        // 2. Read the member
        $readResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$memberId}");
        $readResponse->assertOk()
            ->assertJsonPath('member.full_name', 'Synthetic Retest Member');

        // 3. Update the member (retest PATCH route)
        $updateResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$memberId}", [
                'full_name' => 'Updated Synthetic Retest Member',
                'phone' => '5559999',
                'email' => 'synthetic-retest@lcbeenachi.test',
                'blood_group' => 'A+',
                'status' => 'inactive',
            ]);
        $updateResponse->assertOk()
            ->assertJsonPath('member.full_name', 'Updated Synthetic Retest Member')
            ->assertJsonPath('member.phone', '5559999')
            ->assertJsonPath('member.email', 'synthetic-retest@lcbeenachi.test')
            ->assertJsonPath('member.blood_group', 'A+')
            ->assertJsonPath('member.status', 'inactive');
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
