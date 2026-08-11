<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3boPaymentHistoryFrontendScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bo_payment_history_frontend_scope_gate.md';

    public function test_3bo_document_exists_and_is_docs_tests_only_scope_gate(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3BO - Payment History Frontend Scope Gate', $document);
        $this->assertStringContainsString('docs/tests-only scope gate', $document);
        $this->assertStringContainsString('It does not implement frontend payment history display.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
    }

    public function test_3bo_records_base_branch_and_allowed_files(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3BN - Payment History Read-Only Validation and Closure', $document);
        $this->assertStringContainsString('milestone-3bn-payment-history-read-only-validation-and-closure', $document);
        $this->assertStringContainsString('milestone-3bo-payment-history-frontend-scope-gate', $document);
        $this->assertStringContainsString('backend/docs/milestone_3bo_payment_history_frontend_scope_gate.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3boPaymentHistoryFrontendScopeGateTest.php', $document);
    }

    public function test_3bo_blocks_runtime_frontend_backend_api_and_workflow_changes(): void
    {
        $document = $this->scopeGateDocument();

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
            'payment history implementation files',
            'payment recording files',
            'receipt files',
            'PDF files',
            'export files',
            'Excel files',
            'reminder files',
            'cancellation workflow files',
            'reversal workflow files',
            'allocation workflow files',
            'deployment files',
            'SaaS files',
            'client-specific hardcoding files',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $document);
        }
    }

    public function test_3bo_limits_future_frontend_to_existing_3bm_get_routes(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'GET /api/v1/clubs/{club}/members/me/payments/history',
            'GET /api/v1/clubs/{club}/members/{member}/payments/history',
            'must not create or require new backend API routes',
            'must not change the API response contract',
            'backend as the source of truth',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }

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

    public function test_3bo_defines_normal_member_own_history_only_boundary(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'May see only their own payment history.',
            'Must use only the existing own-history route.',
            'Must not see selected-member payment history for another member.',
            'Must not see all-member payment history.',
            'Must not load unauthorized payment history into hidden frontend state.',
        ] as $roleRule) {
            $this->assertStringContainsString($roleRule, $document);
        }
    }

    public function test_3bo_defines_officer_selected_member_only_boundary(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'President/Admin, Secretary, and Treasurer',
            'May view selected-member payment history only through the existing selected-member API.',
            'Must remain scoped to the active club authorized by the backend.',
            'Must not receive cross-club payment history.',
            'Must not receive all-member payment history in 3BO or the immediate future frontend implementation.',
            'Backend authorization remains the source of truth for every frontend visibility decision.',
        ] as $roleRule) {
            $this->assertStringContainsString($roleRule, $document);
        }
    }

    public function test_3bo_blocks_all_member_screen_and_route(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'No all-member payment history screen is authorized by 3BO.',
            'No all-member payment history route is authorized by 3BO.',
            'Any future all-member payment history experience must be separately scoped',
            'tight pagination, filtering, and authorization review',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }

        $routeSignatures = collect(Route::getRoutes())
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertNotContains('GET|HEAD api/v1/clubs/{club}/payments/history', $routeSignatures);
    }

    public function test_3bo_defines_safe_frontend_metadata_without_inventing_fields(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'payment id or safe payment reference as returned by the backend',
            'payment date',
            'amount',
            'payment mode',
            'status',
            'member id, code, and name where already returned for the authorized view',
            'receipt availability',
            'receipt number and receipt status where already returned',
            'recorded-by id and name where already returned',
            'created timestamp where already returned',
            'absent, null, forbidden, or empty',
            'safe unavailable or empty state',
            'must not invent fields, balances, totals, classifications, labels, or accounting calculations',
        ] as $safeFieldText) {
            $this->assertStringContainsString($safeFieldText, $document);
        }
    }

    public function test_3bo_blocks_notes_internals_and_client_specific_frontend_content(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'raw notes',
            'internal remarks',
            'cancellation internals',
            'reversal internals',
            'allocation internals',
            'ledger internals',
            'receipt notes',
            'audit internals',
            'invented balances',
            'invented totals',
            'client-specific labels or logic',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3bo_requires_safe_future_frontend_states(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'loading',
            'empty or no payment history',
            'missing active club context',
            'unauthenticated or 401',
            'forbidden or 403',
            'general API error',
            'successful own payment history display',
            'successful selected-member officer payment history display',
            'missing or null backend fields',
            'must not expose raw technical errors to users',
        ] as $safeState) {
            $this->assertStringContainsString($safeState, $document);
        }
    }

    public function test_3bo_blocks_mutation_export_receipt_reminder_deployment_and_saas_ui(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'frontend implementation',
            'app/page.tsx changes',
            'backend route changes',
            'controller changes',
            'service changes',
            'resource changes',
            'request class changes',
            'model changes',
            'middleware changes',
            'migration changes',
            'seeder changes',
            'payment create UI',
            'payment edit UI',
            'payment delete UI',
            'cancellation UI',
            'reversal UI',
            'correction UI',
            'allocation UI',
            'reallocation UI',
            'receipt generation UI',
            'receipt mutation UI',
            'export UI',
            'PDF UI',
            'Excel UI',
            'reminder UI',
            'notification UI',
            'deployment implementation',
            'SaaS implementation',
            'client-specific hardcoding',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3bo_does_not_add_frontend_payment_history_runtime_files(): void
    {
        foreach ([
            base_path('../app/payment-history'),
            base_path('../app/finance/payment-history'),
            base_path('../app/members/[member]/payments/history'),
            base_path('../components/payment-history'),
            base_path('../lib/payment-history.ts'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3bo_selects_3bp_frontend_narrow_implementation_candidate_without_starting_it(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3BP - Payment History Frontend Narrow Implementation', $document);
        $this->assertStringContainsString('3BP must not start from 3BO unless explicitly approved.', $document);
        $this->assertStringContainsString('3BP should implement frontend display only if it stays inside the 3BO frontend scope gate.', $document);
    }

    private function scopeGateDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
