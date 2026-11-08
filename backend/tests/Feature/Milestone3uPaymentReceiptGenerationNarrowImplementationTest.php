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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\Support\FinancialPostgresTestCase;

class Milestone3uPaymentReceiptGenerationNarrowImplementationTest extends FinancialPostgresTestCase
{

    public function test_3u_document_records_narrow_backend_service_boundary(): void
    {
        $path = base_path('docs/milestone_3u_payment_receipt_generation_narrow_implementation.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Payment Receipt Generation Narrow Implementation', $content);
        $this->assertStringContainsString('ReceiptGenerationService', $content);
        $this->assertStringContainsString('Receipt number generation inside the service', $content);
        $this->assertStringContainsString('Duplicate receipt prevention', $content);
        $this->assertStringContainsString('Club-scoped receipt numbering', $content);
        $this->assertStringContainsString('No receipt download route', $content);
        $this->assertStringContainsString('No receipt controller', $content);
        $this->assertStringContainsString('No receipt request class', $content);
        $this->assertStringContainsString('No receipt resource class', $content);
        $this->assertStringContainsString('No PDF generation', $content);
        $this->assertStringContainsString('No frontend receipt screen', $content);
        $this->assertStringContainsString('No WhatsApp receipt sending', $content);
        $this->assertStringContainsString('No Excel receipt import/export', $content);
        $this->assertStringContainsString('PAYMENT_RECEIPT_GENERATION_NARROW_IMPLEMENTATION_ADDED', $content);
    }

