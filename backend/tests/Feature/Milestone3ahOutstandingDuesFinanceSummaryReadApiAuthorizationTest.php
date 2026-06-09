<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3ahOutstandingDuesFinanceSummaryReadApiAuthorizationTest extends TestCase
{
    public function test_milestone_3ah_authorization_document_exists_and_records_decision(): void
    {
        $path = base_path('docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md');

        $this->assertFileExists($path);

        $contents = File::get($path);

        $this->assertStringContainsString('Milestone 3AH — Outstanding Dues and Member Finance Summary Read API Authorization', $contents);
        $this->assertStringContainsString('docs/tests-only', $contents);
        $this->assertStringContainsString('OUTSTANDING_DUES_FINANCE_SUMMARY_READ_API_AUTHORIZED_FOR_FUTURE_IMPLEMENTATION', $contents);
        $this->assertStringContainsString('Milestone 3AI — Outstanding Dues and Member Finance Summary Read API Narrow Implementation', $contents);
        $this->assertStringContainsString('No runtime behavior changes are allowed in this milestone', $contents);
    }

    public function test_milestone_3ah_authorizes_exact_future_get_route_boundary(): void
    {
        $contents = File::get(base_path('docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md'));

        $this->assertStringContainsString('GET /api/v1/clubs/{club}/finance/member-summary', $contents);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/members/{member}/finance-summary', $contents);
        $this->assertStringContainsString('/finance/member-summary returns the authenticated normal member\'s own finance summary', $contents);
        $this->assertStringContainsString('/members/{member}/finance-summary returns a selected member finance summary for authorized same-club PST users', $contents);
        $this->assertStringContainsString('No POST, PATCH, PUT, or DELETE route is authorized', $contents);
    }

    public function test_milestone_3ah_defines_future_controller_service_request_and_resource_boundaries(): void
    {
        $contents = File::get(base_path('docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md'));

        $this->assertStringContainsString('MemberFinanceSummaryController', $contents);
        $this->assertStringContainsString('MemberFinanceSummaryService', $contents);
        $this->assertStringContainsString('MemberFinanceSummaryResource', $contents);
        $this->assertStringContainsString('delegate summary calculation to a dedicated service', $contents);
        $this->assertStringContainsString('read existing member ledger entries', $contents);
        $this->assertStringContainsString('read existing payments', $contents);
        $this->assertStringContainsString('read existing receipts', $contents);
        $this->assertStringContainsString('return read-only summary data', $contents);
    }

    public function test_milestone_3ah_defines_allowed_query_parameters_and_blocks_mutating_query_behavior(): void
    {
        $contents = File::get(base_path('docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md'));

        foreach (['include_pending_items', 'include_last_payment', 'include_receipt_status'] as $parameter) {
            $this->assertStringContainsString($parameter, $contents);
        }

        foreach ([
            'changing member_id for normal member own-summary access',
            'creating records',
            'updating records',
            'deleting records',
            'triggering reminders',
            'generating reports',
            'exporting Excel',
            'generating PDFs',
        ] as $behavior) {
            $this->assertStringContainsString($behavior, $contents);
        }
    }

    public function test_milestone_3ah_defines_allowed_response_fields_and_pending_item_fields(): void
    {
        $contents = File::get(base_path('docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md'));

        foreach ([
            'member_id',
            'member_code',
            'member_name',
            'current_dues_amount',
            'current_dues_label',
            'last_payment_amount',
            'last_payment_date',
            'pending_items',
            'receipt_available',
            'payment_status',
            'generated_at',
            'source_type',
            'source_label',
            'amount',
            'due_date',
            'status',
        ] as $field) {
            $this->assertStringContainsString($field, $contents);
        }
    }

    public function test_milestone_3ah_preserves_member_friendly_wording_and_blocks_accounting_wording(): void
    {
        $contents = File::get(base_path('docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md'));

        foreach (['Current Dues', 'Pending Items', 'Last Payment', 'Receipt Available', 'Paid', 'Partly Paid', 'Pending', 'Advance'] as $label) {
            $this->assertStringContainsString($label, $contents);
        }

        foreach (['debit', 'credit', 'ledger posting', 'receivable', 'journal', 'accounting entry', 'reversal', 'audit trail'] as $word) {
            $this->assertStringContainsString($word, $contents);
        }
    }

    public function test_milestone_3ah_defines_fail_closed_access_rules(): void
    {
        $contents = File::get(base_path('docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md'));

        foreach ([
            'unauthenticated access fails closed',
            'normal member can read only own finance summary',
            'normal member cannot read another member finance summary',
            'Treasurer can read same-club member finance summaries',
            'Secretary can read same-club member finance summaries',
            'President/Admin can read same-club member finance summaries',
            'cross-club member finance access fails closed',
            'user without active club membership fails closed',
            'finance summary API remains read-only',
        ] as $rule) {
            $this->assertStringContainsString($rule, $contents);
        }
    }

    public function test_milestone_3ah_does_not_register_future_finance_summary_routes_yet(): void
    {
        $routeUris = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->implode("\n");

        foreach (['finance/member-summary', 'finance-summary', 'member-finance-summary', 'outstanding-dues'] as $fragment) {
            $this->assertStringNotContainsString(
                $fragment,
                $routeUris,
                "3AH must not register future route fragment yet: {$fragment}"
            );
        }
    }

    public function test_milestone_3ah_does_not_add_future_api_implementation_files(): void
    {
        $appFilenames = collect(File::allFiles(base_path('app')))
            ->map(fn ($file) => $file->getFilename())
            ->all();

        foreach ([
            'MemberFinanceSummaryController.php',
            'MemberFinanceSummaryService.php',
            'MemberFinanceSummaryResource.php',
            'MemberFinanceSummaryRequest.php',
            'OutstandingDuesController.php',
            'OutstandingDuesService.php',
            'FinanceSummaryController.php',
            'FinanceSummaryService.php',
        ] as $filename) {
            $this->assertNotContains(
                $filename,
                $appFilenames,
                "3AH must not add implementation file yet: {$filename}"
            );
        }
    }

    public function test_milestone_3ah_blocks_non_read_api_scope(): void
    {
        $contents = File::get(base_path('docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md'));

        foreach ([
            'route implementation',
            'controller implementation',
            'service implementation',
            'request implementation',
            'resource implementation',
            'model implementation',
            'migration implementation',
            'frontend finance screens',
            'Treasurer finance dashboard',
            'Secretary finance dashboard',
            'President finance dashboard',
            'finance reports',
            'Excel import/export',
            'PDF changes',
            'reminder implementation',
            'special collection workflow',
            'fellowship allocation workflow',
            'payment correction workflow',
            'payment reversal workflow',
            'payment cancellation workflow',
            'payment mutation',
            'receipt mutation',
            'ledger mutation',
        ] as $scope) {
            $this->assertStringContainsString($scope, $contents);
        }
    }

    public function test_milestone_3ah_adds_only_authorization_document_and_authorization_test(): void
    {
        $document = base_path('docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md');
        $test = base_path('tests/Feature/Milestone3ahOutstandingDuesFinanceSummaryReadApiAuthorizationTest.php');

        $this->assertFileExists($document);
        $this->assertFileExists($test);

        $contents = File::get($document);

        $this->assertStringContainsString('Only these files are allowed for this milestone', $contents);
        $this->assertStringContainsString('docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md', $contents);
        $this->assertStringContainsString('tests/Feature/Milestone3ahOutstandingDuesFinanceSummaryReadApiAuthorizationTest.php', $contents);
    }
}