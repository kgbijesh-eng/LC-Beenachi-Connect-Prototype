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

class Milestone3rPaymentRecordingApiValidationAndClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_3r_validation_document_exists_and_records_closure_boundary(): void
    {
        $path = base_path('docs/milestone_3r_payment_recording_api_validation_and_closure.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3R', $content);
        $this->assertStringContainsString('Payment Recording API Validation and Closure', $content);
        $this->assertStringContainsString('Validation and closure checkpoint', $content);
        $this->assertStringContainsString('docs/tests-only', $content);
        $this->assertStringContainsString('No route change', $content);
        $this->assertStringContainsString('No controller change', $content);
        $this->assertStringContainsString('No request class change', $content);
        $this->assertStringContainsString('No resource class change', $content);
        $this->assertStringContainsString('No service change', $content);
    }

    public function test_3q_route_exists_as_only_authorized_payment_route(): void
    {
        $paymentRoutes = collect(Route::getRoutes())
            ->filter(function ($route): bool {
                $uri = $route->uri();

                return str_contains($uri, 'payment')
                    || str_contains($uri, 'payments')
                    || str_contains($uri, 'receipt')
                    || str_contains($uri, 'receipts');
            })
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'POST api/v1/clubs/{club}/members/{member}/payments',
        ], $paymentRoutes);
    }

    public function test_controller_delegates_to_payment_recording_service_without_manual_posting_logic(): void
    {
        $path = app_path('Http/Controllers/Api/V1/Finance/PaymentController.php');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('PaymentRecordingService', $content);
        $this->assertStringContainsString('$payments->record', $content);
        $this->assertStringContainsString('PaymentResource', $content);
        $this->assertStringContainsString('Member does not belong to this club.', $content);

        $this->assertStringNotContainsString('Payment::query()->create', $content);
        $this->assertStringNotContainsString('Payment::create', $content);
        $this->assertStringNotContainsString('MemberLedgerEntry::query()->create', $content);
        $this->assertStringNotContainsString('MemberLedgerEntry::create', $content);
        $this->assertStringNotContainsString("'ledger_entry_id' =>", $content);
    }

    public function test_request_accepts_only_approved_fields_and_blocks_backend_controlled_fields(): void
    {
        $path = app_path('Http/Requests/Finance/RecordPaymentRequest.php');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        foreach ([
            "'amount' => ['required', 'numeric', 'gt:0']",
            "'payment_date' => ['required', 'date']",
            "'payment_mode' => ['required', Rule::in(Payment::MODES)]",
            "'reference_number' => ['nullable', 'string', 'max:100']",
            "'notes' => ['nullable', 'string', 'max:1000']",
        ] as $allowedRule) {
            $this->assertStringContainsString($allowedRule, $content);
        }

        foreach ([
            'club_id',
            'member_id',
            'received_by_user_id',
            'ledger_entry_id',
            'status',
            'cancelled_at',
            'cancelled_by_user_id',
            'cancellation_reason',
            'receipt_number',
            'receipt_id',
        ] as $blockedField) {
            $this->assertStringContainsString("'{$blockedField}' => ['prohibited']", $content);
        }
    }

    public function test_resource_exposes_only_safe_payment_response_fields(): void
    {
        $path = app_path('Http/Resources/Finance/PaymentResource.php');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        foreach ([
            'id',
            'club_id',
            'member_id',
            'amount',
            'payment_date',
            'payment_mode',
            'reference_number',
            'notes',
            'status',
            'ledger_entry_id',
            'received_by_user_id',
            'created_at',
            'updated_at',
        ] as $field) {
            $this->assertStringContainsString("'{$field}'", $content);
        }

        foreach ([
            'full_name',
            'email',
            'phone',
            'blood_group',
            'metadata',
            'receipt_number',
            'receipt_id',
            'cancelled_at',
            'cancelled_by_user_id',
            'cancellation_reason',
            'ledger_entries',
            'balance',
        ] as $blockedField) {
            $this->assertStringNotContainsString("'{$blockedField}'", $content);
        }
    }

    public function test_authorized_president_payment_api_flow_remains_validated(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $this->grantPaymentRecordPermissionToRole('president-admin');

        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'LCB-3R-001',
            'full_name' => '3R Payment Validation Member',
            'status' => 'active',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '1250.50',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'upi',
                'reference_number' => '3R-UPI-001',
                'notes' => '3R validation payment',
            ]);

        $response->assertCreated()
            ->assertJsonPath('payment.club_id', $club->id)
            ->assertJsonPath('payment.member_id', $member->id)
            ->assertJsonPath('payment.amount', '1250.50')
            ->assertJsonPath('payment.payment_date', '2026-06-08')
            ->assertJsonPath('payment.payment_mode', 'upi')
            ->assertJsonPath('payment.reference_number', '3R-UPI-001')
            ->assertJsonPath('payment.notes', '3R validation payment')
            ->assertJsonPath('payment.status', 'posted')
            ->assertJsonPath('payment.received_by_user_id', $user->id);

        $paymentId = $response->json('payment.id');
        $ledgerEntryId = $response->json('payment.ledger_entry_id');

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'club_id' => $club->id,
            'member_id' => $member->id,
            'ledger_entry_id' => $ledgerEntryId,
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('member_ledger_entries', [
            'id' => $ledgerEntryId,
            'club_id' => $club->id,
            'member_id' => $member->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_CREDIT,
            'source_type' => MemberLedgerEntry::SOURCE_PAYMENT,
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);
    }

    public function test_normal_member_cross_club_and_invalid_input_guards_remain_enforced(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $memberRole = Role::where('slug', 'member')->firstOrFail();

        $normalUser = User::factory()->create([
            'email' => '3r-normal-member@lcbeenachi.test',
        ]);

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $normalUser->id,
            'role_id' => $memberRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'LCB-3R-002',
            'full_name' => '3R Guard Member',
            'status' => 'active',
        ]);

        $normalToken = $normalUser->createToken('test-token')->plainTextToken;

        $this
            ->withHeader('Authorization', 'Bearer '.$normalToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '100',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'cash',
            ])
            ->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, MemberLedgerEntry::query()->count());

        $president = User::where('email', 'president@lcbeenachi.test')->firstOrFail();
        $this->grantPaymentRecordPermissionToRole('president-admin');
        $presidentToken = $president->createToken('test-token')->plainTextToken;

        $otherClub = Club::query()->create([
            'name' => '3R Other Club',
            'code' => '3R-OTHER',
            'district' => '318E',
        ]);

        $otherMember = Member::query()->create([
            'club_id' => $otherClub->id,
            'member_code' => '3R-OTHER-001',
            'full_name' => '3R Other Club Member',
            'status' => 'active',
        ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$presidentToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/payments", [
                'amount' => '100',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'cash',
            ])
            ->assertForbidden();

    }


    public function test_invalid_payment_input_fails_request_validation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $this->grantPaymentRecordPermissionToRole('president-admin');

        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'LCB-3R-003',
            'full_name' => '3R Invalid Input Member',
            'status' => 'active',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '0',
                'payment_date' => 'not-a-date',
                'payment_mode' => 'invalid-mode',
                'club_id' => $club->id,
                'member_id' => $member->id,
                'ledger_entry_id' => 999,
                'status' => 'posted',
                'receipt_number' => 'R-INVALID',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'amount',
                'payment_date',
                'payment_mode',
                'club_id',
                'member_id',
                'ledger_entry_id',
                'status',
                'receipt_number',
            ]);
    }
    public function test_receipt_cancellation_reversal_reports_reminders_excel_and_frontend_remain_absent(): void
    {
        $blockedPaths = [
            app_path('Http/Controllers/ReceiptController.php'),
            app_path('Http/Controllers/Api/V1/Finance/ReceiptController.php'),
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
            base_path('../app/reports/finance'),
            base_path('../app/reminders'),
        ];

        foreach ($blockedPaths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_3r_does_not_create_unapproved_routes(): void
    {
        $routes = collect(Route::getRoutes())
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->filter(fn (string $route): bool => str_contains($route, 'payment')
                || str_contains($route, 'payments')
                || str_contains($route, 'receipt')
                || str_contains($route, 'receipts')
                || str_contains($route, 'cancel')
                || str_contains($route, 'reverse')
                || str_contains($route, 'reminder')
                || str_contains($route, 'report')
                || str_contains($route, 'excel')
                || str_contains($route, 'import'))
            ->values()
            ->all();

        $this->assertSame([
            'POST api/v1/clubs/{club}/members/{member}/payments',
        ], $routes);
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
