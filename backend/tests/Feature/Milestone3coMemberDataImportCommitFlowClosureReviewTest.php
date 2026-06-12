<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3coMemberDataImportCommitFlowClosureReviewTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3co_member_data_import_commit_flow_closure_review.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3co_document_exists_and_is_docs_tests_only_closure_review(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3CO - Member Data Import Commit Flow Closure Review', $document);
        $this->assertStringContainsString('docs/tests-only closure review milestone', $document);
        $this->assertStringContainsString('It reviews and closes the full member data import commit flow across backend and frontend.', $document);
        $this->assertStringContainsString('It does not change production code.', $document);
        $this->assertStringContainsString('It does not edit app/page.tsx.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime or API behavior.', $document);
        $this->assertStringContainsString('It does not change old milestone tests.', $document);
    }

    public function test_3co_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CN - Member Data Import Commit Frontend Validation and Closure',
            'milestone-3cn-member-data-import-commit-frontend-validation-and-closure',
            'milestone-3co-member-data-import-commit-flow-closure-review',
            'backend/docs/milestone_3co_member_data_import_commit_flow_closure_review.md',
            'backend/tests/Feature/Milestone3coMemberDataImportCommitFlowClosureReviewTest.php',
            'Milestone 3CP - Member Data Import Module Closure Review',
            '3CP should be a final module closure review milestone only unless explicitly approved otherwise.',
            '3CO must not start 3CP.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3co_records_full_3cf_through_3cn_chain(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3CF - Member Data Import Commit Scope Gate',
            '3CG - Member Data Import Commit Implementation Authorization',
            '3CH - Member Data Import Commit Backend Scope and Authorization Closure',
            '3CI - Member Data Import Commit Backend Narrow Implementation',
            '3CJ - Member Data Import Commit Backend Validation and Closure',
            '3CK - Member Data Import Commit Frontend Scope Gate',
            '3CL - Member Data Import Commit Frontend Implementation Authorization',
            '3CM - Member Data Import Commit Frontend Narrow Implementation',
            '3CN - Member Data Import Commit Frontend Validation and Closure',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3co_summarizes_each_commit_flow_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Summary of 3CF',
            'Summary of 3CG',
            'Summary of 3CH',
            'Summary of 3CI',
            'Summary of 3CJ',
            'Summary of 3CK',
            'Summary of 3CL',
            'Summary of 3CM',
            'Summary of 3CN',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3co_records_existing_preview_and_commit_endpoints_and_they_remain_registered(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'POST /api/v1/clubs/{club}/members/import-preview',
            'POST /api/v1/clubs/{club}/members/import-commit',
            'Preview and Commit remain separate steps.',
            'The commit endpoint does not replace or weaken the preview endpoint.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $routes = collect(Route::getRoutes());

        $this->assertCount(1, $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-preview'));
        $this->assertCount(1, $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-commit'));
    }

    public function test_3co_records_backend_and_frontend_commit_lane_closure(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The backend commit lane is closed.',
            'The closed backend lane includes only the authorized commit route, controller, request, resource, service, docs, and tests added through 3CI and validated through 3CJ.',
            'The frontend commit lane is closed.',
            'The closed frontend lane consists only of a commit flow inside the existing member import preview UI.',
            'The frontend lane uses existing backend endpoints only.',
            'The frontend lane remains preview-first and explicitly confirmed.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3co_records_no_backend_api_or_frontend_runtime_changes_in_3co(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3CO does not modify backend APIs.',
            '3CO does not add backend routes.',
            '3CO does not change backend controllers, requests, resources, services, models, middleware, migrations, seeders, config, or API contracts.',
            '3CO does not edit app/page.tsx.',
            '3CO does not add frontend runtime files.',
            '3CO does not change package files.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3co_frontend_still_contains_preview_first_confirmation_and_row_result_display(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Run preview again before commit is offered.',
            'Run preview successfully before commit is offered.',
            'Confirm commit before calling the backend commit endpoint.',
            'checked={commitConfirmed}',
            'disabled={!canOfferCommit || !commitConfirmed}',
            '{"Commit eligible new " + "members"}',
            'Commit Summary',
            'Created',
            'Skipped',
            'Failed',
            'Commit Row',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }
    }

    public function test_3co_documents_access_control_club_scope_and_cross_club_fail_closed(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The full commit flow remains officer-only.',
            'President/Admin through existing president or president-admin role flows',
            'Secretary',
            'Treasurer',
            'Normal Member remains excluded from the full commit flow.',
            'Normal Member does not trigger preview or commit API calls.',
            'The full commit flow remains club-scoped.',
            'Committed members are created only inside the authorized club.',
            'Cross-club access remains fail-closed.',
            'Unauthenticated users are blocked.',
            'Unauthorized roles are blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3co_documents_preview_first_confirmation_backend_validation_and_rechecks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Preview must be run successfully before commit is offered.',
            'Changing pasted rows clears preview and commit state.',
            'Explicit confirmation is required before commit.',
            'Backend validation is authoritative.',
            'Frontend-only validation is not trusted.',
            'Duplicate rows are re-checked at commit time.',
            'Duplicate decisions are not blindly inherited from stale preview results.',
            'Existing-member matches are re-checked at commit time.',
            'Existing-member match decisions are not blindly inherited from stale preview results.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3co_documents_row_level_results_summary_counts_and_create_only_boundary(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The frontend displays backend-provided row-level commit results:',
            'Created',
            'Skipped',
            'Failed',
            'Row-level errors and warnings remain visible.',
            'The frontend displays backend-provided summary counts:',
            'total rows',
            'created rows',
            'skipped rows',
            'failed rows',
            'Commit creates eligible new members only.',
            'Invalid rows do not create members.',
            'Duplicate rows do not create duplicate members.',
            'Existing-member match rows do not overwrite existing member data.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3co_documents_update_delete_and_silent_overwrite_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Existing-member update remains blocked.',
            'Existing-member matches remain skipped or blocked outcomes.',
            'No matched-row update UI exists.',
            'Member deletion remains blocked.',
            'Member deactivation through import remains blocked.',
            'No member deletion UI exists in the import flow.',
            'Silent overwrite remains blocked.',
            'The frontend states that existing members are not overwritten or updated.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3co_documents_manual_rows_upload_parser_storage_queue_and_package_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Manual/paste JSON or spreadsheet-like rows remain the only input path.',
            'The UI uses textarea input.',
            'The backend accepts JSON row payloads.',
            'Excel upload remains blocked.',
            'CSV upload remains blocked.',
            'File upload/storage flow remains blocked.',
            'xlsx parser/package integration remains blocked.',
            'CSV parser/package integration remains blocked.',
            'No composer or package file changes are part of the commit flow.',
            'Queue/job processing remains blocked.',
            'Background import processing remains blocked.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3co_documents_finance_adjacent_export_pdf_reminder_deployment_saas_and_client_specific_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'finance ledger',
            'dues',
            'payments',
            'receipts',
            'payment history',
            'balances',
            'committee data',
            'fellowship data',
            'activity data',
            'event data',
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

    public function test_3co_final_closure_statement_and_next_milestone_are_recorded(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CO closes the full Member Data Import Commit flow.',
            'spans the 3CF through 3CN chain',
            'club-scoped, officer-only, preview-first commit workflow',
            'using the existing preview endpoint and existing commit endpoint',
            'commit-time validation and duplicate/existing-member re-checks are authoritative',
            'eligible new members may be created, existing members are not updated or deleted',
            'Any future Excel/CSV upload, file storage, parser package, queued import processing, matched-row update behavior, member deletion, finance import, export/PDF/reminder behavior, deployment/SaaS behavior, or client-specific behavior requires a new scope gate.',
            'Milestone 3CP - Member Data Import Module Closure Review',
            '3CO must not start 3CP.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
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
