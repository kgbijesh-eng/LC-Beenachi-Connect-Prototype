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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3adPaymentReceiptPdfNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    public function test_3ad_document_exists_and_records_narrow_pdf_boundary(): void
    {
        $path = base_path('docs/milestone_3ad_payment_receipt_pdf_narrow_implementation.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3AD — Payment Receipt PDF Narrow Implementation', $content);
        $this->assertStringContainsString('backend-only payment receipt PDF download support', $content);
        $this->assertStringContainsString('barryvdh/laravel-dompdf', $content);
        $this->assertStringContainsString('Does not create duplicate receipts', $content);
        $this->assertStringContainsString('Does not mutate ledger entries', $content);
    }

    public function test_3ad_pdf_files_and_package_are_present(): void
    {
        $composer = file_get_contents(base_path('composer.json'));

        $this->assertStringContainsString('barryvdh/laravel-dompdf', $composer);
        $this->assertFileExists(app_path('Http/Controllers/Api/V1/Finance/PaymentReceiptPdfController.php'));
        $this->assertFileExists(app_path('Services/Finance/PaymentReceiptPdfService.php'));
        $this->assertFileExists(resource_path('views/pdf/receipt.blade.php'));
    }

    public function test_3ad_pdf_route_is_registered_as_protected_receipt_route(): void
    {
        $receiptRoutes = collect(Route::getRoutes())
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->filter(fn (string $route): bool => str_contains($route, 'receipt'))
            ->values()
            ->all();

        $this->assertContains(
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf',
            $receiptRoutes
        );
    }

    public function test_authorized_user_can_download_issued_receipt_pdf(): void
    {
        $club = $this->createClub();
        config(['instance.organisation_code' => $club->code]);
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '850.75',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'upi',
            'reference_number' => 'UPI-3AD-001',
        ]);

        app(ReceiptGenerationService::class)->generate($user, $payment);

        $paymentCount = Payment::query()->count();
        $ledgerCount = MemberLedgerEntry::query()->count();
        $receiptCount = Receipt::query()->count();

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->get("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt/pdf");

        $response->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));

        $this->assertSame($paymentCount, Payment::query()->count());
        $this->assertSame($ledgerCount, MemberLedgerEntry::query()->count());
        $this->assertSame($receiptCount, Receipt::query()->count());
    }

    public function test_pdf_download_does_not_auto_generate_missing_receipt(): void
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
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt/pdf");

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Receipt has not been generated for this payment.',
            ]);

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_normal_member_can_download_only_own_issued_receipt_pdf(): void
    {
        $club = $this->createClub();
        config(['instance.organisation_code' => $club->code]);
        $issuer = $this->createUserWithPaymentPermission($club);
        $normalUser = $this->createUserWithClubViewPermission($club);

        $ownMember = $this->createMember($club, 'MEM-3AD-OWN', $normalUser);
        $otherMember = $this->createMember($club, 'MEM-3AD-OTHER');

        $ownPayment = app(PaymentRecordingService::class)->record($issuer, $ownMember, [
            'amount' => '300',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        $otherPayment = app(PaymentRecordingService::class)->record($issuer, $otherMember, [
            'amount' => '400',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        app(ReceiptGenerationService::class)->generate($issuer, $ownPayment);
        app(ReceiptGenerationService::class)->generate($issuer, $otherPayment);

        $normalToken = $normalUser->createToken('test-token')->plainTextToken;

        $ownResponse = $this
            ->withHeader('Authorization', 'Bearer '.$normalToken)
            ->get("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/payments/{$ownPayment->id}/receipt/pdf");

        $ownResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $ownResponse->headers->get('content-type'));

        config(['instance.organisation_code' => $club->code]);
        $otherResponse = $this
            ->withHeader('Authorization', 'Bearer '.$normalToken)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/payments/{$otherPayment->id}/receipt/pdf");

        $otherResponse->assertForbidden();
    }

    public function test_cross_club_or_wrong_member_pdf_request_fails_closed(): void
    {
        $club = $this->createClub('LCB', 'Lions Club Beenachi');
        $otherClub = $this->createClub('OTHER', 'Other Club');

        $user = $this->createUserWithPaymentPermission($club);
        $otherUser = $this->createUserWithPaymentPermission($otherClub);

        $member = $this->createMember($club, 'MEM-3AD-001');
        $otherMember = $this->createMember($otherClub, 'MEM-OTHER-3AD-001');

        $otherPayment = app(PaymentRecordingService::class)->record($otherUser, $otherMember, [
            'amount' => '900',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        app(ReceiptGenerationService::class)->generate($otherUser, $otherPayment);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$otherPayment->id}/receipt/pdf");

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Payment does not belong to this club member.',
            ]);
    }

    public function test_unauthenticated_user_cannot_download_receipt_pdf(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '100',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        app(ReceiptGenerationService::class)->generate($user, $payment);

        config(['instance.organisation_code' => $club->code]);
        $response = $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/{$payment->id}/receipt/pdf");

        $response->assertUnauthorized();
    }

    public function test_3ad_does_not_add_blocked_frontend_storage_sharing_report_or_excel_files(): void
    {
        $blockedPaths = [
            storage_path('app/public/receipts'),
            public_path('receipts'),
            app_path('Services/Reports/FinanceReportService.php'),
            app_path('Services/Imports/ExcelImportService.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/ReceiptCorrectionService.php'),
            app_path('Services/Finance/ReceiptReissueService.php'),
            app_path('Services/Finance/SpecialCollectionAllocationService.php'),
            app_path('Services/Finance/FellowshipPaymentService.php'),
            base_path('../app/finance/receipts'),
            base_path('../app/receipts'),
        ];

        foreach ($blockedPaths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    private function createClub(string $code = 'LCB', string $name = 'Lions Club Beenachi'): Club
    {
        return Club::query()->create([
            'name' => $name,
            'code' => $code,
            'district' => '318E',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
    }

    private function createMember(Club $club, string $memberCode = 'MEM-3AD-001', ?User $user = null): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => $user?->id,
            'member_code' => $memberCode,
            'full_name' => 'Lion Receipt PDF Member '.$memberCode,
            'phone' => '9999999999',
            'email' => 'receipt-pdf-'.uniqid().'@lcbeenachi.test',
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
            'name' => '3AD Test Role '.uniqid(),
            'slug' => '3ad-test-role-'.uniqid(),
            'description' => 'Role for 3AD receipt PDF tests.',
            'is_system' => false,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => $permissionSlug,
                    'group' => str_contains($permissionSlug, 'payment') ? 'finance' : 'club',
                    'description' => 'Created for 3AD receipt PDF tests.',
                ]
            );

            $role->permissions()->attach($permission);
        }

        $user = User::query()->create([
            'name' => '3AD Receipt PDF User',
            'email' => '3ad-receipt-pdf-'.uniqid().'@lcbeenachi.test',
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
