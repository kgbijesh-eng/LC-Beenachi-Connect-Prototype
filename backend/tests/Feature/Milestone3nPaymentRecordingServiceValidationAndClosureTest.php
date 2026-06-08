<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\PaymentRecordingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Milestone3nPaymentRecordingServiceValidationAndClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_document_exists_and_records_closure_boundary(): void
    {
        $path = base_path('docs/milestone_3n_payment_recording_service_validation_and_closure.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Payment Recording Service Validation and Closure', $content);
        $this->assertStringContainsString('docs/tests-only', $content);
        $this->assertStringContainsString('PaymentRecordingService', $content);
        $this->assertStringContainsString('payments.ledger_entry_id', $content);
        $this->assertStringContainsString('Milestone 3M is validated', $content);
        $this->assertStringContainsString('No service implementation change', $content);
        $this->assertStringContainsString('No route', $content);
        $this->assertStringContainsString('No controller', $content);
        $this->assertStringContainsString('No receipt generation', $content);
        $this->assertStringContainsString('No Excel import', $content);
    }

    public function test_3m_service_file_exists_and_contains_required_transaction_and_linkage_rules(): void
    {
        $path = app_path('Services/Finance/PaymentRecordingService.php');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('class PaymentRecordingService', $content);
        $this->assertStringContainsString('PERMISSION_RECORD_PAYMENT', $content);
        $this->assertStringContainsString('payments.record', $content);
        $this->assertStringContainsString('DB::transaction', $content);
        $this->assertStringContainsString('MemberLedgerEntry::ENTRY_TYPE_CREDIT', $content);
        $this->assertStringContainsString('MemberLedgerEntry::SOURCE_PAYMENT', $content);
        $this->assertStringContainsString("'ledger_entry_id' => \$ledgerEntry->id", $content);
        $this->assertStringContainsString('Rule::in(Payment::MODES)', $content);
    }

    public function test_service_still_records_posted_payment_and_posted_ledger_credit_with_linkage(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $receivedByUser = $this->createUserWithPaymentRecordingPermission($club);

        $payment = app(PaymentRecordingService::class)->record($receivedByUser, $member, [
            'amount' => '750.50',
            'payment_date' => '2026-06-08',
            'payment_mode' => 'bank_transfer',
            'reference_number' => 'BANK-3N-001',
            'notes' => '3N validation payment',
        ]);

        $this->assertSame($club->id, $payment->club_id);
        $this->assertSame($member->id, $payment->member_id);
        $this->assertSame('750.50', $payment->amount);
        $this->assertSame('posted', $payment->status);
        $this->assertSame($receivedByUser->id, $payment->received_by_user_id);
        $this->assertNotNull($payment->ledger_entry_id);

        $ledgerEntry = $payment->ledgerEntry;

        $this->assertInstanceOf(MemberLedgerEntry::class, $ledgerEntry);
        $this->assertSame($payment->ledger_entry_id, $ledgerEntry->id);
        $this->assertSame($club->id, $ledgerEntry->club_id);
        $this->assertSame($member->id, $ledgerEntry->member_id);
        $this->assertSame(MemberLedgerEntry::ENTRY_TYPE_CREDIT, $ledgerEntry->entry_type);
        $this->assertSame(MemberLedgerEntry::SOURCE_PAYMENT, $ledgerEntry->source_type);
        $this->assertSame('750.50', $ledgerEntry->amount);
        $this->assertSame(MemberLedgerEntry::STATUS_POSTED, $ledgerEntry->status);
        $this->assertSame($payment->id, $ledgerEntry->metadata['payment_id']);
        $this->assertSame('bank_transfer', $ledgerEntry->metadata['payment_mode']);
    }

    public function test_service_permission_cross_club_and_validation_guards_remain_enforced(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $userWithoutPermission = $this->createUserWithClubMembershipButNoPaymentPermission($club);

        try {
            app(PaymentRecordingService::class)->record($userWithoutPermission, $member, [
                'amount' => '100',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'cash',
            ]);

            $this->fail('User without payments.record permission should not record payments.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('permission', $exception->getMessage());
        }

        $authorizedClub = $this->createClub('LCB-3N-AUTH', 'Authorized Club');
        $otherClub = $this->createClub('LCB-3N-OTHER', 'Other Club');
        $authorizedUser = $this->createUserWithPaymentRecordingPermission($authorizedClub);
        $otherMember = $this->createMember($otherClub, 'MEM-3N-OTHER');

        try {
            app(PaymentRecordingService::class)->record($authorizedUser, $otherMember, [
                'amount' => '100',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'cash',
            ]);

            $this->fail('Cross-club payment recording should fail closed.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('access', $exception->getMessage());
        }

        $validUser = $this->createUserWithPaymentRecordingPermission($club);

        try {
            app(PaymentRecordingService::class)->record($validUser, $member, [
                'amount' => '0',
                'payment_date' => 'invalid-date',
                'payment_mode' => 'invalid-mode',
            ]);

            $this->fail('Invalid payment attributes should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
            $this->assertArrayHasKey('payment_date', $exception->errors());
            $this->assertArrayHasKey('payment_mode', $exception->errors());
        }

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, MemberLedgerEntry::query()->count());
    }

    public function test_3n_does_not_add_public_payment_receipt_or_finance_mutation_routes(): void
    {
        $forbiddenMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        $forbiddenRoutes = collect(Route::getRoutes())
            ->filter(function ($route) use ($forbiddenMethods): bool {
                $uri = $route->uri();
                $methods = $route->methods();

                $isFinanceMutationUri = str_contains($uri, 'payment')
                    || str_contains($uri, 'receipt')
                    || str_contains($uri, 'finance');

                return $isFinanceMutationUri && ! empty(array_intersect($forbiddenMethods, $methods));
            })
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->reject(fn (string $route): bool => $route === 'POST api/v1/clubs/{club}/members/{member}/payments') // authorized 3Q payment route
            ->values()
            ->all();

        $this->assertSame([], $forbiddenRoutes);
    }

    public function test_3n_does_not_add_blocked_api_frontend_receipt_report_or_excel_files(): void
    {
        $blockedPaths = [
            app_path('Http/Controllers/PaymentController.php'),
            app_path('Http/Controllers/ReceiptController.php'),
            app_path('Http/Requests/RecordPaymentRequest.php'),
            app_path('Http/Resources/PaymentResource.php'),
            app_path('Services/Finance/ReceiptGenerationService.php'),
            app_path('Services/Finance/ReceiptNumberingService.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/SpecialCollectionAllocationService.php'),
            app_path('Services/Finance/FellowshipPaymentService.php'),
            app_path('Services/Finance/PaymentReminderService.php'),
            app_path('Services/Reports/FinanceReportService.php'),
            app_path('Services/Imports/ExcelImportService.php'),
            base_path('../app/payments'),
            base_path('../app/finance/payments'),
        ];

        foreach ($blockedPaths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    private function createClub(string $code = 'LCB-3N', string $name = 'Lions Club Beenachi'): Club
    {
        return Club::query()->create([
            'name' => $name,
            'code' => $code,
            'district' => '318E',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
    }

    private function createMember(Club $club, string $memberCode = 'MEM-3N-001'): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => null,
            'member_code' => $memberCode,
            'full_name' => 'Lion 3N Test Member',
            'phone' => '9999999999',
            'email' => 'member-'.uniqid().'@lcbeenachi.test',
            'status' => 'active',
            'joined_at' => '2026-06-01',
            'blood_group' => 'O+',
            'blood_donor_consent' => true,
        ]);
    }

    private function createUserWithPaymentRecordingPermission(Club $club): User
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
            'name' => 'Treasurer 3N',
            'slug' => 'treasurer-3n-'.uniqid(),
            'description' => 'Treasurer role for 3N validation.',
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission);

        $user = User::query()->create([
            'name' => 'Treasurer 3N User',
            'email' => 'treasurer-3n-'.uniqid().'@lcbeenachi.test',
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
            'name' => 'Viewer 3N',
            'slug' => 'viewer-3n-'.uniqid(),
            'description' => 'Role without payment recording permission.',
            'is_system' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Viewer 3N User',
            'email' => 'viewer-3n-'.uniqid().'@lcbeenachi.test',
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