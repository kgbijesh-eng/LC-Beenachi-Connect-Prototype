<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3blPaymentHistoryReadOnlyImplementationAuthorizationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bl_payment_history_read_only_implementation_authorization.md';

    public function test_3bl_document_exists_and_is_docs_tests_only_authorization(): void
    {
        $document = $this->authorizationDocument();

        $this->assertStringContainsString('Milestone 3BL - Payment History Read-Only Implementation Authorization', $document);
        $this->assertStringContainsString('docs/tests-only implementation authorization milestone', $document);
        $this->assertStringContainsString('It authorizes a future narrow implementation in Milestone 3BM.', $document);
        $this->assertStringContainsString('It does not implement payment history APIs.', $document);
        $this->assertStringContainsString('It does not implement frontend payment history display.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
    }

    public function test_3bl_records_base_branch_and_allowed_files(): void
    {
        $document = $this->authorizationDocument();

        $this->assertStringContainsString('Milestone 3BK - Payment History Read-Only Scope Gate', $document);
        $this->assertStringContainsString('milestone-3bk-payment-history-read-only-scope-gate', $document);
        $this->assertStringContainsString('milestone-3bl-payment-history-read-only-implementation-authorization', $document);
        $this->assertStringContainsString('backend/docs/milestone_3bl_payment_history_read_only_implementation_authorization.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3blPaymentHistoryReadOnlyImplementationAuthorizationTest.php', $document);
    }

    public function test_3bl_blocks_runtime_frontend_backend_api_and_workflow_changes(): void
    {
        $document = $this->authorizationDocument();

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

    public function test_3bl_authorizes_only_future_3bm_read_only_payment_history_api(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3BM may implement a narrow read-only payment history API only.',
            'protected GET-only payment history API routes',
            'Routes are scoped to a club.',
            'Routes are protected by existing authentication and club authorization patterns.',
            'Normal Member can read only their own payment history.',
            'President/Admin, Secretary, and Treasurer can read selected-member club-scoped payment history.',
            'Cross-club access fails closed.',
            'Backend remains the source of truth.',
            'Responses expose only safe metadata fields.',
            'Any list endpoint is paginated and/or filtered.',
        ] as $authorizationText) {
            $this->assertStringContainsString($authorizationText, $document);
        }
    }

    public function test_3bl_names_candidate_future_routes_without_creating_them(): void
    {
        $document = $this->authorizationDocument();

        $this->assertStringContainsString('GET /api/v1/clubs/{club}/members/me/payments/history', $document);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/members/{member}/payments/history', $document);
        $this->assertStringContainsString('3BL does not create these routes.', $document);
        $this->assertStringContainsString('3BL does not authorize an all-member club payment history endpoint for 3BM.', $document);

        $paymentHistoryRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'payments/history')
                || str_contains($route->uri(), 'payment-history')
                || str_contains($route->uri(), 'payment/history'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/me/payments/history',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/history',
        ], $paymentHistoryRoutes);
    }

    public function test_3bl_defines_future_role_visibility_boundary(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'May read only payment history tied to their own member identity.',
            "Must not read other members' payment history.",
            'Must not read all-member payment history.',
            'Must not read club-wide payment history.',
            'May read selected-member payment history inside their authorized club.',
            'Must remain blocked from cross-club payment records.',
            'Must not gain broad admin-only finance access silently.',
            'Backend authorization remains the source of truth for all role and club boundaries.',
        ] as $roleText) {
            $this->assertStringContainsString($roleText, $document);
        }
    }

    public function test_3bl_defines_safe_future_response_fields_and_risky_fields(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'payment public reference or payment ID only if safe enough',
            'payment date',
            'amount',
            'payment mode',
            'status',
            'member name or basic member reference for officer selected-member view only',
            'receipt number or receipt reference if already generated',
            'receipt availability',
            'recorded by display name or role if already safe',
            'created or generated timestamp if safe',
            'raw internal IDs',
            'free-form remarks',
            'internal notes',
            'cancellation fields',
            'reversal fields',
            'allocation details',
            'audit internals',
            '3BM must not invent balances, totals, accounting labels, or payment classifications.',
        ] as $fieldText) {
            $this->assertStringContainsString($fieldText, $document);
        }
    }

    public function test_3bl_defines_future_api_safety_states_without_frontend_implementation(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'empty payment history',
            'unauthenticated access',
            'forbidden access',
            'missing active club context',
            'member not found or not in club',
            'cross-club access',
            'missing or null optional metadata',
            'pagination metadata if a list is returned',
            'Frontend loading, empty, error, and forbidden states may be planned for future frontend work',
            '3BM must not add frontend display unless separately authorized.',
        ] as $stateText) {
            $this->assertStringContainsString($stateText, $document);
        }
    }

    public function test_3bl_preserves_backend_source_of_truth(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'The backend must remain the source of truth',
            'payment records',
            'payment status',
            'payment dates',
            'payment modes',
            'receipt availability',
            'role visibility',
            'club visibility',
            'cross-club blocking',
            'Future frontend code may display returned values but must not calculate official finance totals or balances.',
        ] as $sourceText) {
            $this->assertStringContainsString($sourceText, $document);
        }
    }

    public function test_3bl_blocks_mutation_export_reminder_audit_deployment_and_client_specific_behavior(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'payment create behavior',
            'payment update behavior',
            'payment delete behavior',
            'payment cancellation',
            'payment reversal',
            'payment correction',
            'payment allocation',
            'payment reallocation',
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

    public function test_3bl_preserves_shared_codebase_and_blocks_client_specific_hardcoding(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'one shared source codebase',
            'Lions Club Beenachi',
            'Rotary Central Bathery',
            'any future pilot club',
            'runtime club, member, role, and payment data protected by backend authorization',
        ] as $sharedCodebaseText) {
            $this->assertStringContainsString($sharedCodebaseText, $document);
        }
    }

    public function test_3bl_allows_only_later_authorized_3bm_payment_history_runtime_files(): void
    {
        $this->assertFileExists(app_path('Http/Controllers/Api/V1/Finance/PaymentHistoryController.php'));
        $this->assertFileExists(app_path('Http/Resources/Finance/PaymentHistoryResource.php'));
        $this->assertFileDoesNotExist(app_path('Http/Requests/Finance/PaymentHistoryRequest.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/PaymentHistoryService.php'));
        $this->assertFileDoesNotExist(app_path('Models/PaymentHistory.php'));
        $this->assertFileDoesNotExist(app_path('Http/Middleware/EnsurePaymentHistoryAccess.php'));
        $this->assertFileDoesNotExist(database_path('migrations/create_payment_histories_table.php'));
        $this->assertFileDoesNotExist(database_path('seeders/PaymentHistorySeeder.php'));
        $this->assertFileDoesNotExist(base_path('../app/page.tsx.3bl'));
    }

    public function test_3bl_defines_expected_validation_for_3bm(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'GET-only payment history routes are present only if authorized by 3BL.',
            'Normal Member can read only own payment history.',
            'President/Admin, Secretary, and Treasurer can read selected-member club-scoped payment history.',
            'Cross-club access fails closed.',
            'Unauthenticated access fails closed.',
            'Backend responses expose only safe fields.',
            'Empty history responses are safe.',
            'Pagination or filtering is present for list responses.',
            'No all-member endpoint is added unless separately authorized.',
            'No frontend display is added unless separately authorized.',
            'No mutation workflows are added.',
            'No export, PDF, Excel, reminder, cancellation, reversal, allocation, deployment, SaaS, or client-specific behavior is added.',
        ] as $validationText) {
            $this->assertStringContainsString($validationText, $document);
        }
    }

    public function test_3bl_selects_3bm_as_next_milestone(): void
    {
        $document = $this->authorizationDocument();

        $this->assertStringContainsString('Milestone 3BM - Payment History Read-Only Narrow Implementation', $document);
        $this->assertStringContainsString('3BM may implement only the narrow read-only API authorized by this document.', $document);
    }

    private function authorizationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
