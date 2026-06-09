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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3qPaymentRecordingApiNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    public function test_3q_document_records_narrow_api_implementation_boundary(): void
    {
        $path = base_path('docs/milestone_3q_payment_recording_api_narrow_implementation.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Payment Recording API Narrow Implementation', $content);
        $this->assertStringContainsString('PaymentRecordingService', $content);
        $this->assertStringContainsString('POST /api/v1/clubs/{club}/members/{member}/payments', $content);
        $this->assertStringContainsString('No receipt generation', $content);
        $this->assertStringContainsString('No receipt numbering', $content);
        $this->assertStringContainsString('No cancel route', $content);
        $this->assertStringContainsString('No reverse route', $content);
        $this->assertStringContainsString('No Excel import', $content);
        $this->assertStringContainsString('No frontend', $content);
    }

    public function test_payment_recording_route_is_registered_as_single_post_route(): void
    {
        $paymentRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'payments'))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf',
            'POST api/v1/clubs/{club}/members/{member}/payments',
            'POST api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
        ], $paymentRoutes);
    }

    public function test_president_admin_can_record_member_payment_through_api(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();
        $this->grantPaymentRecordPermissionToRole('president-admin');

        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'LCB-PAY-001',
            'full_name' => 'Payment API Member',
            'status' => 'active',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '850.75',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'upi',
                'reference_number' => 'UPI-3Q-001',
                'notes' => '3Q API payment',
            ]);

        $response->assertCreated()
            ->assertJsonPath('payment.club_id', $club->id)
            ->assertJsonPath('payment.member_id', $member->id)
            ->assertJsonPath('payment.amount', '850.75')
            ->assertJsonPath('payment.payment_date', '2026-06-08')
            ->assertJsonPath('payment.payment_mode', 'upi')
            ->assertJsonPath('payment.reference_number', 'UPI-3Q-001')
            ->assertJsonPath('payment.notes', '3Q API payment')
            ->assertJsonPath('payment.status', 'posted')
            ->assertJsonPath('payment.received_by_user_id', $user->id);

        $paymentId = $response->json('payment.id');
        $ledgerEntryId = $response->json('payment.ledger_entry_id');

        $this->assertNotNull($paymentId);
        $this->assertNotNull($ledgerEntryId);

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'club_id' => $club->id,
            'member_id' => $member->id,
            'payment_mode' => 'upi',
            'reference_number' => 'UPI-3Q-001',
            'ledger_entry_id' => $ledgerEntryId,
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('member_ledger_entries', [
            'id' => $ledgerEntryId,
            'club_id' => $club->id,
            'member_id' => $member->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_CREDIT,
            'source_type' => MemberLedgerEntry::SOURCE_PAYMENT,
            'reference_number' => 'UPI-3Q-001',
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);
    }

    public function test_treasurer_can_record_member_payment_when_granted_payment_record_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $treasurerRole = Role::where('slug', 'treasurer')->firstOrFail();
        $this->grantPaymentRecordPermissionToRole('treasurer');

        $user = User::factory()->create([
            'email' => 'treasurer-payment-api@lcbeenachi.test',
        ]);

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $treasurerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'LCB-PAY-002',
            'full_name' => 'Treasurer Payment API Member',
            'status' => 'active',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '500',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'cash',
            ]);

        $response->assertCreated()
            ->assertJsonPath('payment.member_id', $member->id)
            ->assertJsonPath('payment.amount', '500.00')
            ->assertJsonPath('payment.payment_mode', 'cash')
            ->assertJsonPath('payment.status', 'posted');
    }

    public function test_normal_member_cannot_record_payment_through_api(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $memberRole = Role::where('slug', 'member')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'normal-payment-api@lcbeenachi.test',
        ]);

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $memberRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'LCB-PAY-003',
            'full_name' => 'Blocked Normal Payment API Member',
            'status' => 'active',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '100',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'cash',
            ]);

        $response->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, MemberLedgerEntry::query()->count());
    }

    public function test_unauthenticated_user_cannot_record_payment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'LCB-PAY-004',
            'full_name' => 'Unauthenticated Payment API Member',
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
            'amount' => '100',
            'payment_date' => '2026-06-08',
            'payment_mode' => 'cash',
        ]);

        $response->assertUnauthorized();

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, MemberLedgerEntry::query()->count());
    }

    public function test_cross_club_member_payment_recording_fails_closed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();
        $this->grantPaymentRecordPermissionToRole('president-admin');

        $otherClub = Club::query()->create([
            'name' => 'Other Club',
            'code' => 'OTHER-PAY',
            'district' => '318E',
        ]);

        $otherMember = Member::query()->create([
            'club_id' => $otherClub->id,
            'member_code' => 'OTHER-PAY-001',
            'full_name' => 'Other Club Payment Member',
            'status' => 'active',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/payments", [
                'amount' => '100',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'cash',
            ]);

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Member does not belong to this club.',
            ]);

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, MemberLedgerEntry::query()->count());
    }

    public function test_payment_api_validates_required_fields_and_rejects_backend_controlled_fields(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();
        $this->grantPaymentRecordPermissionToRole('president-admin');

        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'LCB-PAY-005',
            'full_name' => 'Validation Payment API Member',
            'status' => 'active',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '0',
                'payment_date' => 'not-a-date',
                'payment_mode' => 'crypto',
                'club_id' => $club->id,
                'member_id' => $member->id,
                'received_by_user_id' => $user->id,
                'ledger_entry_id' => 999,
                'status' => 'cancelled',
                'receipt_number' => 'R-001',
                'receipt_id' => 1,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'amount',
                'payment_date',
                'payment_mode',
                'club_id',
                'member_id',
                'received_by_user_id',
                'ledger_entry_id',
                'status',
                'receipt_number',
                'receipt_id',
            ]);

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, MemberLedgerEntry::query()->count());
    }

    public function test_3q_does_not_add_unapproved_payment_receipt_or_finance_routes(): void
    {
        $routes = collect(Route::getRoutes())
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->filter(fn (string $route): bool => str_contains($route, 'payment')
                || str_contains($route, 'payments')
                || str_contains($route, 'receipt')
                || str_contains($route, 'receipts'))
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf',
            'POST api/v1/clubs/{club}/members/{member}/payments',
            'POST api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
        ], $routes);
    }

    public function test_3q_does_not_add_blocked_receipt_report_reminder_excel_or_frontend_files(): void
    {
        $blockedPaths = [
            app_path('Http/Controllers/ReceiptController.php'),
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

    private function grantPaymentRecordPermissionToRole(string $roleSlug): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => PaymentRecordingService::PERMISSION_RECORD_PAYMENT],
            [
                'name' => 'Record Payments',
                'group' => 'finance',
                'description' => 'Allows recording member payments.',
            ]
        );

        $role = Role::where('slug', $roleSlug)->firstOrFail();

        if (! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
            $role->permissions()->attach($permission);
        }
    }
}
