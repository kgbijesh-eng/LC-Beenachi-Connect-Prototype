<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3alMemberFinanceSummaryFrontendScopeGateTest extends TestCase
{
    public function test_3al_scope_document_exists_and_records_decision(): void
    {
        $path = base_path('docs/milestone_3al_member_finance_summary_frontend_scope_gate.md');

        $this->assertFileExists($path);

        $contents = File::get($path);

        $this->assertStringContainsString('Milestone 3AL - Member Finance Summary Frontend Scope Gate', $contents);
        $this->assertStringContainsString('MEMBER_FINANCE_SUMMARY_FRONTEND_SCOPE_GATE_DEFINED', $contents);
        $this->assertStringContainsString('Docs/tests-only frontend scope gate', $contents);
        $this->assertStringContainsString('Define the future frontend integration boundary for member-facing finance summary display', $contents);
    }

    public function test_3al_defines_frontend_area_without_implementing_frontend(): void
    {
        $contents = File::get(base_path('docs/milestone_3al_member_finance_summary_frontend_scope_gate.md'));

        foreach ([
            'Member-facing dashboard summary card',
            'Member-facing finance summary panel',
            'Existing member area where Current Dues and Last Payment can be shown clearly',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }

        $this->assertStringContainsString('No frontend change', $contents);
        $this->assertStringContainsString('3AL only defines frontend scope', $contents);
    }

    public function test_3al_defines_allowed_future_api_endpoints(): void
    {
        $contents = File::get(base_path('docs/milestone_3al_member_finance_summary_frontend_scope_gate.md'));

        $this->assertStringContainsString('GET /api/v1/clubs/{club}/finance/member-summary', $contents);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/members/{member}/finance-summary', $contents);

        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'finance/member-summary') || str_contains($route->uri(), 'finance-summary'))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/finance/member-summary',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/finance-summary',
        ], $routes);
    }

    public function test_3al_defines_normal_member_frontend_privacy_boundary(): void
    {
        $contents = File::get(base_path('docs/milestone_3al_member_finance_summary_frontend_scope_gate.md'));

        foreach ([
            'Normal member should use only GET /api/v1/clubs/{club}/finance/member-summary for own finance summary',
            'Normal member must not use selected member finance summary route',
            'Normal member must see member-friendly wording only',
            'Normal member must not see internal ledger, debit, credit, accounting, voucher, or audit wording',
            'Normal members must see only their own finance summary',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_3al_defines_allowed_normal_member_fields(): void
    {
        $contents = File::get(base_path('docs/milestone_3al_member_finance_summary_frontend_scope_gate.md'));

        foreach ([
            'Current Dues',
            'Last Payment',
            'Last Payment Date',
            'Receipt Available',
            'Receipt Status',
            'Pending Items',
            'Payment Status',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_3al_defines_pst_read_only_frontend_boundary(): void
    {
        $contents = File::get(base_path('docs/milestone_3al_member_finance_summary_frontend_scope_gate.md'));

        foreach ([
            'President/Admin, Secretary, and Treasurer may later use selected member finance summary for same-club members',
            'PST selected member finance summary UI must stay read-only in this scope lane',
            'PST UI must not include payment recording, receipt generation, correction, reversal, cancellation, report, reminder, or Excel actions unless separately authorized',
            'PST users must see only same-club selected member finance summary',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_3al_defines_safe_display_loading_empty_and_error_states(): void
    {
        $contents = File::get(base_path('docs/milestone_3al_member_finance_summary_frontend_scope_gate.md'));

        foreach ([
            'Show a simple loading state while finance summary is being fetched',
            'Do not block the whole member directory or whole dashboard if finance summary loading fails',
            'If no dues are pending, show No current dues',
            'If no last payment exists, show No payment recorded yet',
            'If no receipt exists, show Receipt not available',
            'If the finance summary request fails, show Finance summary unavailable',
            'Do not expose technical API errors to normal members',
            'Do not show cross-club or permission failure details to normal members',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_3al_selects_3am_narrow_frontend_implementation_candidate(): void
    {
        $contents = File::get(base_path('docs/milestone_3al_member_finance_summary_frontend_scope_gate.md'));

        foreach ([
            'Next recommended milestone:',
            'Milestone 3AM - Member Finance Summary Frontend Narrow Implementation',
            'Connect existing frontend member-facing area to GET /api/v1/clubs/{club}/finance/member-summary',
            'Display Current Dues, Last Payment, Receipt Available, Payment Status, and Pending Items',
            'Add loading, empty, and safe error states',
            'Do not add payment actions, receipt generation actions, reports, reminders, Excel import, or new backend routes',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_3al_blocks_runtime_backend_and_frontend_implementation_scope(): void
    {
        $contents = File::get(base_path('docs/milestone_3al_member_finance_summary_frontend_scope_gate.md'));

        foreach ([
            'No frontend change',
            'No API change',
            'No controller change',
            'No service change',
            'No resource change',
            'No request change',
            'No model change',
            'No migration change',
            'No seeder change',
            'No dashboard implementation',
            'No report implementation',
            'No reminder implementation',
            'No Excel implementation',
            'No PDF implementation',
            'No payment action',
            'No receipt generation action',
            'No ledger mutation',
            'No correction workflow',
            'No reversal workflow',
            'No cancellation workflow',
            'No special collection workflow',
            'No fellowship allocation workflow',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_3al_does_not_add_blocked_routes_or_runtime_files(): void
    {
        $blockedRouteFragments = [
            'dashboard',
            'report',
            'reports',
            'reminder',
            'reminders',
            'excel',
            'import',
            'imports',
            'correction',
            'corrections',
            'reversal',
            'reversals',
            'cancellation',
            'cancellations',
            'special-collection',
            'special-collections',
            'fellowship',
        ];

        foreach (Route::getRoutes() as $route) {
            foreach ($blockedRouteFragments as $blockedRouteFragment) {
                $this->assertStringNotContainsString($blockedRouteFragment, $route->uri());
            }
        }

        $blockedPaths = [
            app_path('Http/Controllers/Api/V1/Finance/FinanceDashboardController.php'),
            app_path('Http/Controllers/Api/V1/Finance/FinanceReportController.php'),
            app_path('Http/Controllers/Api/V1/Finance/PaymentReminderController.php'),
            app_path('Http/Controllers/Api/V1/Finance/PaymentCorrectionController.php'),
            app_path('Http/Controllers/Api/V1/Finance/PaymentReversalController.php'),
            app_path('Http/Controllers/Api/V1/Finance/PaymentCancellationController.php'),
            app_path('Http/Controllers/Api/V1/Finance/SpecialCollectionController.php'),
            app_path('Http/Controllers/Api/V1/Finance/FellowshipPaymentController.php'),
            app_path('Services/Finance/FinanceDashboardService.php'),
            app_path('Services/Finance/FinanceReportService.php'),
            app_path('Services/Finance/PaymentReminderService.php'),
            app_path('Services/Finance/PaymentCorrectionService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/SpecialCollectionAllocationService.php'),
            app_path('Services/Finance/FellowshipPaymentService.php'),
            app_path('Services/Imports/ExcelImportService.php'),
            base_path('../app/finance-summary'),
            base_path('../app/finance-dashboard'),
            base_path('../app/reports'),
            base_path('../app/reminders'),
        ];

        foreach ($blockedPaths as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3al_adds_only_expected_scope_document_and_test(): void
    {
        $this->assertFileExists(base_path('docs/milestone_3al_member_finance_summary_frontend_scope_gate.md'));
        $this->assertFileExists(base_path('tests/Feature/Milestone3alMemberFinanceSummaryFrontendScopeGateTest.php'));

        $this->assertFileDoesNotExist(app_path('Http/Controllers/Api/V1/Finance/MemberFinanceSummaryFrontendController.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/MemberFinanceSummaryFrontendService.php'));
        $this->assertFileDoesNotExist(app_path('Http/Resources/Finance/MemberFinanceSummaryFrontendResource.php'));
    }
}