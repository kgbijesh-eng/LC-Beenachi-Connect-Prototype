<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone3dyMemberEditFlowFrontendImplementationTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__.'/../../docs/milestone_3dy_member_edit_flow_frontend_implementation.md';

    public function test_3dy_creates_member_edit_flow_frontend_implementation_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DY — Member Edit Flow Frontend Implementation',
            '3DY fixes DN-020 only.',
            '3DY implements the frontend member edit/update flow using the existing backend member update API.',
            '3DY keeps implementation narrow.',
            '3DY does not perform a full manual acceptance execution.',
            '3DY does not deploy.',
            '3DY does not use production data.',
            '3DY does not use real club member records.',
            'DN-020 Confirmed Defect from 3DW',
            'Backend Member Create/Read/Update Status from 3DW',
            'PATCH /api/v1/clubs/{club}/members/{member}',
            'Actual Editable Fields Implemented',
            'Implementation Summary',
            'Files Changed',
            'Confirmation that backend runtime was not edited',
            'Confirmation that member backend runtime was not edited',
            'Confirmation that payment/auth/import/profile/deployment/SaaS behavior was not changed',
            'Confirmation that no production data or real member records were used',
            'Confirmation that no deployment occurred',
            'Relationship to corrected 3DN',
            'Relationship to 3DO',
            'Relationship to 3DQ',
            'Relationship to 3DS',
            'Relationship to 3DU',
            'Relationship to 3DV',
            'Relationship to 3DW',
            'Relationship to 3DX',
            'DN-010',
            'fixed by 3DQ',
            'DN-031',
            'fixed by 3DS',
            'DN-047',
            'PASS after 3DU',
            'DN-020 Implementation Status after 3DY',
            'Validation Evidence',
            'Risk Notes',
            'Required Implementation Decision',
            'Go/No-Go Interpretation',
            'Expected Next Milestone',
            'Milestone 3DZ — Member Edit Flow Frontend Retest and Closure',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3dy_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3dy-member-edit-flow-frontend-implementation') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'app/page.tsx',
            'backend/docs/milestone_3dy_member_edit_flow_frontend_implementation.md',
            'backend/tests/Feature/Milestone3dyMemberEditFlowFrontendImplementationTest.php',
            'backend/tests/Feature/Milestone3dxMemberEditFlowDefectImplementationScopeGateTest.php',
        ];

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

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DY may change only approved 3DY files, plus the narrow 3DX lifecycle exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3DY.");
        }
    }

    public function test_3dy_backend_member_update_remains_functional_with_synthetic_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $president = User::where('email', 'president@lcbeenachi.test')->firstOrFail();
        $token = $president->createToken('test-token')->plainTextToken;

        // Create a synthetic member
        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-999',
            'full_name' => 'Original Synthetic Name',
            'phone' => '1234567890',
            'status' => 'active',
        ]);

        config(['instance.organisation_code' => $club->code]);
        // Send PATCH update request
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
                'full_name' => 'Updated Synthetic Name',
                'phone' => '0987654321',
                'email' => 'synthetic-updated@lcbeenachi.test',
                'status' => 'inactive',
                'blood_group' => 'O-',
            ]);

        $response->assertOk()
            ->assertJsonPath('member.full_name', 'Updated Synthetic Name')
            ->assertJsonPath('member.phone', '0987654321')
            ->assertJsonPath('member.email', 'synthetic-updated@lcbeenachi.test')
            ->assertJsonPath('member.status', 'inactive')
            ->assertJsonPath('member.blood_group', 'O-');
    }

    public function test_3dy_backend_authorization_expectations_are_preserved(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        // A normal user/member user who is unauthorized
        $normalUser = User::factory()->create();
        $token = $normalUser->createToken('test-token')->plainTextToken;

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-998',
            'full_name' => 'Test Member',
        ]);

        config(['instance.organisation_code' => $club->code]);
        // Unauthorized user attempts update
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
                'full_name' => 'Attacked Name',
            ]);

        $response->assertForbidden();
    }

    private function scopeDocument(): string
    {
        $this->assertFileExists($this->docPath);
        return file_get_contents($this->docPath);
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
