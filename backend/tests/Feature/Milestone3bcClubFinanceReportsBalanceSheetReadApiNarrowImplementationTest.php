<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Milestone3bcClubFinanceReportsBalanceSheetReadApiNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3bc_club_finance_reports_balance_sheet_read_api_narrow_implementation.md';

    public function test_3bc_document_exists_and_records_narrow_boundary(): void
    {
        $this->assertFileExists($this->docPath);

        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Milestone 3BC — Club Finance Reports and Balance Sheet Read API Narrow Implementation', $document);
        $this->assertStringContainsString('NARROW IMPLEMENTATION COMPLETE', $document);
        $this->assertStringContainsString('read-only Club Finance Reports and Balance Sheet API', $document);
        $this->assertStringContainsString('This milestone does not create, update, delete, allocate, reverse, cancel, remind, export, import, generate receipt, generate PDF, share receipt, or mutate finance records.', $document);
        $this->assertStringContainsString('Normal Member users are blocked from club-level finance reports.', $document);
    }

    public function test_club_finance_report_routes_are_registered_as_get_only(): void
    {
        $reportRoute = collect(Route::getRoutes())
            ->first(fn ($route): bool => $route->uri() === 'api/v1/clubs/{club}/finance/reports');

        $balanceSheetRoute = collect(Route::getRoutes())
            ->first(fn ($route): bool => $route->uri() === 'api/v1/clubs/{club}/finance/balance-sheet');

        $this->assertNotNull($reportRoute);
        $this->assertNotNull($balanceSheetRoute);

        $this->assertSame(['GET', 'HEAD'], $reportRoute->methods());
        $this->assertSame(['GET', 'HEAD'], $balanceSheetRoute->methods());
    }

    public function test_president_secretary_and_treasurer_can_read_club_finance_reports(): void
    {
        [$club] = $this->clubWithFinanceData();

        foreach (['president-admin', 'secretary', 'treasurer'] as $roleSlug) {
            $user = $this->userWithRole($club, $roleSlug);

            $this->actingAs($user)
                ->getJson("/api/v1/clubs/{$club->id}/finance/reports")
                ->assertOk()
                ->assertJsonPath('finance_reports.club_id', $club->id)
                ->assertJsonPath('finance_reports.club_finance_overview.total_debit_amount', '1500.00')
                ->assertJsonPath('finance_reports.club_finance_overview.total_credit_amount', '700.00')
                ->assertJsonPath('finance_reports.club_finance_overview.current_dues_amount', '800.00')
                ->assertJsonPath('finance_reports.outstanding_dues_summary.total_outstanding_amount', '800.00')
                ->assertJsonPath('finance_reports.payment_collection_summary.posted_payment_total', '700.00')
                ->assertJsonPath('finance_reports.payment_collection_summary.posted_payment_count', 1)
                ->assertJsonPath('finance_reports.receipt_summary.issued_receipt_count', 1);
        }
    }

    public function test_president_secretary_and_treasurer_can_read_balance_sheet(): void
    {
        [$club] = $this->clubWithFinanceData();

        foreach (['president-admin', 'secretary', 'treasurer'] as $roleSlug) {
            $user = $this->userWithRole($club, $roleSlug);

            $this->actingAs($user)
                ->getJson("/api/v1/clubs/{$club->id}/finance/balance-sheet")
                ->assertOk()
                ->assertJsonPath('balance_sheet.club_id', $club->id)
                ->assertJsonPath('balance_sheet.assets.member_receivables', '800.00')
                ->assertJsonPath('balance_sheet.assets.cash_collected', '700.00')
                ->assertJsonPath('balance_sheet.liabilities.member_advances', '0.00')
                ->assertJsonPath('balance_sheet.net_position.amount', '-800.00')
                ->assertJsonPath('balance_sheet.net_position.label', 'Amount Receivable')
                ->assertJsonPath('balance_sheet.source', 'existing_posted_ledger_entries_only');
        }
    }

    public function test_normal_member_cannot_read_club_level_finance_reports_or_balance_sheet(): void
    {
        [$club] = $this->clubWithFinanceData();
        $user = $this->userWithRole($club, 'member');

        $this->actingAs($user)
            ->getJson("/api/v1/clubs/{$club->id}/finance/reports")
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson("/api/v1/clubs/{$club->id}/finance/balance-sheet")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_read_club_finance_reports_or_balance_sheet(): void
    {
        [$club] = $this->clubWithFinanceData();

        $this->getJson("/api/v1/clubs/{$club->id}/finance/reports")
            ->assertUnauthorized();

        $this->getJson("/api/v1/clubs/{$club->id}/finance/balance-sheet")
            ->assertUnauthorized();
    }

    public function test_cross_club_finance_reports_fail_closed(): void
    {
        [$club] = $this->clubWithFinanceData();
        $otherClub = $this->createClub('Other Finance Club');
        $user = $this->userWithRole($club, 'treasurer');

        $this->actingAs($user)
            ->getJson("/api/v1/clubs/{$otherClub->id}/finance/reports")
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson("/api/v1/clubs/{$otherClub->id}/finance/balance-sheet")
            ->assertForbidden();
    }

    public function test_report_routes_do_not_add_mutation_methods(): void
    {
        $financeReportUris = [
            'api/v1/clubs/{club}/finance/reports',
            'api/v1/clubs/{club}/finance/balance-sheet',
        ];

        foreach ($financeReportUris as $uri) {
            $routes = collect(Route::getRoutes())
                ->filter(fn ($route): bool => $route->uri() === $uri);

            $this->assertCount(1, $routes);

            $methods = $routes->first()->methods();

            $this->assertContains('GET', $methods);
            $this->assertNotContains('POST', $methods);
            $this->assertNotContains('PATCH', $methods);
            $this->assertNotContains('PUT', $methods);
            $this->assertNotContains('DELETE', $methods);
        }
    }

    public function test_3bc_does_not_add_blocked_runtime_surfaces(): void
    {
        $this->assertFileDoesNotExist(app_path('Http/Requests/Finance/ClubFinanceReportRequest.php'));
        $this->assertFileDoesNotExist(app_path('Models/ClubFinanceReport.php'));
        $this->assertFileDoesNotExist(database_path('migrations/create_club_finance_reports_table.php'));
        $this->assertFileDoesNotExist(base_path('../app/page.tsx.3bc'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/PaymentCancellationService.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/PaymentReversalService.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/ReceiptSharingService.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/FinanceExportService.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/ExcelImportService.php'));
    }
    private function createClub(string $name = 'Lions Club Beenachi Test'): Club
    {
        $columns = Schema::getColumnListing('clubs');

        $attributes = [];

        foreach ([
            'name' => $name,
            'club_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . substr(md5($name . microtime()), 0, 6),
            'code' => 'LCB' . random_int(1000, 9999),
            'club_code' => 'LCB' . random_int(1000, 9999),
            'district' => '318E',
            'status' => 'active',
        ] as $column => $value) {
            if (in_array($column, $columns, true)) {
                $attributes[$column] = $value;
            }
        }

        return Club::unguarded(fn (): Club => Club::query()->create($attributes));
    }

    private function createMember(Club $club): Member
    {
        $columns = Schema::getColumnListing('members');

        $attributes = [
            'club_id' => $club->id,
        ];

        foreach ([
            'member_code' => 'LFM001',
            'full_name' => 'Lion Finance Member',
            'name' => 'Lion Finance Member',
            'phone' => '9999999999',
            'mobile' => '9999999999',
            'email' => 'lion.finance@example.test',
            'status' => 'active',
            'membership_status' => 'active',
            'joined_at' => '2026-06-01',
            'joined_on' => '2026-06-01',
            'join_date' => '2026-06-01',
        ] as $column => $value) {
            if (in_array($column, $columns, true)) {
                $attributes[$column] = $value;
            }
        }

        return Member::unguarded(fn (): Member => Member::query()->create($attributes));
    }


    /**
     * @return array{0: Club, 1: Member}
     */
    private function clubWithFinanceData(): array
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);

        $club = $this->createClub();
        $admin = $this->userWithRole($club, 'president-admin');

        $member = $this->createMember($club);

        MemberLedgerEntry::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => $admin->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_DEBIT,
            'source_type' => MemberLedgerEntry::SOURCE_MONTHLY_DUES,
            'amount' => '1000.00',
            'accounting_month' => '2026-06',
            'due_date' => '2026-06-30',
            'transaction_date' => '2026-06-01',
            'reference_number' => 'DUE-001',
            'description' => 'Monthly dues',
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);

        MemberLedgerEntry::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => $admin->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_DEBIT,
            'source_type' => MemberLedgerEntry::SOURCE_SPECIAL_COLLECTION,
            'amount' => '500.00',
            'accounting_month' => '2026-06',
            'due_date' => '2026-06-30',
            'transaction_date' => '2026-06-02',
            'reference_number' => 'SPC-001',
            'description' => 'Special collection',
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);

        $creditEntry = MemberLedgerEntry::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => $admin->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_CREDIT,
            'source_type' => MemberLedgerEntry::SOURCE_PAYMENT,
            'amount' => '700.00',
            'accounting_month' => '2026-06',
            'due_date' => null,
            'transaction_date' => '2026-06-05',
            'reference_number' => 'PAY-001',
            'description' => 'Payment received',
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);

        $payment = Payment::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'amount' => '700.00',
            'payment_date' => '2026-06-05',
            'payment_mode' => 'cash',
            'reference_number' => 'PAY-001',
            'notes' => 'Payment for dues',
            'received_by_user_id' => $admin->id,
            'ledger_entry_id' => $creditEntry->id,
            'status' => 'posted',
        ]);

        Receipt::query()->create([
            'club_id' => $club->id,
            'payment_id' => $payment->id,
            'receipt_number' => 'RCPT-2026-0001',
            'receipt_date' => '2026-06-05',
            'issued_by_user_id' => $admin->id,
            'status' => 'issued',
            'notes' => 'Issued receipt',
        ]);

        return [$club, $member];
    }

    private function userWithRole(Club $club, string $roleSlug): User
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return $user;
    }
}
