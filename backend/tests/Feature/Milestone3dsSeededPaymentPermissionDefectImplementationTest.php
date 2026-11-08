<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\PaymentRecordingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Support\FinancialPostgresTestCase;

class Milestone3dsSeededPaymentPermissionDefectImplementationTest extends FinancialPostgresTestCase
{

    private string $docPath = __DIR__.'/../../docs/milestone_3ds_seeded_payment_permission_defect_implementation.md';

    public function test_3ds_document_records_seeded_payment_permission_implementation_scope(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3DS - Seeded Payment Permission Defect Implementation',
            '3DS fixes DN-031 only.',
            '3DS does not fix DN-020.',
            '3DS does not directly fix DN-047.',
            '3DS does not edit frontend runtime code.',
            '3DS does not edit app/page.tsx.',
            '3DS does not edit payment controller, payment service, payment middleware, routes, requests, resources, models, migrations, package files, or lock files.',
            'No runtime authorization checks are weakened.',
            '## Narrow test-maintenance exception',
            '3DS required narrow compatibility updates to existing backend feature tests because the seeded President/Admin permission list intentionally gained one new permission: `payments.record`.',
            '`backend/tests/Feature/AuthMeRoleAwareResponseTest.php`',
            '`backend/tests/Feature/ClubPermissionServiceTest.php`',
            '`backend/tests/Feature/ProtectedClubFoundationRoutesTest.php`',
            'These changes do not alter runtime behavior. They only update expected permission fragments/counts from the previous President/Admin seeded permission set to the corrected set that now includes `payments.record`.',
            'Normal Member payment authorization remains denied with HTTP 403.',
            '3DR lifecycle guard exception:',
            'Not used.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_local_seeded_president_admin_has_payment_record_permission_and_member_does_not(): void
    {
        $this->seed(DatabaseSeeder::class);

        $paymentPermission = Permission::where('slug', PaymentRecordingService::PERMISSION_RECORD_PAYMENT)->first();
        $presidentRole = Role::where('slug', 'president-admin')->firstOrFail();
        $memberRole = Role::where('slug', 'member')->firstOrFail();

        $this->assertNotNull($paymentPermission);
        $this->assertTrue($presidentRole->permissions()->where('slug', PaymentRecordingService::PERMISSION_RECORD_PAYMENT)->exists());
        $this->assertFalse($memberRole->permissions()->where('slug', PaymentRecordingService::PERMISSION_RECORD_PAYMENT)->exists());

        $president = User::where('email', 'president@lcbeenachi.test')->firstOrFail();
        $normalMember = User::where('email', 'local.member@lcbeenachi.test')->firstOrFail();

        $this->assertTrue(
            $president->clubMemberships()
                ->whereHas('role.permissions', fn ($query) => $query->where('slug', PaymentRecordingService::PERMISSION_RECORD_PAYMENT))
                ->exists()
        );

        $this->assertFalse(
            $normalMember->clubMemberships()
                ->whereHas('role.permissions', fn ($query) => $query->where('slug', PaymentRecordingService::PERMISSION_RECORD_PAYMENT))
                ->exists()
        );
    }

    public function test_seeded_president_admin_can_record_synthetic_member_payment_and_normal_member_still_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'LCB-3DS-PAY-001',
            'full_name' => '3DS Synthetic Payment Member',
            'status' => 'active',
        ]);

        config(['instance.organisation_code' => 'LCB']);
        $presidentLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'president@lcbeenachi.test',
            'password' => 'password',
        ]);

        $presidentLogin->assertOk();

        config(['instance.organisation_code' => $club->code]);
        $presidentPayment = $this
            ->withHeader('Authorization', 'Bearer '.$presidentLogin->json('token'))
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '101.00',
                'payment_date' => '2026-06-14',
                'payment_mode' => 'cash',
                'reference_number' => '3DS-DN-031',
                'notes' => '3DS DN-031 synthetic local permission test',
            ]);

        $presidentPayment->assertCreated()
            ->assertJsonPath('payment.member_id', $member->id)
            ->assertJsonPath('payment.amount', '101.00')
            ->assertJsonPath('payment.payment_mode', 'cash')
            ->assertJsonPath('payment.reference_number', '3DS-DN-031')
            ->assertJsonPath('payment.status', 'posted');

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, MemberLedgerEntry::query()->count());

        Auth::guard('sanctum')->forgetUser();

        config(['instance.organisation_code' => 'LCB']);
        $normalMemberLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'local.member@lcbeenachi.test',
            'password' => 'password',
        ]);

        $normalMemberLogin->assertOk();

        config(['instance.organisation_code' => $club->code]);
        $blockedPayment = $this
            ->withHeader('Authorization', 'Bearer '.$normalMemberLogin->json('token'))
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '99.00',
                'payment_date' => '2026-06-14',
                'payment_mode' => 'cash',
            ]);

        $blockedPayment->assertForbidden();

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, MemberLedgerEntry::query()->count());
    }

    public function test_dn_010_logout_follow_up_auth_me_remains_unauthorized_after_logout(): void
    {
        $this->seed(DatabaseSeeder::class);

        config(['instance.organisation_code' => 'LCB']);
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'president@lcbeenachi.test',
            'password' => 'password',
        ]);

        $login->assertOk();

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
