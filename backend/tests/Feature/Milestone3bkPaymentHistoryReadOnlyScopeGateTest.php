<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3bkPaymentHistoryReadOnlyScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bk_payment_history_read_only_scope_gate.md';

    public function test_3bk_document_exists_and_is_docs_tests_only_scope_gate(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3BK - Payment History Read-Only Scope Gate', $document);
        $this->assertStringContainsString('docs/tests-only scope gate', $document);
        $this->assertStringContainsString('It does not implement payment history APIs.', $document);
        $this->assertStringContainsString('It does not implement frontend payment history display.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
    }

    public function test_3bk_records_base_branch_and_allowed_files(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3BJ - Finance Module Post Reports Frontend Closure Review and Next Scope Decision', $document);
        $this->assertStringContainsString('milestone-3bj-finance-module-post-reports-frontend-closure-review-next-scope-decision', $document);
        $this->assertStringContainsString('milestone-3bk-payment-history-read-only-scope-gate', $document);
        $this->assertStringContainsString('backend/docs/milestone_3bk_payment_history_read_only_scope_gate.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bkPaymentHistoryReadOnlyScopeGateTest.php', $document);
    }

    public function test_3bk_blocks_runtime_frontend_backend_api_and_workflow_changes(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'app/page.tsx',
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
            'payment history API implementation',
            'frontend payment history display',
            'export files',
            'PDF files',
            'Excel files',
            'reminder files',
            'payment mutation files',
            'receipt mutation files',
            'cancellation workflow files',
            'reversal workflow files',
            'allocation workflow files',
            'deployment files',
            'SaaS files',
            'client-specific hardcoding',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $document);
        }
    }

    public function test_3bk_decides_future_read_boundary_clearly(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Normal Member may see only their own payment history.',
            'President/Admin, Secretary, and Treasurer may see club-scoped read-only payment history.',
            'Future selected-member payment history view may be authorized first.',
            'Future all-member payment history list may be authorized only if tightly filtered and paginated in a later implementation milestone.',
            'No broader admin-only finance access may be introduced silently.',
            'Cross-club access must fail closed.',
            'Backend must remain the source of truth.',
        ] as $decision) {
            $this->assertStringContainsString($decision, $document);
        }
    }

    public function test_3bk_defines_normal_member_and_officer_role_visibility(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'May see only payment records tied to their own member identity.',
            "Must not see other members' payment records.",
            'Must not see all-member payment history.',
            'Must not see club-wide payment history.',
            'May see club-scoped read-only payment history where backend authorization allows.',
            'May use a selected-member payment history view first in a future implementation milestone.',
            'May use an all-member list only if a later implementation milestone defines tight filters and pagination.',
            'Must remain blocked from cross-club payment records.',
        ] as $roleRule) {
            $this->assertStringContainsString($roleRule, $document);
        }
    }

    public function test_3bk_allows_only_viewing_existing_payment_records(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Future payment history may read existing payment records only.', $document);

        foreach ([
            'create payments',
            'update payments',
            'delete payments',
            'cancel payments',
            'reverse payments',
            'correct payments',
            'allocate payments',
            'reallocate payments',
            'generate receipts',
            'mutate receipts',
            'export records',
            'send reminders or notifications',
            'calculate balances',
            'invent totals',
            'invent accounting classifications',
        ] as $blockedBehavior) {
            $this->assertStringContainsString($blockedBehavior, $document);
        }
    }

    public function test_3bk_lists_safe_future_fields_to_evaluate_without_exposing_them_now(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'payment ID only if safe and public-facing enough, otherwise avoid exposing raw internal ID',
            'payment date',
            'amount',
            'payment mode',
            'status',
            'member name or basic member reference for officer view only',
            'receipt number or reference if already generated',
            'receipt availability',
            'recorded by display name or role if already safe',
            'created or generated timestamp if safe',
            'remarks or notes only if explicitly authorized and safe',
            '3BK does not itself expose these fields.',
            '3BK does not decide that raw internal IDs or notes are safe by default.',
        ] as $fieldText) {
            $this->assertStringContainsString($fieldText, $document);
        }
    }

    public function test_3bk_defines_safe_future_states(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'loading',
            'empty or no payment history',
            'missing active club context',
            'unauthenticated or 401',
            'forbidden or 403',
            'API error',
            'successful member payment history display',
            'successful selected-member officer payment history display',
            'safe handling for missing or null fields',
        ] as $safeState) {
            $this->assertStringContainsString($safeState, $document);
        }
    }

    public function test_3bk_context_models_and_apis_are_review_only_not_changed(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Payment',
            'Receipt',
            'Member',
            'Club',
            'ClubMembership',
            'Role',
            'User',
            'MemberLedgerEntry',
            'auth/me',
            'club/me',
            'member summary',
            'payment recording',
            'receipt view',
            'receipt PDF',
            'club finance reports',
            'balance sheet',
            'may be reviewed for context only and must not be changed in 3BK',
        ] as $contextSurface) {
            $this->assertStringContainsString($contextSurface, $document);
        }
    }

    public function test_3bk_allows_only_later_authorized_3bm_payment_history_routes_and_runtime_files(): void
    {
        $paymentHistoryRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'payment-history')
                || str_contains($route->uri(), 'payments/history')
                || str_contains($route->uri(), 'payment/history'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/me/payments/history',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/history',
        ], $paymentHistoryRoutes);

        $this->assertFileExists(app_path('Http/Controllers/Api/V1/Finance/PaymentHistoryController.php'));
        $this->assertFileExists(app_path('Http/Resources/Finance/PaymentHistoryResource.php'));
        $this->assertFileDoesNotExist(app_path('Http/Requests/Finance/PaymentHistoryRequest.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/PaymentHistoryService.php'));
        $this->assertFileDoesNotExist(app_path('Models/PaymentHistory.php'));
        $this->assertFileDoesNotExist(database_path('migrations/create_payment_histories_table.php'));
        $this->assertFileDoesNotExist(database_path('seeders/PaymentHistorySeeder.php'));
        $this->assertFileDoesNotExist(base_path('../app/page.tsx.3bk'));
    }

    public function test_3bk_blocks_future_mutation_export_reminder_audit_deployment_and_client_specific_behavior(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'create payment behavior',
            'update payment behavior',
            'delete payment behavior',
            'cancellation',
            'reversal',
            'correction',
            'allocation',
            'reallocation',
            'receipt generation',
            'receipt mutation',
            'export',
            'PDF',
            'Excel',
            'reminders',
            'notifications',
            'audit dashboard',
            'deployment implementation',
            'SaaS implementation',
            'frontend accounting calculations',
            'invented balances',
            'invented totals',
            'client-specific behavior',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3bk_preserves_shared_codebase_and_blocks_client_specific_hardcoding(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'one shared codebase strategy',
            'Lions Club Beenachi',
            'Rotary Central Bathery',
            'any future pilot club',
            'Allowed future behavior must use runtime club and member data returned by backend authorization and data APIs.',
        ] as $sharedCodebaseText) {
            $this->assertStringContainsString($sharedCodebaseText, $document);
        }
    }

    public function test_3bk_defines_expected_future_implementation_authorization_checks(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'selected-member payment history scope',
            'member-own payment history scope',
            'role visibility boundary',
            'cross-club fail-closed behavior',
            'safe field list',
            'blocked mutation workflows',
            'backend source-of-truth rule',
            'empty/loading/error states',
            'pagination/filter requirement before any all-member list',
            'no frontend display until separately authorized',
        ] as $futureCheck) {
            $this->assertStringContainsString($futureCheck, $document);
        }
    }

    public function test_3bk_selects_3bl_as_next_milestone(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3BL - Payment History Read-Only Implementation Authorization', $document);
        $this->assertStringContainsString('3BL should remain docs/tests-only unless the human explicitly authorizes a different scope.', $document);
        $this->assertStringContainsString('3BL may authorize a future narrow implementation only after it confirms 3BK scope boundaries.', $document);
    }

    private function scopeGateDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
