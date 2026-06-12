<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3brPaymentHistoryLaneClosureReviewTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3br_payment_history_lane_closure_review.md';

    public function test_3br_document_exists_and_is_docs_tests_only_closure_review(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('Milestone 3BR - Payment History Lane Closure Review and Next Roadmap Decision', $document);
        $this->assertStringContainsString('docs/tests-only closure review and next-roadmap decision milestone', $document);
        $this->assertStringContainsString('It does not implement a new feature.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change API behavior.', $document);
    }

    public function test_3br_records_base_branch_and_allowed_files_only(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('Milestone 3BQ - Payment History Frontend Validation and Closure', $document);
        $this->assertStringContainsString('milestone-3bq-payment-history-frontend-validation-and-closure', $document);
        $this->assertStringContainsString('milestone-3br-payment-history-lane-closure-review', $document);
        $this->assertStringContainsString('backend/docs/milestone_3br_payment_history_lane_closure_review.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3brPaymentHistoryLaneClosureReviewTest.php', $document);
    }

    public function test_3br_blocks_all_runtime_backend_frontend_api_and_workflow_scope(): void
    {
        $document = $this->reviewDocument();

        foreach ([
            'app/page.tsx',
            'frontend runtime files',
            'backend routes',
            'backend controllers',
            'backend services',
            'backend resources',
            'backend requests',
            'backend models',
            'backend middleware',
            'backend migrations',
            'backend seeders',
            'backend config',
            'API behavior',
            'payment implementation files',
            'payment-history implementation files',
            'receipt implementation files',
            'export files',
            'PDF files',
            'Excel files',
            'reminder files',
            'cancellation workflow files',
            'reversal workflow files',
            'allocation workflow files',
            'deployment files',
            'SaaS files',
            'client-specific hardcoding files',
            '3BR must not implement Excel import.',
            '3BR must not choose implementation silently.',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $document);
        }
    }

    public function test_3br_reviews_complete_payment_history_milestone_lane(): void
    {
        $document = $this->reviewDocument();

        foreach ([
            'Milestone 3BK - Payment History Read-Only Scope Gate',
            'Milestone 3BL - Payment History Read-Only Implementation Authorization',
            'Milestone 3BM - Payment History Read-Only Narrow Implementation',
            'Milestone 3BN - Payment History Read-Only Validation and Closure',
            'Milestone 3BO - Payment History Frontend Scope Gate',
            'Milestone 3BP - Payment History Frontend Narrow Implementation',
            'Milestone 3BQ - Payment History Frontend Validation and Closure',
            'The backend API lane is complete through 3BN.',
            'The frontend display lane is complete through 3BQ.',
            'Any future payment history changes require a new explicitly approved scope gate.',
        ] as $laneText) {
            $this->assertStringContainsString($laneText, $document);
        }
    }

    public function test_payment_history_backend_routes_remain_exact_get_only_routes(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'payments/history'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/me/payments/history',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/history',
        ], $routes);
    }

    public function test_3br_confirms_role_boundaries_for_member_and_officer_history(): void
    {
        $document = $this->reviewDocument();

        foreach ([
            'Normal Member remains limited to:',
            'own payment history only.',
            'GET /api/v1/clubs/{club}/members/me/payments/history.',
            'no selected-member payment history access.',
            'no all-member payment history access.',
            'President/Admin, Secretary, and Treasurer remain limited to:',
            'selected-member payment history only.',
            'GET /api/v1/clubs/{club}/members/{member}/payments/history.',
            'active-club scoped access through backend authorization.',
            'no all-member payment history screen.',
            'no all-member payment history route.',
        ] as $roleBoundary) {
            $this->assertStringContainsString($roleBoundary, $document);
        }
    }

    public function test_frontend_payment_history_closure_artifact_still_matches_role_boundary(): void
    {
        $page = $this->frontendPage();
        $paymentHistoryBlock = $this->paymentHistoryBlock($page);

        foreach ([
            'canUseSelectedMemberHistory = role === "president" || role === "secretary" || role === "treasurer"',
            'LOCAL_MEMBER_FINANCE_LOGIN',
            'Member view loads only your own payment history.',
            'Other members\' payment history is not requested.',
            '"/members/" + memberId + "/payments/history?per_page=10&page="',
            '"/members/me/payments/history?per_page=10&page="',
            'Selected member in active club',
            '{ label: "My Payment History", screen: "payment-history" }',
        ] as $frontendText) {
            $this->assertStringContainsString($frontendText, $page.$paymentHistoryBlock);
        }
    }

    public function test_3br_confirms_no_all_member_or_mutation_payment_history_behavior(): void
    {
        $document = $this->reviewDocument();
        $paymentHistoryBlock = $this->paymentHistoryBlock($this->frontendPage());

        foreach ([
            'all-member payment history screen',
            'all-member payment history route',
            'payment create UI',
            'payment edit UI',
            'payment delete UI',
            'payment mutation API',
            'cancellation UI',
            'cancellation API',
            'reversal UI',
            'reversal API',
            'correction UI',
            'allocation UI',
            'allocation API',
            'receipt generation UI',
            'receipt mutation UI',
            'export UI',
            'export API',
            'PDF export UI',
            'Excel export UI',
            'reminder UI',
            'reminder API',
            'audit dashboard',
            'deployment implementation',
            'SaaS implementation',
            'client-specific behavior',
        ] as $blockedBehavior) {
            $this->assertStringContainsString($blockedBehavior, $document);
        }

        foreach ([
            'All Payment History',
            'All Members Payment History',
            'Create Payment',
            'Edit Payment',
            'Delete Payment',
            'Cancel Payment',
            'Reverse Payment',
            'Allocate Payment',
            'Generate Receipt',
            'Export Payment History',
            'Send Reminder',
        ] as $blockedFrontendText) {
            $this->assertStringNotContainsString($blockedFrontendText, $paymentHistoryBlock);
        }
    }

    public function test_3br_confirms_backend_api_drift_did_not_occur_after_3bm(): void
    {
        $document = $this->reviewDocument();

        foreach ([
            'after 3BM',
            'payment history backend route boundary remains exactly',
            'GET /api/v1/clubs/{club}/members/me/payments/history',
            'GET /api/v1/clubs/{club}/members/{member}/payments/history',
            'No all-member payment history endpoint is present.',
            'No backend API behavior changes are made in 3BR.',
            'No backend runtime files are edited in 3BR.',
        ] as $driftText) {
            $this->assertStringContainsString($driftText, $document);
        }
    }

    public function test_3br_confirms_finance_module_is_stable_enough_for_next_lane_selection(): void
    {
        $document = $this->reviewDocument();

        foreach ([
            'payment recording is implemented and closed.',
            'receipt generation and receipt access are implemented and closed.',
            'club finance reports and balance sheet read/display lane is implemented and closed.',
            'payment history backend and frontend lane is implemented and closed.',
            'mutation-heavy follow-ups remain intentionally deferred.',
            'backend remains the source of truth.',
        ] as $stabilityClaim) {
            $this->assertStringContainsString($stabilityClaim, $document);
        }
    }

    public function test_3br_evaluates_next_roadmap_candidates_without_implementation(): void
    {
        $document = $this->reviewDocument();

        foreach ([
            'Member data import / Excel onboarding scope gate',
            'Finance export / Excel export scope gate',
            'Reminder workflow scope gate',
            'Payment cancellation/reversal scope gate',
            'Allocation workflow scope gate',
            'Deployment/SaaS readiness gate',
            'Non-finance module continuation',
        ] as $candidate) {
            $this->assertStringContainsString($candidate, $document);
        }
    }

    public function test_3br_records_member_data_import_excel_onboarding_as_next_lane(): void
    {
        $document = $this->reviewDocument();

        foreach ([
            'Recommended next lane:',
            'Member data import / Excel onboarding - scope gate first',
            'Existing clubs need practical migration from old Excel/AppSheet records before pilot onboarding.',
            'This should start as a docs/tests-only scope gate, not implementation.',
            'The next lane should prefer data onboarding clarity before deployment or mutation-heavy finance workflows.',
        ] as $decisionText) {
            $this->assertStringContainsString($decisionText, $document);
        }
    }

    public function test_3br_selects_exact_3bs_next_milestone_title(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('The expected next milestone is:', $document);
        $this->assertStringContainsString('Milestone 3BS - Member Data Import / Excel Onboarding Scope Gate', $document);
        $this->assertStringContainsString('3BS should be docs/tests-only unless the human explicitly authorizes a different scope.', $document);
        $this->assertStringContainsString('3BS must not implement Excel import.', $document);
        $this->assertStringNotContainsString('Milestone 3BS - Generic Next Scope Gate', $document);
    }

    public function test_3bs_boundary_recommendation_blocks_unscoped_import_and_finance_mutation(): void
    {
        $document = $this->reviewDocument();

        foreach ([
            'what member data may be imported later.',
            'source file assumptions.',
            'required columns.',
            'validation and duplicate handling questions.',
            'privacy and consent boundaries.',
            'role authorization.',
            'rollback and dry-run expectations.',
            'blocked payment, receipt, ledger, and finance import behavior unless separately authorized.',
            'no client-specific hardcoding.',
        ] as $boundaryText) {
            $this->assertStringContainsString($boundaryText, $document);
        }
    }

    public function test_no_blocked_payment_history_or_excel_runtime_files_were_added_by_3br(): void
    {
        foreach ([
            app_path('Http/Requests/Finance/PaymentHistoryRequest.php'),
            app_path('Services/Finance/PaymentHistoryService.php'),
            app_path('Models/PaymentHistory.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/PaymentAllocationService.php'),
            app_path('Services/Finance/PaymentReminderService.php'),
            app_path('Services/Finance/PaymentHistoryExportService.php'),
            app_path('Http/Controllers/Api/V1/MemberImportController.php'),
            app_path('Services/MemberImportService.php'),
            app_path('Imports/MemberImport.php'),
            database_path('migrations/create_member_imports_table.php'),
            base_path('../app/member-import'),
            base_path('../app/import'),
            base_path('../components/member-import'),
            base_path('../lib/member-import.ts'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3br_closure_decision_names_exact_3bs_scope_gate(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('Milestone 3BR closes the Payment History lane closure review.', $document);
        $this->assertStringContainsString('The next milestone is selected exactly as:', $document);
        $this->assertStringContainsString('Milestone 3BS - Member Data Import / Excel Onboarding Scope Gate', $document);
        $this->assertStringContainsString('No next milestone is started by 3BR.', $document);
    }

    private function reviewDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function frontendPage(): string
    {
        $path = base_path('../app/page.tsx');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function paymentHistoryBlock(string $page): string
    {
        $start = strpos($page, 'function PaymentHistory({ role }: { role: Role })');
        $end = strpos($page, 'function ReceiptPreview', $start === false ? 0 : $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($page, $start, $end - $start);
    }
}
