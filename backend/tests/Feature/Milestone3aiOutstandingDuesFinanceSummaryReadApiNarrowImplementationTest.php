<?php

declare(strict_types=1);

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3aiOutstandingDuesFinanceSummaryReadApiNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    public function test_3ai_document_records_narrow_read_api_implementation_boundary(): void
    {
        $path = base_path('docs/milestone_3ai_outstanding_dues_finance_summary_read_api_narrow_implementation.md');

        $this->assertFileExists($path);

        $contents = File::get($path);

        $this->assertStringContainsString('Milestone 3AI', $contents);
        $this->assertStringContainsString('OUTSTANDING_DUES_FINANCE_SUMMARY_READ_API_NARROW_IMPLEMENTATION_ADDED', $contents);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/finance/member-summary', $contents);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/members/{member}/finance-summary', $contents);
        $this->assertStringContainsString('MemberFinanceSummaryService', $contents);
        $this->assertStringContainsString('MemberFinanceSummaryController', $contents);
        $this->assertStringContainsString('MemberFinanceSummaryResource', $contents);
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

    public function test_finance_summary_routes_are_registered_as_get_only(): void
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

    public function test_normal_member_can_read_own_finance_summary(): void
    {
        $club = $this->createClub();
        $normalUser = $this->createUserWithRole($club, 'member');
        $member = $this->createMember($club, $normalUser, 'MEM-3AI-OWN');

        $this->createDebit($club, $member, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00, '2026-06-10');
        $this->createDebit($club, $member, MemberLedgerEntry::SOURCE_SPECIAL_COLLECTION, 500.00, '2026-06-15');
        $this->createCredit($club, $member, 600.00);

        $payment = $this->createPostedPayment($club, $member, $normalUser, 600.00, '2026-06-10');
        $this->createIssuedReceipt($club, $payment, $normalUser);

        Sanctum::actingAs($normalUser);

        $response = $this->getJson("/api/v1/clubs/{$club->id}/finance/member-summary");

        $response->assertOk()
            ->assertJsonPath('finance_summary.member_id', $member->id)
            ->assertJsonPath('finance_summary.member_code', 'MEM-3AI-OWN')
            ->assertJsonPath('finance_summary.current_dues_amount', '900.00')
            ->assertJsonPath('finance_summary.current_dues_label', 'Current Dues')
            ->assertJsonPath('finance_summary.payment_status', 'Partly Paid')
            ->assertJsonPath('finance_summary.last_payment_amount', '600.00')
            ->assertJsonPath('finance_summary.last_payment_date', '2026-06-10')
            ->assertJsonPath('finance_summary.receipt_available', true)
            ->assertJsonPath('finance_summary.receipt_status', 'issued')
            ->assertJsonPath('finance_summary.pending_items.0.source_label', 'Monthly Dues')
            ->assertJsonPath('finance_summary.pending_items.0.amount', '400.00')
            ->assertJsonPath('finance_summary.pending_items.0.status', 'Partly Paid')
            ->assertJsonPath('finance_summary.pending_items.1.source_label', 'Special Collection')
            ->assertJsonPath('finance_summary.pending_items.1.amount', '500.00')
            ->assertJsonPath('finance_summary.pending_items.1.status', 'Pending');

        $this->assertSame(3, MemberLedgerEntry::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_normal_member_cannot_read_selected_member_finance_summary(): void
    {
        $club = $this->createClub();
        $normalUser = $this->createUserWithRole($club, 'member');
        $ownMember = $this->createMember($club, $normalUser, 'MEM-3AI-NORMAL');
        $otherMember = $this->createMember($club, null, 'MEM-3AI-OTHER');

        $this->createDebit($club, $ownMember, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00);
        $this->createDebit($club, $otherMember, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 2000.00);

        Sanctum::actingAs($normalUser);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/finance-summary")
            ->assertForbidden();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/finance-summary")
            ->assertForbidden();
    }

    public function test_treasurer_secretary_and_president_admin_can_read_selected_same_club_member_summary(): void
    {
        foreach (['treasurer', 'secretary', 'president-admin'] as $roleSlug) {
            $club = $this->createClub('LCB-'.strtoupper($roleSlug).uniqid());
            $user = $this->createUserWithRole($club, $roleSlug);
            $member = $this->createMember($club, null, 'MEM-3AI-'.strtoupper($roleSlug).uniqid());

            $this->createDebit($club, $member, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00);

            Sanctum::actingAs($user);

            $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/finance-summary")
                ->assertOk()
                ->assertJsonPath('finance_summary.member_id', $member->id)
                ->assertJsonPath('finance_summary.current_dues_amount', '1000.00')
                ->assertJsonPath('finance_summary.current_dues_label', 'Current Dues')
                ->assertJsonPath('finance_summary.payment_status', 'Pending');
        }
    }

    public function test_cross_club_selected_member_finance_summary_fails_closed(): void
    {
        $club = $this->createClub('LCB-ONE');
        $otherClub = $this->createClub('LCB-TWO');

        $treasurer = $this->createUserWithRole($club, 'treasurer');
        $otherClubMember = $this->createMember($otherClub, null, 'MEM-3AI-CROSS');

        Sanctum::actingAs($treasurer);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherClubMember->id}/finance-summary")
            ->assertNotFound()
            ->assertJson([
                'message' => 'Member does not belong to this club.',
            ]);
    }

    public function test_unauthenticated_user_cannot_read_finance_summary(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club, null, 'MEM-3AI-PUBLIC');

        $this->getJson("/api/v1/clubs/{$club->id}/finance/member-summary")
            ->assertUnauthorized();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/finance-summary")
            ->assertUnauthorized();
    }

    public function test_query_parameters_can_hide_optional_summary_sections_without_mutation(): void
    {
        $club = $this->createClub();
        $normalUser = $this->createUserWithRole($club, 'member');
        $member = $this->createMember($club, $normalUser, 'MEM-3AI-QUERY');

        $this->createDebit($club, $member, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00);
        $this->createCredit($club, $member, 250.00);
        $this->createPostedPayment($club, $member, $normalUser, 250.00, '2026-06-10');

        Sanctum::actingAs($normalUser);

        $response = $this->getJson("/api/v1/clubs/{$club->id}/finance/member-summary?include_pending_items=false&include_last_payment=false&include_receipt_status=false");

        $response->assertOk()
            ->assertJsonPath('finance_summary.current_dues_amount', '750.00')
            ->assertJsonPath('finance_summary.pending_items', [])
            ->assertJsonPath('finance_summary.last_payment_amount', null)
            ->assertJsonPath('finance_summary.last_payment_date', null)
            ->assertJsonPath('finance_summary.receipt_available', null)
            ->assertJsonPath('finance_summary.receipt_status', null);

        $this->assertSame(2, MemberLedgerEntry::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_3ai_does_not_add_blocked_scope_files(): void
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

    private function createClub(string $code = 'LCB-3AI'): Club
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
                'description' => '3AI test role.',
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

    private function createMember(Club $club, ?User $user = null, string $memberCode = 'MEM-3AI-001'): Member
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
            'description' => '3AI read-only due item',
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
            'description' => '3AI read-only payment credit',
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
            'reference_number' => '3AI-PAY-'.uniqid(),
            'notes' => '3AI existing payment',
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
            'notes' => '3AI existing receipt',
        ]);
    }
}