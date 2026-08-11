<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\Finance\MemberFinanceSummaryController;
use App\Http\Resources\Finance\MemberFinanceSummaryResource;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\MemberFinanceSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3ajOutstandingDuesFinanceSummaryReadApiValidationAndClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_3aj_closure_document_exists_and_records_validation_decision(): void
    {
        $path = base_path('docs/milestone_3aj_outstanding_dues_finance_summary_read_api_validation_and_closure.md');

        $this->assertFileExists($path);

        $contents = File::get($path);

        $this->assertStringContainsString('Milestone 3AJ', $contents);
        $this->assertStringContainsString('OUTSTANDING_DUES_FINANCE_SUMMARY_READ_API_VALIDATED_AND_CLOSED', $contents);
        $this->assertStringContainsString('Docs/tests-only validation and closure', $contents);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/finance/member-summary', $contents);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/members/{member}/finance-summary', $contents);
        $this->assertStringContainsString('No controller changes', $contents);
        $this->assertStringContainsString('No service changes', $contents);
        $this->assertStringContainsString('No resource changes', $contents);
        $this->assertStringContainsString('No route changes', $contents);
        $this->assertStringContainsString('No frontend', $contents);
        $this->assertStringContainsString('No dashboard', $contents);
        $this->assertStringContainsString('No reports', $contents);
        $this->assertStringContainsString('No reminders', $contents);
        $this->assertStringContainsString('No Excel', $contents);
        $this->assertStringContainsString('No PDF', $contents);
        $this->assertStringContainsString('No payment mutation', $contents);
        $this->assertStringContainsString('No receipt mutation', $contents);
        $this->assertStringContainsString('No ledger mutation', $contents);
    }

    public function test_3ai_finance_summary_implementation_files_remain_present(): void
    {
        $this->assertTrue(class_exists(MemberFinanceSummaryController::class));
        $this->assertTrue(class_exists(MemberFinanceSummaryService::class));
        $this->assertTrue(class_exists(MemberFinanceSummaryResource::class));

        $this->assertFileExists(app_path('Http/Controllers/Api/V1/Finance/MemberFinanceSummaryController.php'));
        $this->assertFileExists(app_path('Services/Finance/MemberFinanceSummaryService.php'));
        $this->assertFileExists(app_path('Http/Resources/Finance/MemberFinanceSummaryResource.php'));
        $this->assertFileExists(base_path('tests/Feature/Milestone3aiOutstandingDuesFinanceSummaryReadApiNarrowImplementationTest.php'));
        $this->assertFileExists(base_path('docs/milestone_3ai_outstanding_dues_finance_summary_read_api_narrow_implementation.md'));
    }

    public function test_finance_summary_routes_remain_get_only_and_exact(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'finance-summary') || str_contains($route->uri(), 'finance/member-summary'))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/finance/member-summary',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/finance-summary',
        ], $routes);

        foreach (Route::getRoutes() as $route) {
            if (! str_contains($route->uri(), 'finance-summary') && ! str_contains($route->uri(), 'finance/member-summary')) {
                continue;
            }

            $this->assertContains('GET', $route->methods());
            $this->assertNotContains('POST', $route->methods());
            $this->assertNotContains('PATCH', $route->methods());
            $this->assertNotContains('PUT', $route->methods());
            $this->assertNotContains('DELETE', $route->methods());
        }
    }

    public function test_member_own_summary_access_remains_member_friendly_and_read_only(): void
    {
        $club = $this->createClub();
        $memberUser = $this->createUserWithRole($club, 'member');
        $member = $this->createMember($club, $memberUser, 'MEM-3AJ-OWN');

        $this->createDebit($club, $member, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00, '2026-06-10');
        $this->createDebit($club, $member, MemberLedgerEntry::SOURCE_SPECIAL_COLLECTION, 300.00, '2026-06-20');
        $this->createCredit($club, $member, 500.00);

        $payment = $this->createPostedPayment($club, $member, $memberUser, 500.00, '2026-06-11');
        $this->createIssuedReceipt($club, $payment, $memberUser);

        Sanctum::actingAs($memberUser);

        $response = $this->getJson("/api/v1/clubs/{$club->id}/finance/member-summary");

        $response->assertOk()
            ->assertJsonPath('finance_summary.member_id', $member->id)
            ->assertJsonPath('finance_summary.member_code', 'MEM-3AJ-OWN')
            ->assertJsonPath('finance_summary.current_dues_amount', '800.00')
            ->assertJsonPath('finance_summary.current_dues_label', 'Current Dues')
            ->assertJsonPath('finance_summary.payment_status', 'Partly Paid')
            ->assertJsonPath('finance_summary.last_payment_amount', '500.00')
            ->assertJsonPath('finance_summary.last_payment_date', '2026-06-11')
            ->assertJsonPath('finance_summary.receipt_available', true)
            ->assertJsonPath('finance_summary.receipt_status', 'issued')
            ->assertJsonPath('finance_summary.pending_items.0.source_label', 'Monthly Dues')
            ->assertJsonPath('finance_summary.pending_items.0.amount', '500.00')
            ->assertJsonPath('finance_summary.pending_items.0.status', 'Partly Paid')
            ->assertJsonPath('finance_summary.pending_items.1.source_label', 'Special Collection')
            ->assertJsonPath('finance_summary.pending_items.1.amount', '300.00')
            ->assertJsonPath('finance_summary.pending_items.1.status', 'Pending');

        $this->assertSame(3, MemberLedgerEntry::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_selected_summary_access_remains_pst_only(): void
    {
        foreach (['treasurer', 'secretary', 'president-admin'] as $roleSlug) {
            $club = $this->createClub('LCB-3AJ-'.substr(md5($roleSlug), 0, 6));
            $pstUser = $this->createUserWithRole($club, $roleSlug);
            $member = $this->createMember($club, null, 'MEM-3AJ-'.strtoupper(substr(md5($roleSlug), 0, 6)));

            $this->createDebit($club, $member, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 700.00);

            Sanctum::actingAs($pstUser);

            $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/finance-summary")
                ->assertOk()
                ->assertJsonPath('finance_summary.member_id', $member->id)
                ->assertJsonPath('finance_summary.current_dues_amount', '700.00')
                ->assertJsonPath('finance_summary.payment_status', 'Pending');
        }
    }

    public function test_normal_member_cross_club_and_unauthenticated_access_fail_closed(): void
    {
        $club = $this->createClub('LCB-3AJ-A');
        $otherClub = $this->createClub('LCB-3AJ-B');

        $normalUser = $this->createUserWithRole($club, 'member');
        $ownMember = $this->createMember($club, $normalUser, 'MEM-3AJ-NORMAL');
        $otherMember = $this->createMember($otherClub, null, 'MEM-3AJ-OTHER');

        Sanctum::actingAs($normalUser);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/finance-summary")
            ->assertForbidden();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/finance-summary")
            ->assertNotFound();

        $this->getJson("/api/v1/clubs/{$otherClub->id}/finance/member-summary")
            ->assertForbidden();

    }

    public function test_unauthenticated_finance_summary_access_fails_closed(): void
    {
        $club = $this->createClub('LCB-3AJ-PUBLIC');
        $member = $this->createMember($club, null, 'MEM-3AJ-PUBLIC');

        $this->getJson("/api/v1/clubs/{$club->id}/finance/member-summary")
            ->assertUnauthorized();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/finance-summary")
            ->assertUnauthorized();
    }
    public function test_optional_query_flags_remain_display_only_and_do_not_mutate_records(): void
    {
        $club = $this->createClub();
        $memberUser = $this->createUserWithRole($club, 'member');
        $member = $this->createMember($club, $memberUser, 'MEM-3AJ-FLAGS');

        $this->createDebit($club, $member, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 900.00);
        $this->createCredit($club, $member, 200.00);
        $this->createPostedPayment($club, $member, $memberUser, 200.00, '2026-06-12');

        Sanctum::actingAs($memberUser);

        $response = $this->getJson("/api/v1/clubs/{$club->id}/finance/member-summary?include_pending_items=false&include_last_payment=false&include_receipt_status=false");

        $response->assertOk()
            ->assertJsonPath('finance_summary.current_dues_amount', '700.00')
            ->assertJsonPath('finance_summary.pending_items', [])
            ->assertJsonPath('finance_summary.last_payment_amount', null)
            ->assertJsonPath('finance_summary.last_payment_date', null)
            ->assertJsonPath('finance_summary.receipt_available', null)
            ->assertJsonPath('finance_summary.receipt_status', null);

        $this->assertSame(2, MemberLedgerEntry::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_3aj_adds_no_new_implementation_scope_files(): void
    {
        $blockedPaths = [
            app_path('Http/Requests/Finance/MemberFinanceSummaryRequest.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/PaymentCorrectionService.php'),
            app_path('Services/Finance/SpecialCollectionAllocationService.php'),
            app_path('Services/Finance/FellowshipPaymentService.php'),
            app_path('Services/Finance/PaymentReminderService.php'),
            app_path('Services/Reports/FinanceReportService.php'),
            app_path('Services/Imports/ExcelImportService.php'),
            base_path('../app/finance'),
            base_path('../app/reports'),
            base_path('../app/reminders'),
        ];

        foreach ($blockedPaths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    private function createClub(string $code = 'LCB-3AJ'): Club
    {
        return Club::query()->create([
            'name' => 'Lions Club Beenachi',
            'code' => $code,
            'district' => '318E',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(Club $club, string $roleSlug): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'club.view'],
            [
                'name' => 'View Club',
                'group' => 'club',
                'description' => 'View club foundation details.',
            ]
        );

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => match ($roleSlug) {
                    'president-admin' => 'President/Admin',
                    'secretary' => 'Secretary',
                    'treasurer' => 'Treasurer',
                    default => 'Member',
                },
                'description' => '3AJ test role.',
                'is_system' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create();

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function createMember(Club $club, ?User $user = null, string $memberCode = 'MEM-3AJ-001'): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => $user?->id,
            'member_code' => $memberCode,
            'full_name' => 'Lion Finance Summary '.$memberCode,
            'phone' => '9999999999',
            'email' => strtolower($memberCode).'@lcbeenachi.test',
            'status' => 'active',
            'joined_at' => '2026-06-01',
            'blood_group' => 'O+',
            'blood_donor_consent' => true,
        ]);
    }

    private function createDebit(
        Club $club,
        Member $member,
        string $sourceType,
        float $amount,
        string $dueDate = '2026-06-10'
    ): MemberLedgerEntry {
        return MemberLedgerEntry::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create()->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_DEBIT,
            'source_type' => $sourceType,
            'amount' => $amount,
            'accounting_month' => '2026-06',
            'due_date' => $dueDate,
            'transaction_date' => $dueDate,
            'description' => '3AJ validation due item',
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);
    }

    private function createCredit(Club $club, Member $member, float $amount): MemberLedgerEntry
    {
        return MemberLedgerEntry::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create()->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_CREDIT,
            'source_type' => MemberLedgerEntry::SOURCE_PAYMENT,
            'amount' => $amount,
            'accounting_month' => null,
            'due_date' => null,
            'transaction_date' => '2026-06-10',
            'description' => '3AJ validation payment credit',
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);
    }

    private function createPostedPayment(
        Club $club,
        Member $member,
        User $receivedBy,
        float $amount,
        string $paymentDate
    ): Payment {
        return Payment::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'payment_mode' => 'cash',
            'reference_number' => '3AJ-PAY-'.uniqid(),
            'notes' => '3AJ existing payment',
            'received_by_user_id' => $receivedBy->id,
            'ledger_entry_id' => null,
            'status' => 'posted',
        ]);
    }

    private function createIssuedReceipt(Club $club, Payment $payment, User $issuedBy): Receipt
    {
        return Receipt::query()->create([
            'club_id' => $club->id,
            'payment_id' => $payment->id,
            'receipt_number' => 'LCB/2026-27/RCP/'.str_pad((string) $payment->id, 4, '0', STR_PAD_LEFT),
            'receipt_date' => $payment->payment_date,
            'issued_by_user_id' => $issuedBy->id,
            'status' => 'issued',
            'notes' => '3AJ existing receipt',
        ]);
    }
}
