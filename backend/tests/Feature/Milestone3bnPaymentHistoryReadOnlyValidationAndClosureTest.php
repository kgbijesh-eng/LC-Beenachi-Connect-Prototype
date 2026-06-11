<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3bnPaymentHistoryReadOnlyValidationAndClosureTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bn_payment_history_read_only_validation_and_closure.md';

    public function test_3bn_document_exists_and_is_docs_tests_only_closure(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3BN - Payment History Read-Only Validation and Closure', $document);
        $this->assertStringContainsString('docs/tests-only validation and closure milestone', $document);
        $this->assertStringContainsString('It validates and closes Milestone 3BM.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
    }

    public function test_3bn_allows_only_closure_doc_and_validation_test(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('backend/docs/milestone_3bn_payment_history_read_only_validation_and_closure.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bnPaymentHistoryReadOnlyValidationAndClosureTest.php', $document);

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
            'client-specific hardcoding files',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3bm_implementation_artifacts_remain_present_for_closure(): void
    {
        foreach ([
            base_path('docs/milestone_3bm_payment_history_read_only_narrow_implementation.md'),
            base_path('tests/Feature/Milestone3bmPaymentHistoryReadOnlyNarrowImplementationTest.php'),
            app_path('Http/Controllers/Api/V1/Finance/PaymentHistoryController.php'),
            app_path('Http/Resources/Finance/PaymentHistoryResource.php'),
        ] as $requiredPath) {
            $this->assertFileExists($requiredPath);
        }
    }

    public function test_payment_history_routes_remain_exact_get_only_routes(): void
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

    public function test_3bm_did_not_add_all_member_or_mutation_payment_history_routes(): void
    {
        $routeSignatures = collect(Route::getRoutes())
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        foreach ([
            'GET|HEAD api/v1/clubs/{club}/payments/history',
            'POST api/v1/clubs/{club}/members/{member}/payments/history',
            'PATCH api/v1/clubs/{club}/members/{member}/payments/history',
            'PUT api/v1/clubs/{club}/members/{member}/payments/history',
            'DELETE api/v1/clubs/{club}/members/{member}/payments/history',
            'POST api/v1/clubs/{club}/members/me/payments/history',
            'PATCH api/v1/clubs/{club}/members/me/payments/history',
            'PUT api/v1/clubs/{club}/members/me/payments/history',
            'DELETE api/v1/clubs/{club}/members/me/payments/history',
        ] as $blockedRoute) {
            $this->assertNotContains($blockedRoute, $routeSignatures);
        }
    }

    public function test_payment_history_controller_preserves_role_authorization_and_cross_club_guards(): void
    {
        $controller = $this->paymentHistoryController();

        foreach ([
            'public function own(Request $request, Club $club): JsonResponse',
            '->where(\'club_id\', $club->id)',
            '->where(\'user_id\', $request->user()->id)',
            'Member profile not found for authenticated user in this club.',
            'public function show(Request $request, Club $club, Member $member): JsonResponse',
            'Member does not belong to this club.',
            'This action is unauthorized.',
            "'president-admin'",
            "'secretary'",
            "'treasurer'",
            'private function activeRoleSlug(User $user, Club $club): ?string',
            '->where(\'status\', \'active\')',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $controller);
        }

        $this->assertStringNotContainsString("'member'", $this->selectedMemberRoleSlugBlock($controller));
        $this->assertStringNotContainsString("'admin'", $this->selectedMemberRoleSlugBlock($controller));
    }

    public function test_payment_history_controller_uses_capped_pagination_without_filters_or_all_member_query(): void
    {
        $controller = $this->paymentHistoryController();

        foreach ([
            'private const DEFAULT_PER_PAGE = 10;',
            'private const MAX_PER_PAGE = 25;',
            'request->query(\'per_page\'',
            'return min($requested, self::MAX_PER_PAGE);',
            '->where(\'member_id\', $member->id)',
            '->paginate($this->perPage($request))',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $controller);
        }

        $this->assertStringNotContainsString("request->query('status'", $controller);
        $this->assertStringNotContainsString("request->query('payment_mode'", $controller);
        $this->assertStringNotContainsString("request->query('from'", $controller);
        $this->assertStringNotContainsString("request->query('to'", $controller);
    }

    public function test_payment_history_resource_exposes_only_safe_metadata(): void
    {
        $resource = $this->paymentHistoryResource();

        foreach ([
            "'payment_id' =>",
            "'payment_date' =>",
            "'amount' =>",
            "'payment_mode' =>",
            "'status' =>",
            "'member' =>",
            "'receipt' =>",
            "'available' =>",
            "'receipt_number' =>",
            "'recorded_by' =>",
            "'created_at' =>",
        ] as $safeField) {
            $this->assertStringContainsString($safeField, $resource);
        }

        foreach ([
            "'notes' =>",
            'cancellation_reason',
            'cancelled_at',
            'cancelled_by',
            'reversal',
            'allocation',
            'ledger_entry_id',
            'receipt_date',
            'receipt notes',
            'audit',
            'balance',
            'total',
        ] as $blockedField) {
            $this->assertStringNotContainsString($blockedField, $resource);
        }
    }

    public function test_3bm_focused_test_covers_authorization_pagination_read_only_and_safe_fields(): void
    {
        $test = file_get_contents(base_path('tests/Feature/Milestone3bmPaymentHistoryReadOnlyNarrowImplementationTest.php'));

        foreach ([
            'test_normal_member_can_read_only_own_payment_history',
            'test_normal_member_cannot_read_selected_member_payment_history',
            'test_president_secretary_and_treasurer_can_read_selected_member_payment_history',
            'test_cross_club_selected_member_payment_history_fails_closed',
            'test_unauthorized_role_fails_closed_for_selected_member_payment_history',
            'test_unauthenticated_user_cannot_read_payment_history',
            'test_payment_history_uses_capped_pagination_without_filters',
            'test_payment_history_does_not_mutate_payment_or_receipt_records',
            'assertJsonMissingPath',
            'notes',
            'cancellation_reason',
            'ledger_entry_id',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $test);
        }
    }

    public function test_compatibility_guard_updates_are_limited_to_3bm_routes_and_runtime_files(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'newly authorized 3BM GET payment-history routes and runtime files',
            'all-member payment history',
            'payment mutation',
            'cancellation',
            'reversal',
            'allocation',
            'export',
            'PDF',
            'Excel',
            'reminders',
            'frontend display',
            'deployment',
            'SaaS',
            'client-specific behavior',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }

        foreach ([
            'tests/Feature/Milestone3bMemberAccountLedgerDatabaseFoundationTest.php',
            'tests/Feature/Milestone3cMemberLedgerBalanceCalculationFoundationTest.php',
            'tests/Feature/Milestone3dMemberLedgerBalanceReadApiScopeGateTest.php',
            'tests/Feature/Milestone3eMemberLedgerBalanceReadApiNarrowImplementationTest.php',
            'tests/Feature/Milestone3fMemberLedgerBalanceReadApiValidationAndClosureTest.php',
            'tests/Feature/Milestone3gPaymentRecordingScopeGateTest.php',
            'tests/Feature/Milestone3hPaymentRecordingDatabaseFoundationAuthorizationTest.php',
            'tests/Feature/Milestone3iPaymentRecordingDatabaseFoundationImplementationTest.php',
            'tests/Feature/Milestone3jPaymentRecordingDatabaseFoundationValidationAndClosureTest.php',
            'tests/Feature/Milestone3kPaymentRecordingServiceScopeGateTest.php',
            'tests/Feature/Milestone3lPaymentRecordingServiceImplementationAuthorizationTest.php',
            'tests/Feature/Milestone3oPaymentRecordingApiScopeGateTest.php',
            'tests/Feature/Milestone3pPaymentRecordingApiImplementationAuthorizationTest.php',
            'tests/Feature/Milestone3qPaymentRecordingApiNarrowImplementationTest.php',
            'tests/Feature/Milestone3rPaymentRecordingApiValidationAndClosureTest.php',
            'tests/Feature/Milestone3bkPaymentHistoryReadOnlyScopeGateTest.php',
            'tests/Feature/Milestone3blPaymentHistoryReadOnlyImplementationAuthorizationTest.php',
        ] as $compatibilityFile) {
            $this->assertFileExists(base_path($compatibilityFile));
        }
    }

    public function test_no_blocked_payment_history_support_files_or_frontend_files_exist(): void
    {
        foreach ([
            app_path('Http/Requests/Finance/PaymentHistoryRequest.php'),
            app_path('Services/Finance/PaymentHistoryService.php'),
            app_path('Models/PaymentHistory.php'),
            app_path('Http/Middleware/EnsurePaymentHistoryAccess.php'),
            database_path('migrations/create_payment_histories_table.php'),
            database_path('seeders/PaymentHistorySeeder.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/PaymentAllocationService.php'),
            app_path('Services/Finance/PaymentReminderService.php'),
            app_path('Services/Finance/PaymentHistoryExportService.php'),
            base_path('../app/page.tsx.3bn'),
            base_path('../app/payment-history'),
            base_path('../app/finance/payment-history'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3bn_closes_payment_history_backend_api_lane(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3BN closes the Payment History Read-Only backend API lane.', $document);
        $this->assertStringContainsString('No next milestone is started by 3BN.', $document);
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function paymentHistoryController(): string
    {
        $path = app_path('Http/Controllers/Api/V1/Finance/PaymentHistoryController.php');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function paymentHistoryResource(): string
    {
        $path = app_path('Http/Resources/Finance/PaymentHistoryResource.php');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function selectedMemberRoleSlugBlock(string $controller): string
    {
        $start = strpos($controller, 'private const SELECTED_MEMBER_PAYMENT_HISTORY_ROLE_SLUGS');
        $end = strpos($controller, 'private const DEFAULT_PER_PAGE', $start === false ? 0 : $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($controller, $start, $end - $start);
    }
}
