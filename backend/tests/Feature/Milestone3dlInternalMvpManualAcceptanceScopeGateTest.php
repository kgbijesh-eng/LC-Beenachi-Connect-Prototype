<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3dlInternalMvpManualAcceptanceScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dl_internal_mvp_manual_acceptance_scope_gate.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3dl_document_exists_and_records_docs_tests_only_scope_gate(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DL - Internal MVP Manual Acceptance Scope Gate',
            'Milestone 3DL is a docs/tests-only internal MVP manual acceptance scope gate.',
            '3DL defines the future manual acceptance process for LC Beenachi internal MVP readiness.',
            '3DL does not implement frontend runtime behavior.',
            '3DL does not edit app/page.tsx.',
            '3DL does not change backend runtime behavior.',
            '3DL does not run live/manual acceptance against production data.',
            '3DL does not deploy the app.',
            '3DL must not start 3DM.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dl_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'milestone-3dk-mvp-frontend-usability-validation-closure',
            'f511910a8a3bdff4c4c779a26c88033762609a10',
            'milestone-3dl-internal-mvp-manual-acceptance-scope-gate',
            'backend/docs/milestone_3dl_internal_mvp_manual_acceptance_scope_gate.md',
            'backend/tests/Feature/Milestone3dlInternalMvpManualAcceptanceScopeGateTest.php',
            'Milestone 3DM - Internal MVP Manual Acceptance Authorization',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dl_current_file_changes_are_limited_to_approved_docs_and_test_files(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DL is a docs/tests-only internal MVP manual acceptance scope gate.',
            '3DL defines the future manual acceptance process for LC Beenachi internal MVP readiness.',
            '3DL does not implement frontend runtime behavior.',
            '3DL does not edit app/page.tsx.',
            '3DL does not change backend runtime behavior.',
            '3DL does not add routes, controllers, requests, resources, services, models, migrations, policies, middleware, seeders, factories, config, package files, or lock files.',
            '3DL does not run live/manual acceptance against production data.',
            '3DL does not deploy the app.',
            'backend/docs/milestone_3dl_internal_mvp_manual_acceptance_scope_gate.md',
            'backend/tests/Feature/Milestone3dlInternalMvpManualAcceptanceScopeGateTest.php',
            'app/page.tsx is not edited in 3DL.',
            'Backend runtime is not edited in 3DL.',
            'Routes/controllers/requests/resources/services/models/migrations are not edited in 3DL.',
            'Older milestone tests are not edited in 3DL.',
            'Milestone 3DM - Internal MVP Manual Acceptance Authorization',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_project_relationships_through_3dk(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 2E recorded Member Module Closure.',
            'Milestones 3A through 3AF established finance foundation, payment recording, receipt, receipt PDF, and related closure lanes.',
            'Milestones 3AG through 3AI established Outstanding Dues and Member Finance Summary read API work.',
            'Milestones 3AJ through 3BZ covered Payment History, Import, roadmap, and related closure lanes where relevant.',
            'Milestones 3CT through 3DG covered Member Profile Documents backend and frontend lanes through metadata-only frontend closure.',
            'Milestones 3DH through 3DK covered MVP frontend usability readiness, implementation authorization, narrow implementation, validation, and closure.',
            '3DL does not reopen any closed lane.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_manual_acceptance_boundary_roles_and_local_data(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DM - Internal MVP Manual Acceptance Authorization',
            '3DL must not execute 3DM.',
            '3DL must not run browser/manual testing beyond validation commands.',
            'Local development environment.',
            'Seeded/local test users.',
            'Non-production data.',
            'Existing APIs only.',
            'Existing frontend only.',
            'Real member financial records.',
            'Production database.',
            'Live club deployment.',
            'President/Admin.',
            'Secretary.',
            'Treasurer.',
            'Normal Member.',
            'president@lcbeenachi.test',
            'secretary@lcbeenachi.test',
            'treasurer@lcbeenachi.test',
            'local.member@lcbeenachi.test',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_internal_mvp_acceptance_checklist(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            '### A. Login/session',
            '1. President/Admin can log in and see the internal MVP dashboard.',
            '5. Logout/session handling is understandable.',
            '### B. Dashboard and quick actions',
            '7. Internal MVP / Quick Actions area is visible.',
            '8. Members, Finance, Receipts, and Profile Documents areas are discoverable.',
            '### C. Members',
            '10. Member list loads clearly.',
            '11. Authorized officer can create a member.',
            '12. Authorized officer can edit a member.',
            '### D. Finance/current dues',
            '15. Current dues/member finance summary is visible where supported.',
            '19. Backend still blocks unauthorized finance actions.',
            '### E. Payment recording',
            '20. Payment recording entry point is discoverable for authorized roles where supported.',
            '23. Success/refresh behavior after payment is understandable where supported.',
            '### F. Receipts and payment history',
            '24. Receipt access is discoverable where supported.',
            '25. Receipt PDF access is discoverable where already approved.',
            '26. Payment history visibility is understandable where supported.',
            '### G. Member profile documents',
            '28. Profile Documents section is visible in member context.',
            '34. No profile document upload/download/preview/delete/OCR/PDF behavior is present.',
            '### H. States and errors',
            '35. Loading states are understandable.',
            '39. Refresh after create/update/payment works where supported.',
            '### I. Explicit exclusions',
            '47. No import/deployment/SaaS/client-specific behavior is introduced.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_result_categories_severity_go_no_go_and_reporting_format(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'PASS - works as expected for internal MVP.',
            'PASS WITH MINOR NOTES - usable but needs polish later.',
            'BLOCKED - cannot test because prerequisite/setup/API/data is missing.',
            'FAIL - approved MVP behavior exists but does not work.',
            'NOT IN MVP SCOPE - intentionally excluded from internal MVP.',
            'Critical - blocks internal MVP use or creates wrong access/security behavior.',
            'Major - important MVP flow broken but workaround may exist.',
            'Minor - usability issue that does not block internal testing.',
            'Note - observation for later polish.',
            'President/Admin, Secretary, Treasurer, and Member login flows are testable.',
            'No critical defects remain open.',
            'Login/session cannot be tested.',
            'Critical defects remain open.',
            '| ID | Area | Role | Test Step | Expected Result | Actual Result | Status | Severity | Notes |',
            'Total checks:',
            'Go/No-Go recommendation:',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_unsupported_exclusions_receipt_pdf_profile_documents_and_risks(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'No profile document upload.',
            'No profile document download.',
            'No profile document preview.',
            'No profile document delete.',
            'No OCR.',
            'No profile document PDF generation.',
            'Receipt PDF remains allowed only because it belongs to the already-approved finance/receipt lane.',
            'Receipt PDF wording must not be generalized to Profile Documents.',
            'Profile document upload/download/PDF/OCR remains out of scope.',
            'Profile document storage, public URLs, signed URLs, preview, file download, and DELETE behavior remain out of scope.',
            '3DL is not deployment readiness.',
            '3DL is not SaaS readiness.',
            '3DL does not certify production use.',
            'Manual testing is still required before real club operation.',
            'Local/test data may not reveal all real-data issues.',
            'UI role hiding is not security; backend authorization must remain authoritative.',
            'Existing app/page.tsx is large and future changes must remain narrow.',
            'Missing backend/API gaps must become separate milestones, not fake frontend behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_current_frontend_contains_known_internal_mvp_surfaces_read_only(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            'Internal MVP',
            'Quick Actions',
            'Active Members',
            'Current Dues',
            'Receipt',
            'Payment History',
            'Profile Documents',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $frontend);
        }
    }

    private function scopeDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function frontendSource(): string
    {
        $this->assertFileExists($this->frontendPath);

        return file_get_contents($this->frontendPath);
    }

    private function blockedPaths(): array
    {
        return [
            'app/page.tsx',
            'backend/routes/api.php',
            'backend/app/Http/Controllers',
            'backend/app/Http/Requests',
            'backend/app/Http/Resources',
            'backend/app/Services',
            'backend/app/Models',
            'backend/database/migrations',
            'backend/database/seeders',
            'backend/database/factories',
            'backend/app/Policies',
            'backend/app/Providers',
            'backend/app/Http/Middleware',
            'backend/config',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'pnpm-lock.yaml',
            'yarn.lock',
            'backend/tests/Feature/Milestone3djMvpFrontendUsabilityNarrowImplementationTest.php',
            'backend/tests/Feature/Milestone3diMvpFrontendUsabilityImplementationAuthorizationTest.php',
            'backend/tests/Feature/Milestone3dhMvpFrontendUsabilityReadinessScopeGateTest.php',
            'backend/tests/Feature/Milestone3dgMemberProfileDocumentsFrontendValidationAndClosureTest.php',
            'backend/tests/Feature/Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest.php',
        ];
    }
}
