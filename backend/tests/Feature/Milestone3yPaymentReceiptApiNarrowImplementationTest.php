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

class Milestone3yPaymentReceiptApiNarrowImplementationTest extends FinancialPostgresTestCase
{

    public function test_3y_document_records_narrow_api_implementation_boundary(): void
    {
        $path = base_path('docs/milestone_3y_payment_receipt_api_narrow_implementation.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3Y - Payment Receipt API Narrow Implementation', $content);
        $this->assertStringContainsString('PAYMENT_RECEIPT_API_NARROW_IMPLEMENTATION_ADDED', $content);
        $this->assertStringContainsString('ReceiptGenerationService', $content);
        $this->assertStringContainsString('POST /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt', $content);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt', $content);
        $this->assertStringContainsString('No PDF generation', $content);
        $this->assertStringContainsString('No frontend receipt screen', $content);
        $this->assertStringContainsString('No WhatsApp/SMS/email receipt sending', $content);
        $this->assertStringContainsString('No Excel import/export', $content);
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

    public function test_authorized_user_can_generate_receipt_through_api(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '850.75',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'upi',
            'reference_number' => 'UPI-3Y-001',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt", [
                'notes' => 'Receipt generated through 3Y API',
            ]);

        $response->assertCreated()
            ->assertJsonPath('receipt.receipt_number', 'LCB/2026-27/RCP/0001')
            ->assertJsonPath('receipt.club_id', $club->id)
            ->assertJsonPath('receipt.member_id', $member->id)
            ->assertJsonPath('receipt.member_name', $member->full_name)
            ->assertJsonPath('receipt.payment_id', $payment->id)
            ->assertJsonPath('receipt.amount', '850.75')
            ->assertJsonPath('receipt.payment_date', '2026-06-10')
            ->assertJsonPath('receipt.payment_method', 'upi')
            ->assertJsonPath('receipt.receipt_status', 'issued')
            ->assertJsonPath('receipt.generated_by', $user->id);

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, MemberLedgerEntry::query()->count());
        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_get_receipt_returns_existing_receipt_without_auto_generation(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '500',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $missingResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt");

        $missingResponse->assertNotFound()
            ->assertJson([
                'message' => 'Receipt has not been generated for this payment.',
            ]);

        $this->assertSame(0, Receipt::query()->count());

        $receipt = app(ReceiptGenerationService::class)->generate($user, $payment);

        $viewResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt");

        $viewResponse->assertOk()
            ->assertJsonPath('receipt.receipt_id', $receipt->id)
            ->assertJsonPath('receipt.receipt_number', 'LCB/2026-27/RCP/0001');

        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_normal_member_cannot_generate_receipt_but_can_view_own_generated_receipt(): void
    {
        $club = $this->createClub();
        $issuer = $this->createUserWithPaymentPermission($club);
        $normalUser = $this->createUserWithClubViewPermission($club);

        $member = $this->createMember($club, 'MEM-3Y-OWN', $normalUser);

        $payment = app(PaymentRecordingService::class)->record($issuer, $member, [
            'amount' => '300',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        $normalToken = $normalUser->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $generateResponse = $this
            ->withHeader('Authorization', 'Bearer '.$normalToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt");

        $generateResponse->assertForbidden();

        $receipt = app(ReceiptGenerationService::class)->generate($issuer, $payment);

        $viewResponse = $this
            ->withHeader('Authorization', 'Bearer '.$normalToken)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt");

        $viewResponse->assertOk()
            ->assertJsonPath('receipt.receipt_id', $receipt->id)
            ->assertJsonPath('receipt.member_id', $member->id);
    }

    public function test_normal_member_cannot_view_other_members_receipt(): void
    {
        $club = $this->createClub();
        $issuer = $this->createUserWithPaymentPermission($club);
        $normalUser = $this->createUserWithClubViewPermission($club);

        $ownMember = $this->createMember($club, 'MEM-3Y-OWN', $normalUser);
        $otherMember = $this->createMember($club, 'MEM-3Y-OTHER');

        $this->assertNotSame($ownMember->id, $otherMember->id);

        $payment = app(PaymentRecordingService::class)->record($issuer, $otherMember, [
            'amount' => '300',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        app(ReceiptGenerationService::class)->generate($issuer, $payment);

        $normalToken = $normalUser->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$normalToken)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/payments/{$payment->id}/receipt");

        $response->assertForbidden();
    }

    public function test_duplicate_receipt_generation_through_api_fails_closed(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '700',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'upi',
        ]);

        app(ReceiptGenerationService::class)->generate($user, $payment);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);

        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_cross_club_or_wrong_member_payment_receipt_request_fails_closed(): void
    {
        $club = $this->createClub('LCB', 'Lions Club Beenachi');
        $otherClub = $this->createClub('OTHER', 'Other Club');

        $user = $this->createUserWithPaymentPermission($club);
        $otherUser = $this->createUserWithPaymentPermission($otherClub);

        $member = $this->createMember($club, 'MEM-3Y-001');
        $otherMember = $this->createMember($otherClub, 'MEM-OTHER-001');

        $otherPayment = app(PaymentRecordingService::class)->record($otherUser, $otherMember, [
            'amount' => '900',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$otherPayment->id}/receipt");

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Payment does not belong to this club member.',
            ]);

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_generate_receipt_request_rejects_backend_controlled_fields(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '100',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt", [
                'receipt_id' => 1,
                'receipt_number' => 'MANUAL-001',
                'receipt_status' => 'issued',
                'generated_by' => $user->id,
                'generated_at' => now()->toISOString(),
                'issued_by_user_id' => $user->id,
                'club_id' => $club->id,
                'member_id' => $member->id,
                'payment_id' => $payment->id,
                'amount' => '1',
                'payment_date' => '2026-06-10',
                'payment_method' => 'cash',
                'payment_mode' => 'cash',
                'status' => 'cancelled',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'receipt_id',
                'receipt_number',
                'receipt_status',
                'generated_by',
                'generated_at',
                'issued_by_user_id',
                'club_id',
                'member_id',
                'payment_id',
                'amount',
                'payment_date',
                'payment_method',
                'payment_mode',
                'status',
            ]);

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_3y_does_not_add_blocked_receipt_pdf_frontend_report_excel_or_workflow_files(): void
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
        config(['instance.organisation_code' => $club->code]);
        return $club;
    }

    private function createMember(Club $club, string $memberCode = 'MEM-3Y-001', ?User $user = null): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => $user?->id,
            'member_code' => $memberCode,
            'full_name' => 'Lion Receipt API Member '.$memberCode,
            'phone' => '9999999999',
            'email' => 'receipt-api-'.uniqid().'@lcbeenachi.test',
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
            'name' => '3Y Test Role '.uniqid(),
            'slug' => '3y-test-role-'.uniqid(),
            'description' => 'Role for 3Y receipt API tests.',
            'is_system' => false,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => $permissionSlug,
                    'group' => str_contains($permissionSlug, 'payment') ? 'finance' : 'club',
                    'description' => 'Created for 3Y receipt API tests.',
                ]
            );

            $role->permissions()->attach($permission);
        }

        $user = User::query()->create([
            'name' => '3Y Receipt API User',
            'email' => '3y-receipt-api-'.uniqid().'@lcbeenachi.test',
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
