<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use Tests\Support\FinancialPostgresTestCase;

class Milestone3dqLogout500DefectFixImplementationTest extends FinancialPostgresTestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dq_logout_500_defect_fix_implementation.md';

    public function test_logged_out_token_follow_up_auth_me_returns_401_not_500(): void
    {
        $user = User::factory()->create([
            'name' => '3DQ Auth Test User',
            'email' => '3dq.auth@example.test',
            'password' => 'correct-password',
        ]);
        $club = Club::create(['name' => 'Synthetic logout instance', 'code' => 'SYN-3DQ', 'is_active' => true]);
        $role = Role::create(['name' => 'Member', 'slug' => 'member', 'is_system' => true]);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active']);
        config(['instance.organisation_code' => $club->code]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => '3dq.auth@example.test',
            'password' => 'correct-password',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('user.email', '3dq.auth@example.test');

        $token = $loginResponse->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', '3dq.auth@example.test');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully.',
            ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_3dq_document_records_implementation_scope_and_evidence(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3DQ - Logout 500 Defect Fix Implementation',
            'DN-010 - Logout/token follow-up /auth/me returns HTTP 500 after logout.',
            'Actual route path tested: /api/v1/auth/me.',
            'Acceptance shorthand: /auth/me.',
            'Root cause found through inspection:',
            'AuthController::me assumed an authenticated user object and immediately called load() on $request->user().',
            'Implementation summary:',
            'AuthController::me now checks for a missing authenticated user and returns HTTP 401 with "Unauthenticated." before loading relationships.',
            'backend/app/Http/Controllers/Api/V1/Auth/AuthController.php',
            'backend/tests/Feature/Milestone3dqLogout500DefectFixImplementationTest.php',
            'backend/docs/milestone_3dq_logout_500_defect_fix_implementation.md',
            'app/page.tsx was not edited.',
            'Frontend runtime was not edited.',
            'Payment, member edit, import, profile document, deployment, and SaaS behavior were not changed.',
            'Corrected 3DN result remains NO-GO PENDING DEFECT FIXES.',
            'DN-010 is fixed by 3DQ if validation passes.',
            'DN-020, DN-031, and DN-047 remain unresolved.',
            'Expected next milestone:',
            'Milestone 3DR - Seeded Payment Permission Defect Scope Gate.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dq_document_records_validation_governance_and_risks(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'A valid logged-in token can call /api/v1/auth/me successfully.',
            'Logout using that token succeeds according to the existing logout contract.',
            'A follow-up /api/v1/auth/me request using the same logged-out token returns HTTP 401 unauthenticated, not HTTP 500.',
            'Existing authenticated /auth/me behavior remains unchanged before logout.',
            'The fix does not weaken authentication.',
            'The fix does not mask authorization failures as success.',
            '3DQ does not use production data.',
            '3DQ does not use real club financial records.',
            '3DQ does not perform real payment collection.',
            '3DQ is not deployment readiness.',
            '3DQ is not SaaS readiness.',
            'Profile document upload/download/PDF/OCR remains out of scope.',
            'Receipt PDF remains allowed only because it belongs to the approved finance/receipt lane.',
            '3DQ must not start 3DR.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
