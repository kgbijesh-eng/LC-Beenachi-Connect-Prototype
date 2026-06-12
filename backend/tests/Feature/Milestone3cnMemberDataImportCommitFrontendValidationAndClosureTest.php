<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3cnMemberDataImportCommitFrontendValidationAndClosureTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cn_member_data_import_commit_frontend_validation_and_closure.md';
    private string $implementationDocPath = __DIR__ . '/../../docs/milestone_3cm_member_data_import_commit_frontend_narrow_implementation.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3cn_document_exists_and_is_docs_tests_only_closure(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3CN - Member Data Import Commit Frontend Validation and Closure', $document);
        $this->assertStringContainsString('docs/tests-only validation and closure milestone', $document);
        $this->assertStringContainsString('It validates and closes the 3CM member data import commit frontend implementation.', $document);
        $this->assertStringContainsString('It does not change production code.', $document);
        $this->assertStringContainsString('It does not edit app/page.tsx.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime or API behavior.', $document);
        $this->assertStringContainsString('It does not change old milestone tests.', $document);
    }

    public function test_3cn_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CM - Member Data Import Commit Frontend Narrow Implementation',
            'milestone-3cm-member-data-import-commit-frontend-narrow-implementation',
            'milestone-3cn-member-data-import-commit-frontend-validation-and-closure',
            'backend/docs/milestone_3cn_member_data_import_commit_frontend_validation_and_closure.md',
            'backend/tests/Feature/Milestone3cnMemberDataImportCommitFrontendValidationAndClosureTest.php',
            'Milestone 3CO - Member Data Import Commit Flow Closure Review',
            '3CO should be a closure review milestone only unless explicitly approved otherwise.',
            '3CN must not start 3CO.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cn_summarizes_3ck_3cl_and_3cm_lane_history(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3CK frontend scope gate summary',
            'Milestone 3CK opened the member data import commit frontend scope gate.',
            '3CL frontend implementation authorization summary',
            'Milestone 3CL converted the 3CK scope gate into future implementation authorization for 3CM.',
            '3CM frontend implementation summary',
            'Milestone 3CM connected the existing member import preview UI to the existing backend commit endpoint.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cn_records_existing_preview_and_commit_endpoints_and_no_backend_api_changes(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'POST /api/v1/clubs/{club}/members/import-preview',
            'POST /api/v1/clubs/{club}/members/import-commit',
            '3CM did not modify backend APIs.',
            '3CM did not add backend routes.',
            '3CM did not change backend controllers, requests, resources, services, models, middleware, migrations, seeders, config, or API contracts.',
            'Backend remains the source of truth',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cn_records_frontend_runtime_limited_to_app_page_and_does_not_edit_runtime_files(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Frontend runtime changes in 3CM were limited to:',
            'app/page.tsx',
            '3CM did not add frontend runtime files outside app/page.tsx.',
            '3CN does not edit app/page.tsx.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cn_frontend_still_contains_preview_first_commit_flow_and_safe_confirmation(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Run preview again before commit is offered.',
            'Run preview successfully before commit is offered.',
            'Confirm commit before calling the backend commit endpoint.',
            'checked={commitConfirmed}',
            'disabled={!canOfferCommit || !commitConfirmed}',
            'I understand this creates eligible new members only and will not overwrite, update, or delete existing members.',
            '{"Commit eligible new " + "members"}',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }
    }

    public function test_3cn_frontend_displays_backend_summary_and_row_level_created_skipped_failed_results(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Commit Summary',
            'Created',
            'Skipped',
            'Failed',
            'Commit Row',
            'Status:',
            'Member ID:',
            'Errors:',
            'Warnings:',
            'Created rows are new member records. Skipped and Failed rows require review and are not implied to be created.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }
    }

    public function test_3cn_documents_officer_only_normal_member_exclusion_and_club_scope(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The closed frontend commit lane remains officer-only.',
            'President/Admin through the existing president role flow',
            'Secretary',
            'Treasurer',
            'Normal Member remains excluded.',
            'Normal Member does not trigger commit API calls.',
            'Normal Member does not load preview or commit data into hidden frontend state.',
            'The frontend resolves active club context before preview or commit.',
            'Preview and commit calls are made only for the active authorized club.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cn_documents_preview_first_confirmation_and_row_level_display_closure_claims(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Preview must run successfully before commit is offered.',
            'Changing pasted rows clears preview and commit state.',
            'Preview and Commit remain clearly separated.',
            '3CM requires explicit confirmation before commit.',
            '3CM displays row-level eligibility before commit.',
            'Rows with validation errors remain visible before commit.',
            'Duplicate warnings remain visible before commit.',
            'Existing-member match warnings remain visible before commit.',
            '3CM displays backend-provided row-level results after commit:',
            'Summary counts do not hide row-level failures.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cn_documents_create_only_messaging_and_blocks_update_delete_silent_overwrite(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Commit eligible new members',
            'Confirm commit',
            'Created',
            'Skipped',
            'Failed',
            'Import all',
            'Overwrite',
            'Replace',
            'Apply all',
            'Bulk upload',
            'Upload Excel',
            'Upload CSV',
            'Existing-member update remains blocked.',
            'Member deletion remains blocked.',
            'Silent overwrite remains blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cn_documents_manual_rows_upload_parser_storage_and_queue_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Manual/paste JSON or spreadsheet-like rows remain the only input path.',
            'Excel upload remains blocked.',
            'CSV upload remains blocked.',
            'File upload/storage flow remains blocked.',
            'xlsx parser/package integration remains blocked.',
            'CSV parser/package integration remains blocked.',
            'No composer or package files were changed.',
            'Queue/job processing remains blocked.',
            'Background import processing remains blocked.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cn_documents_finance_adjacent_export_pdf_reminder_deployment_saas_and_client_specific_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'finance ledger',
            'dues',
            'payments',
            'receipts',
            'payment history',
            'balances',
            'Committee import remains blocked.',
            'Fellowship import remains blocked.',
            'Activity import remains blocked.',
            'Event import remains blocked.',
            'Export behavior remains blocked.',
            'PDF behavior remains blocked.',
            'Reminder behavior remains blocked.',
            'Deployment implementation remains blocked.',
            'SaaS implementation remains blocked.',
            'Client-specific behavior remains blocked.',
            'Hardcoded Lions Club Beenachi rules remain blocked.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cn_references_3cm_implementation_document_claims(): void
    {
        $implementationDocument = $this->implementationDocument();

        foreach ([
            'It connects the existing member import preview UI to the existing backend commit endpoint.',
            'It changes frontend runtime only in app/page.tsx.',
            'POST /api/v1/clubs/{club}/members/import-preview',
            'POST /api/v1/clubs/{club}/members/import-commit',
            'The frontend requires an explicit confirmation checkbox before commit.',
            'Commit eligible new members',
            'Milestone 3CN - Member Data Import Commit Frontend Validation and Closure',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $implementationDocument);
        }
    }

    public function test_3cn_final_closure_statement_and_next_milestone_are_recorded(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CN closes the Member Data Import Commit frontend lane.',
            'officer-only, club-scoped, preview-first frontend commit flow',
            'using manual/paste rows and the existing backend preview and commit endpoints',
            'requires explicit confirmation',
            'displays backend summary counts and row-level Created, Skipped, and Failed outcomes',
            'Any future Excel/CSV upload, parser package, upload storage, queued import, matched-row update, member deletion, finance import, deployment/SaaS, or client-specific behavior requires a new scope gate.',
            'Milestone 3CO - Member Data Import Commit Flow Closure Review',
            '3CN must not start 3CO.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->implementationDocPath);

        return file_get_contents($this->implementationDocPath);
    }

    private function frontendSource(): string
    {
        $this->assertFileExists($this->frontendPath);

        return file_get_contents($this->frontendPath);
    }

    private function sourceBetween(string $source, string $startNeedle, string $endNeedle): string
    {
        $start = strpos($source, $startNeedle);
        $this->assertNotFalse($start, "Could not find source start marker: {$startNeedle}");

        $end = strpos($source, $endNeedle, $start + strlen($startNeedle));
        $this->assertNotFalse($end, "Could not find source end marker: {$endNeedle}");

        return substr($source, $start, $end - $start);
    }
}
