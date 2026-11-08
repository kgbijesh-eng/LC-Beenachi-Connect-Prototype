<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\PaymentRecordingService;
use App\Services\Finance\ReceiptGenerationService;
use Illuminate\Support\Facades\Route;
use Tests\Support\FinancialPostgresTestCase;

class Milestone3zPaymentReceiptApiValidationAndClosureTest extends FinancialPostgresTestCase
{

    public function test_3z_validation_document_exists_and_records_closure_boundary(): void
    {
        $path = base_path('docs/milestone_3z_payment_receipt_api_validation_and_closure.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3Z - Payment Receipt API Validation and Closure', $content);
        $this->assertStringContainsString('PAYMENT_RECEIPT_API_VALIDATED_AND_CLOSED', $content);
        $this->assertStringContainsString('Focused 3Y validation passed: 10 passed, 73 assertions.', $content);
        $this->assertStringContainsString('Full backend validation passed: 284 passed, 2235 assertions.', $content);
        $this->assertStringContainsString('No PDF generation', $content);
        $this->assertStringContainsString('No frontend receipt screen', $content);
        $this->assertStringContainsString('No Excel import/export', $content);
    }

    public function test_3y_receipt_api_files_remain_present_for_validation(): void
    {
        $this->assertFileExists(app_path('Http/Controllers/Api/V1/Finance/ReceiptController.php'));
        $this->assertFileExists(app_path('Http/Requests/Finance/GenerateReceiptRequest.php'));
        $this->assertFileExists(app_path('Http/Resources/Finance/ReceiptResource.php'));
        $this->assertFileExists(base_path('docs/milestone_3y_payment_receipt_api_narrow_implementation.md'));
        $this->assertFileExists(base_path('tests/Feature/Milestone3yPaymentReceiptApiNarrowImplementationTest.php'));
    }
    public function test_receipt_json_routes_remain_registered_after_later_pdf_expansion(): void
    {
        $receiptRoutes = collect(Route::getRoutes())
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->filter(fn (string $route): bool => str_contains($route, 'receipt'))
            ->values()
            ->all();

        $this->assertContains(
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
            $receiptRoutes
        );

        $this->assertContains(
            'POST api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
            $receiptRoutes
        );

        $this->assertContains(
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf',
            $receiptRoutes
        );
    }

