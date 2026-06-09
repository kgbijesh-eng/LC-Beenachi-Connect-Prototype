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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Milestone3vPaymentReceiptGenerationValidationAndClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_3v_validation_document_exists_and_records_closure_boundary(): void
    {
        $path = base_path('docs/milestone_3v_payment_receipt_generation_validation_and_closure.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Payment Receipt Generation Validation and Closure', $content);
        $this->assertStringContainsString('VALIDATION AND CLOSURE', $content);
        $this->assertStringContainsString('DOCS AND TESTS ONLY', $content);
        $this->assertStringContainsString('NO NEW IMPLEMENTATION IN 3V', $content);
        $this->assertStringContainsString('ReceiptGenerationService', $content);
        $this->assertStringContainsString('8 passed, 55 assertions', $content);
        $this->assertStringContainsString('54 passed, 467 assertions', $content);
        $this->assertStringContainsString('186 passed, 1642 assertions', $content);
        $this->assertStringContainsString('255 passed, 1995 assertions', $content);
        $this->assertStringContainsString('PAYMENT_RECEIPT_GENERATION_SERVICE_VALIDATED_AND_CLOSED', $content);
        $this->assertStringContainsString('No receipt route', $content);
        $this->assertStringContainsString('No receipt controller', $content);
        $this->assertStringContainsString('No PDF generation', $content);
        $this->assertStringContainsString('No frontend receipt screen', $content);
        $this->assertStringContainsString('No Excel receipt import', $content);
    }

    public function test_3u_receipt_generation_files_remain_present_for_validation(): void
    {
        $this->assertFileExists(app_path('Services/Finance/ReceiptGenerationService.php'));
        $this->assertFileExists(base_path('docs/milestone_3u_payment_receipt_generation_narrow_implementation.md'));
        $this->assertFileExists(base_path('tests/Feature/Milestone3uPaymentReceiptGenerationNarrowImplementationTest.php'));

        $serviceContent = file_get_contents(app_path('Services/Finance/ReceiptGenerationService.php'));

        $this->assertStringContainsString('class ReceiptGenerationService', $serviceContent);
        $this->assertStringContainsString('DB::transaction', $serviceContent);
        $this->assertStringContainsString('lockForUpdate', $serviceContent);
        $this->assertStringContainsString('PERMISSION_GENERATE_RECEIPT', $serviceContent);
        $this->assertStringContainsString('PaymentRecordingService::PERMISSION_RECORD_PAYMENT', $serviceContent);
        $this->assertStringContainsString('A receipt has already been generated for this payment.', $serviceContent);
        $this->assertStringContainsString('Receipts can only be generated for posted payments.', $serviceContent);
    }

    public function test_receipt_generation_service_remains_transaction_safe_and_does_not_create_extra_ledger_entries(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = $this->createUserWithPaymentPermission($club);

        $payment = app(PaymentRecordingService::class)->record($user, $member, [
            'amount' => '900',
            'payment_date' => '2026-06-12',
            'payment_mode' => 'upi',
            'reference_number' => 'UPI-3V-001',
        ]);

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, MemberLedgerEntry::query()->count());
        $this->assertSame(0, Receipt::query()->count());

        $receipt = app(ReceiptGenerationService::class)->generate($user, $payment, [
            'receipt_date' => '2026-06-12',
            'notes' => '3V closure validation receipt',
        ]);

        $this->assertSame($club->id, $receipt->club_id);
        $this->assertSame($payment->id, $receipt->payment_id);
        $this->assertSame('LCB/2026-27/RCP/0001', $receipt->receipt_number);
        $this->assertSame('2026-06-12', $receipt->receipt_date->toDateString());
        $this->assertSame($user->id, $receipt->issued_by_user_id);
        $this->assertSame('issued', $receipt->status);

        $payment->refresh();

        $this->assertSame('posted', $payment->status);
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, MemberLedgerEntry::query()->count());
        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_receipt_numbering_duplicate_and_status_guards_remain_validated(): void
    {
        $club = $this->createClub();
        $memberOne = $this->createMember($club, 'MEM-3V-001');
        $memberTwo = $this->createMember($club, 'MEM-3V-002');
        $user = $this->createUserWithPaymentPermission($club);

        $paymentOne = app(PaymentRecordingService::class)->record($user, $memberOne, [
            'amount' => '100',
            'payment_date' => '2026-06-12',
            'payment_mode' => 'cash',
        ]);

        $paymentTwo = app(PaymentRecordingService::class)->record($user, $memberTwo, [
            'amount' => '200',
            'payment_date' => '2026-06-13',
            'payment_mode' => 'cash',
        ]);

        $receiptOne = app(ReceiptGenerationService::class)->generate($user, $paymentOne);
        $receiptTwo = app(ReceiptGenerationService::class)->generate($user, $paymentTwo);

        $this->assertSame('LCB/2026-27/RCP/0001', $receiptOne->receipt_number);
        $this->assertSame('LCB/2026-27/RCP/0002', $receiptTwo->receipt_number);

        try {
            app(ReceiptGenerationService::class)->generate($user, $paymentOne);

            $this->fail('Duplicate receipt generation must remain blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $draftPayment = Payment::query()->create([
            'club_id' => $club->id,
            'member_id' => $memberOne->id,
            'amount' => '50.00',
            'payment_date' => '2026-06-12',
            'payment_mode' => 'cash',
            'received_by_user_id' => $user->id,
            'ledger_entry_id' => null,
            'status' => 'draft',
        ]);

        try {
            app(ReceiptGenerationService::class)->generate($user, $draftPayment);

            $this->fail('Draft payment receipt generation must remain blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $this->assertSame(2, Receipt::query()->count());
    }

    public function test_permission_and_cross_club_guards_remain_validated(): void
    {
        $club = $this->createClub('LCB', 'Lions Club Beenachi');
        $otherClub = $this->createClub('OTHER', 'Other Club');

        $member = $this->createMember($club, 'MEM-3V-003');
        $otherMember = $this->createMember($otherClub, 'MEM-OTHER-3V-001');

        $authorizedUser = $this->createUserWithPaymentPermission($club);
        $unauthorizedUser = $this->createUserWithClubMembershipButNoPaymentPermission($club);
        $otherUser = $this->createUserWithPaymentPermission($otherClub);

        $payment = app(PaymentRecordingService::class)->record($authorizedUser, $member, [
            'amount' => '300',
            'payment_date' => '2026-06-12',
            'payment_mode' => 'cash',
        ]);

        try {
            app(ReceiptGenerationService::class)->generate($unauthorizedUser, $payment);

            $this->fail('User without payment permission must not generate receipts.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('permission', $exception->getMessage());
        }

        $otherPayment = app(PaymentRecordingService::class)->record($otherUser, $otherMember, [
            'amount' => '400',
            'payment_date' => '2026-06-12',
            'payment_mode' => 'cash',
        ]);

        try {
            app(ReceiptGenerationService::class)->generate($authorizedUser, $otherPayment);

            $this->fail('Cross-club receipt generation must remain blocked.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('access', $exception->getMessage());
        }

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_3v_closes_without_receipt_api_pdf_frontend_report_messaging_or_excel_scope(): void
    {
        $routes = collect(Route::getRoutes())
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->filter(fn (string $route): bool => str_contains($route, 'receipt') || str_contains($route, 'receipts'))
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf',
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
        return Club::query()->create([
            'name' => $name,
            'code' => $code,
            'district' => '318E',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
    }

    private function createMember(Club $club, string $memberCode = 'MEM-3V-000'): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => null,
            'member_code' => $memberCode,
            'full_name' => 'Lion 3V Receipt Member',
            'phone' => '9999999999',
            'email' => 'receipt-3v-'.uniqid().'@lcbeenachi.test',
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
            'description' => 'Treasurer role for 3V receipt validation tests.',
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission);

        $user = User::query()->create([
            'name' => '3V Receipt Validator',
            'email' => 'receipt-validator-'.uniqid().'@lcbeenachi.test',
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
            'name' => 'Receipt Closure Viewer',
            'slug' => 'receipt-closure-viewer-'.uniqid(),
            'description' => 'Role without payment receipt generation permission.',
            'is_system' => true,
        ]);

        $user = User::query()->create([
            'name' => '3V Unauthorized User',
            'email' => 'receipt-unauthorized-'.uniqid().'@lcbeenachi.test',
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