    public function test_service_generates_issued_receipt_for_posted_payment_without_extra_ledger_entries(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '750',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'upi',
            'reference_number' => 'UPI-3U-001',
            'notes' => 'Payment before receipt',
        ]);

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, MemberLedgerEntry::query()->count());
        $this->assertSame(0, Receipt::query()->count());

        $receipt = app(ReceiptGenerationService::class)->generate($user, $payment, [
            'notes' => 'Receipt issued in 3U',
        ]);

        $this->assertInstanceOf(Receipt::class, $receipt);
        $this->assertSame($club->id, $receipt->club_id);
        $this->assertSame($payment->id, $receipt->payment_id);
        $this->assertSame('LCB/2026-27/RCP/0001', $receipt->receipt_number);
        $this->assertSame('2026-06-10', $receipt->receipt_date->toDateString());
        $this->assertSame($user->id, $receipt->issued_by_user_id);
        $this->assertSame('issued', $receipt->status);
        $this->assertSame('Receipt issued in 3U', $receipt->notes);

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, MemberLedgerEntry::query()->count());
        $this->assertSame(1, Receipt::query()->count());

        $this->assertDatabaseHas('receipts', [
            'id' => $receipt->id,
            'club_id' => $club->id,
            'payment_id' => $payment->id,
            'receipt_number' => 'LCB/2026-27/RCP/0001',
            'status' => 'issued',
        ]);
    }

    public function test_service_generates_sequential_receipt_numbers_scoped_by_club_and_financial_year(): void
    {
        $club = $this->createClub('LCB', 'Lions Club Beenachi');
        $otherClub = $this->createClub('OTHER', 'Other Club');

        $memberOne = $this->createMember($club, 'MEM-3U-001');
        $memberTwo = $this->createMember($club, 'MEM-3U-002');
        $otherMember = $this->createMember($otherClub, 'MEM-OTHER-001');

        $user = $this->createUserWithPaymentPermission($club);
        $otherUser = $this->createUserWithPaymentPermission($otherClub);

        config(['instance.organisation_code' => $club->code]);
        $paymentOne = app(PaymentRecordingService::class)->record($user, $memberOne, [
            'amount' => '100',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        $paymentTwo = app(PaymentRecordingService::class)->record($user, $memberTwo, [
            'amount' => '200',
            'payment_date' => '2026-06-11',
            'payment_mode' => 'upi',
        ]);

        config(['instance.organisation_code' => $otherClub->code]);
        $otherPayment = app(PaymentRecordingService::class)->record($otherUser, $otherMember, [
            'amount' => '300',
            'payment_date' => '2026-06-12',
            'payment_mode' => 'cash',
        ]);

        config(['instance.organisation_code' => $club->code]);
        $receiptOne = app(ReceiptGenerationService::class)->generate($user, $paymentOne);
        $receiptTwo = app(ReceiptGenerationService::class)->generate($user, $paymentTwo);
        config(['instance.organisation_code' => $otherClub->code]);
        $otherReceipt = app(ReceiptGenerationService::class)->generate($otherUser, $otherPayment);

        $this->assertSame('LCB/2026-27/RCP/0001', $receiptOne->receipt_number);
        $this->assertSame('LCB/2026-27/RCP/0002', $receiptTwo->receipt_number);
        $this->assertSame('OTHER/2026-27/RCP/0001', $otherReceipt->receipt_number);
    }

    public function test_service_rejects_duplicate_receipt_for_same_payment(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '500',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        app(ReceiptGenerationService::class)->generate($user, $payment);

        try {
            app(ReceiptGenerationService::class)->generate($user, $payment);

            $this->fail('The service should reject duplicate official receipts for the same payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_service_rejects_non_posted_payment_statuses(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        foreach (['draft', 'cancelled', 'reversed'] as $status) {
            $payment = Payment::query()->create([
                'club_id' => $club->id,
                'member_id' => $member->id,
                'amount' => '100.00',
                'payment_date' => '2026-06-10',
                'payment_mode' => 'cash',
                'received_by_user_id' => $user->id,
                'ledger_entry_id' => null,
                'status' => $status,
            ]);

            try {
                app(ReceiptGenerationService::class)->generate($user, $payment);

                $this->fail("The service should reject {$status} payments.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment', $exception->errors());
            }
        }

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_service_rejects_user_without_payment_permission(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $authorizedUser = $this->createUserWithPaymentPermission($club);
        $unauthorizedUser = $this->createUserWithClubMembershipButNoPaymentPermission($club);

        $payment = app(PaymentRecordingService::class)->record($authorizedUser, $member, [
            'amount' => '600',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'cash',
        ]);

        try {
            app(ReceiptGenerationService::class)->generate($unauthorizedUser, $payment);

            $this->fail('The service should reject users without receipt generation permission.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('permission', $exception->getMessage());
        }

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_service_rejects_cross_club_receipt_generation(): void
    {
        $authorizedClub = $this->createClub('LCB', 'Lions Club Beenachi');
        $otherClub = $this->createClub('OTHER', 'Other Club');

        $authorizedUser = $this->createUserWithPaymentPermission($authorizedClub);
        $otherUser = $this->createUserWithPaymentPermission($otherClub);
        $otherMember = $this->createMember($otherClub, 'MEM-OTHER-001');

        config(['instance.organisation_code' => $otherClub->code]);
        $otherPayment = app(PaymentRecordingService::class)->record($otherUser, $otherMember, [
            'amount' => '700',
            'payment_date' => '2026-06-10',
            'payment_mode' => 'upi',
        ]);

        try {
            app(ReceiptGenerationService::class)->generate($authorizedUser, $otherPayment);

            $this->fail('The service should reject cross-club receipt generation.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('access', $exception->getMessage());
        }

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_3u_does_not_register_receipt_routes_or_add_blocked_files(): void
    {
        $routes = collect(Route::getRoutes())
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->filter(fn (string $route): bool => str_contains($route, 'receipt') || str_contains($route, 'receipts'))
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf',
            'GET|HEAD api/v1/clubs/{club}/finance/receipts/{receipt}',
            'GET|HEAD api/v1/clubs/{club}/finance/ordinary-income-receipts',
            'GET|HEAD api/v1/clubs/{club}/finance/ordinary-income-receipts/{receipt}',
            'GET|HEAD api/v1/clubs/{club}/finance/ordinary-income-receipts/options',
            'POST api/v1/clubs/{club}/finance/ordinary-income-receipts',
            'POST api/v1/clubs/{club}/finance/ordinary-income-receipts/{receipt}/correction',
            'POST api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
        ], $routes);

        $blockedPaths = [
            app_path('Services/Finance/ReceiptNumberingService.php'),
            app_path('Services/Finance/ReceiptPdfService.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
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

    private function createMember(Club $club, string $memberCode = 'MEM-3U-001'): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => null,
            'member_code' => $memberCode,
            'full_name' => 'Lion Receipt Member',
            'phone' => '9999999999',
            'email' => 'receipt-member-'.uniqid().'@lcbeenachi.test',
            'status' => 'active',
            'joined_at' => '2026-06-01',
            'blood_group' => 'O+',
            'blood_donor_consent' => true,
        ]);
    }

    private function createUserWithPaymentPermission(Club $club): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => PaymentRecordingService::PERMISSION_RECORD_PAYMENT],
            [
                'name' => 'Record Payments',
                'group' => 'finance',
                'description' => 'Allows recording member payments.',
            ]
        );

        $role = Role::query()->create([
            'name' => 'Treasurer',
            'slug' => 'treasurer-'.uniqid(),
            'description' => 'Treasurer role for receipt generation tests.',
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission);

        $user = User::query()->create([
            'name' => 'Receipt Issuer',
            'email' => 'receipt-issuer-'.uniqid().'@lcbeenachi.test',
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

    private function createUserWithClubMembershipButNoPaymentPermission(Club $club): User
    {
        $role = Role::query()->create([
            'name' => 'Receipt Viewer',
            'slug' => 'receipt-viewer-'.uniqid(),
            'description' => 'Role without receipt generation permission.',
            'is_system' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Receipt Viewer User',
            'email' => 'receipt-viewer-'.uniqid().'@lcbeenachi.test',
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