    public function test_controller_request_and_resource_preserve_3y_boundaries(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Api/V1/Finance/ReceiptController.php'));
        $request = file_get_contents(app_path('Http/Requests/Finance/GenerateReceiptRequest.php'));
        $resource = file_get_contents(app_path('Http/Resources/Finance/ReceiptResource.php'));

        $this->assertStringContainsString('ReceiptGenerationService', $controller);
        $this->assertStringContainsString('->generate(', $controller);
        $this->assertStringContainsString('Payment does not belong to this club member.', $controller);
        $this->assertStringContainsString('Receipt has not been generated for this payment.', $controller);
        $this->assertStringNotContainsString('MemberLedgerEntry::query()->create', $controller);
        $this->assertStringNotContainsString('Payment::query()->create', $controller);

        foreach ([
            "'receipt_id' => ['prohibited']",
            "'receipt_number' => ['prohibited']",
            "'issued_by_user_id' => ['prohibited']",
            "'club_id' => ['prohibited']",
            "'member_id' => ['prohibited']",
            "'payment_id' => ['prohibited']",
            "'amount' => ['prohibited']",
            "'status' => ['prohibited']",
            "'cancelled_at' => ['prohibited']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $request);
        }

        foreach ([
            "'receipt_id'",
            "'receipt_number'",
            "'club_id'",
            "'member_id'",
            "'member_name'",
            "'payment_id'",
            "'amount'",
            "'payment_date'",
            "'payment_method'",
            "'receipt_status'",
            "'generated_at'",
            "'generated_by'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $resource);
        }
    }

    public function test_authorized_receipt_generation_and_view_flow_remains_validated(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        config(['instance.organisation_code' => $club->code]);
        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '925.50',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'upi',
            'reference_number' => 'UPI-3Z-001',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $generateResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt", [
                'notes' => '3Z validation receipt',
            ]);

        $generateResponse->assertCreated()
            ->assertJsonPath('receipt.receipt_number', 'LCB/2026-27/RCP/0001')
            ->assertJsonPath('receipt.member_id', $member->id)
            ->assertJsonPath('receipt.payment_id', $payment->id)
            ->assertJsonPath('receipt.amount', '925.50')
            ->assertJsonPath('receipt.payment_method', 'upi')
            ->assertJsonPath('receipt.receipt_status', 'issued')
            ->assertJsonPath('receipt.generated_by', $user->id);

        $viewResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt");

        $viewResponse->assertOk()
            ->assertJsonPath('receipt.receipt_number', 'LCB/2026-27/RCP/0001');

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, MemberLedgerEntry::query()->count());
        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_get_receipt_does_not_auto_generate_receipt(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        config(['instance.organisation_code' => $club->code]);
        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '450',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt");

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Receipt has not been generated for this payment.',
            ]);

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_normal_member_receipt_visibility_remains_privacy_safe(): void
    {
        $club = $this->createClub();
        $issuer = $this->createUserWithPaymentPermission($club);
        $normalUser = $this->createUserWithClubViewPermission($club);

        $ownMember = $this->createMember($club, 'MEM-3Z-OWN', $normalUser);
        $otherMember = $this->createMember($club, 'MEM-3Z-OTHER');

        $ownPayment = app(PaymentRecordingService::class)->record($issuer, $ownMember, [
            'amount' => '300',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        config(['instance.organisation_code' => $otherClub->code]);
        $otherPayment = app(PaymentRecordingService::class)->record($issuer, $otherMember, [
            'amount' => '400',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        $ownReceipt = app(ReceiptGenerationService::class)->generate($issuer, $ownPayment);
        app(ReceiptGenerationService::class)->generate($issuer, $otherPayment);

        $normalToken = $normalUser->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $ownView = $this
            ->withHeader('Authorization', 'Bearer '.$normalToken)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/payments/{$ownPayment->id}/receipt");

        $ownView->assertOk()
            ->assertJsonPath('receipt.receipt_id', $ownReceipt->id)
            ->assertJsonPath('receipt.member_id', $ownMember->id);

        $otherView = $this
            ->withHeader('Authorization', 'Bearer '.$normalToken)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/payments/{$otherPayment->id}/receipt");

        $otherView->assertForbidden();

        $generateAttempt = $this
            ->withHeader('Authorization', 'Bearer '.$normalToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/payments/{$ownPayment->id}/receipt");

        $generateAttempt->assertForbidden();
    }

    public function test_duplicate_and_cross_club_receipt_requests_remain_fail_closed(): void
    {
        $club = $this->createClub('LCB', 'Lions Club Beenachi');
        $otherClub = $this->createClub('OTHER', 'Other Club');

        $user = $this->createUserWithPaymentPermission($club);
        $otherUser = $this->createUserWithPaymentPermission($otherClub);

        $member = $this->createMember($club, 'MEM-3Z-001');
        $otherMember = $this->createMember($otherClub, 'MEM-OTHER-3Z-001');

        config(['instance.organisation_code' => $club->code]);
        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '700',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'upi',
        ]);

        config(['instance.organisation_code' => $otherClub->code]);
        $otherPayment = app(PaymentRecordingService::class)->record($otherUser, $otherMember, [
            'amount' => '900',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        config(['instance.organisation_code' => $club->code]);
        app(ReceiptGenerationService::class)->generate($user, $payment);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $duplicateResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt");

        $duplicateResponse->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);

        $crossClubResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$otherPayment->id}/receipt");

        $crossClubResponse->assertNotFound()
            ->assertJson([
                'message' => 'Payment does not belong to this club member.',
            ]);

        $this->assertSame(1, Receipt::query()->where('club_id', $club->id)->count());
        $this->assertSame(0, Receipt::query()->where('club_id', $otherClub->id)->count());
    }

    public function test_3z_does_not_add_blocked_later_scope_files(): void
    {
        $blockedPaths = [
            app_path('Services/Finance/ReceiptPdfService.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/ReceiptCorrectionService.php'),
            app_path('Services/Finance/ReceiptReissueService.php'),
            app_path('Services/Finance/SpecialCollectionAllocationService.php'),
            app_path('Services/Finance/FellowshipPaymentService.php'),
            app_path('Services/Reports/FinanceReportService.php'),
            app_path('Services/Imports/ExcelImportService.php'),
            base_path('../app/finance/receipts'),
            base_path('../app/receipts'),
        ];

        foreach ($blockedPaths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    private function createClub(string $code = 'LCB', string $name = 'Lions Club Beenachi'): Club
    {
        $club = Club::query()->create([
            'name' => $name,
            'code' => $code,
            'district' => '318E',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
        config(['instance.organisation_code' => $club->code]);
        return $club;
    }

    private function createMember(Club $club, string $memberCode = 'MEM-3Z-001', ?User $user = null): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => $user?->id,
            'member_code' => $memberCode,
            'full_name' => 'Lion Receipt API Closure Member '.$memberCode,
            'phone' => '9999999999',
            'email' => 'receipt-api-closure-'.uniqid().'@lcbeenachi.test',
            'status' => 'active',
            'joined_at' => '2026-06-01',
            'blood_group' => 'O+',
            'blood_donor_consent' => true,
        ]);
    }

    private function createUserWithPaymentPermission(Club $club): User
    {
        return $this->createUserWithPermissions($club, [
            PaymentRecordingService::PERMISSION_RECORD_PAYMENT,
            'club.view',
        ]);
    }

    private function createUserWithClubViewPermission(Club $club): User
    {
        return $this->createUserWithPermissions($club, [
            'club.view',
        ]);
    }

    private function createUserWithPermissions(Club $club, array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => '3Z Test Role '.uniqid(),
            'slug' => '3z-test-role-'.uniqid(),
            'description' => 'Role for 3Z receipt API validation tests.',
            'is_system' => false,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => $permissionSlug,
                    'group' => str_contains($permissionSlug, 'payment') ? 'finance' : 'club',
                    'description' => 'Created for 3Z receipt API validation tests.',
                ]
            );

            $role->permissions()->attach($permission);
        }

        $user = User::query()->create([
            'name' => '3Z Receipt API User',
            'email' => '3z-receipt-api-'.uniqid().'@lcbeenachi.test',
            'password' => 'password',
        ]);

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }
}