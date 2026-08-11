<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3bgClubFinanceReportsBalanceSheetFrontendDisplayImplementationAuthorizationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bg_club_finance_reports_balance_sheet_frontend_display_implementation_authorization.md';

    public function test_3bg_document_exists_and_is_docs_tests_only_authorization(): void
    {
        $this->assertFileExists($this->docPath);

        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Milestone 3BG - Club Finance Reports and Balance Sheet Frontend Display Implementation Authorization', $document);
        $this->assertStringContainsString('docs/tests-only implementation authorization milestone', $document);
        $this->assertStringContainsString('It does not implement frontend display.', $document);
        $this->assertStringContainsString('It does not change app/page.tsx.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
    }

    public function test_3bg_authorizes_only_future_3bh_narrow_frontend_display(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Milestone 3BG now converts the 3BE scope gate into exact future authorization for Milestone 3BH.', $document);
        $this->assertStringContainsString('Milestone 3BH may implement only a narrow read-only frontend display', $document);
        $this->assertStringContainsString('Authorized future runtime file:', $document);
        $this->assertStringContainsString('app/page.tsx', $document);
        $this->assertStringContainsString('Milestone 3BH must not change any backend runtime file.', $document);
    }

    public function test_3bg_defines_frontend_display_boundary(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Club Finance Reports',
            'Balance Sheet Summary',
            'Outstanding dues summary',
            'Member-wise dues summary',
            'Payment collection summary',
            'Receipt summary',
            'inside the existing Finance section',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }
    }

    public function test_3bg_limits_future_frontend_to_existing_api_usage_only(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('GET /api/v1/clubs/{club}/finance/reports', $document);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/finance/balance-sheet', $document);
        $this->assertStringContainsString('Milestone 3BH must not add, rename, remove, or change backend routes.', $document);
        $this->assertStringContainsString('Milestone 3BH must not create new report endpoints.', $document);
        $this->assertStringContainsString('must not call the 3AZ candidate route family as if those routes exist', $document);
    }

    public function test_3bg_preserves_backend_as_source_of_truth(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'The backend remains the source of truth',
            'may format values for display',
            'Calculate official balances independently',
            'Recalculate dues totals independently',
            'Mutate finance data',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }
    }

    public function test_3bg_defines_allowed_role_visibility(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'President/Admin',
            'Secretary',
            'Treasurer',
            'Normal Member role must not see club-level finance reports',
            'must not expose privileged report data in hidden client state',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }
    }

    public function test_3bg_defines_safety_loading_empty_and_error_states(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Loading',
            'No finance records yet',
            'Forbidden access',
            'Unauthenticated access',
            'API error',
            'Missing active club context',
            'must not expose raw technical errors',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }
    }

    public function test_3bg_blocks_backend_api_accounting_mutation_and_workflows(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Backend routes',
            'Backend controllers',
            'Backend services',
            'Backend resources',
            'Backend requests',
            'Backend models',
            'Migrations',
            'Seeders',
            'API behavior',
            'Payment mutation',
            'Receipt mutation',
            'Payment cancellation',
            'Payment reversal',
            'Allocation workflow',
            'accounting mutation workflows',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3bg_blocks_export_pdf_excel_and_reminder_scope(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Report exports',
            'PDF generation',
            'Excel import',
            'Excel export',
            'Reminder workflow',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3bg_blocks_client_specific_hardcoding(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'one shared codebase strategy',
            'Lions Club Beenachi',
            'Rotary Central Bathery',
            'Any future pilot club',
            'Conditional behavior based on a specific club name',
            'Conditional behavior based on a specific pilot club code',
            'Client-specific production forks',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }
    }

    public function test_3bg_defines_expected_validation_for_3bh(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Future frontend display is implemented only in app/page.tsx',
            'No backend runtime files are changed',
            'Only the two existing 3BC API routes are used',
            'Backend remains the calculation source of truth',
            'Normal Member role does not see club-level reports',
            'Frontend build passes',
            'php artisan test',
            'git diff --check',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }
    }

    public function test_3bg_records_allowed_files_and_blocks_runtime_files(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('backend/docs/milestone_3bg_club_finance_reports_balance_sheet_frontend_display_implementation_authorization.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bgClubFinanceReportsBalanceSheetFrontendDisplayImplementationAuthorizationTest.php', $document);

        foreach ([
            'Frontend runtime files',
            'Backend routes',
            'Backend controllers',
            'Backend services',
            'Backend resources',
            'Backend requests',
            'Backend models',
            'Runtime behavior changes',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3bg_selects_3bh_as_next_milestone(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Milestone 3BH - Club Finance Reports and Balance Sheet Frontend Display Narrow Implementation.', $document);
        $this->assertStringContainsString('3BH may implement only the narrow frontend display authorized by this document.', $document);
    }

    public function test_3bg_adds_only_authorization_doc_and_test(): void
    {
        $this->assertFileExists(base_path('docs/milestone_3bg_club_finance_reports_balance_sheet_frontend_display_implementation_authorization.md'));
        $this->assertFileExists(base_path('tests/Feature/Milestone3bgClubFinanceReportsBalanceSheetFrontendDisplayImplementationAuthorizationTest.php'));

        $this->assertFileDoesNotExist(app_path('Http/Controllers/Api/V1/Finance/ClubFinanceReportFrontendController.php'));
        $this->assertFileDoesNotExist(app_path('Http/Requests/Finance/ClubFinanceReportFrontendRequest.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/ClubFinanceReportFrontendService.php'));
        $this->assertFileDoesNotExist(app_path('Models/ClubFinanceReportView.php'));
        $this->assertFileDoesNotExist(database_path('migrations/create_club_finance_report_views_table.php'));
        $this->assertFileDoesNotExist(base_path('../app/page.tsx.3bg'));
    }
}
