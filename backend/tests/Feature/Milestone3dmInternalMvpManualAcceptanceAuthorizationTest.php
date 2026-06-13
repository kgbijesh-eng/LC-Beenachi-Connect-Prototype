<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3dmInternalMvpManualAcceptanceAuthorizationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dm_internal_mvp_manual_acceptance_authorization.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3dm_document_exists_and_records_docs_tests_only_authorization_scope(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3DM - Internal MVP Manual Acceptance Authorization',
            'Milestone 3DM is a docs/tests-only authorization milestone.',
            '3DM authorizes a future controlled internal MVP manual acceptance execution lane.',
            '3DM does not execute manual acceptance.',
            '3DM does not edit frontend runtime code.',
            '3DM does not edit app/page.tsx.',
            '3DM does not change backend runtime behavior.',
            '3DM does not deploy the app.',
            '3DM does not use production data.',
            '3DM does not use real club financial records.',
            '3DM must not start 3DN.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dm_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'milestone-3dl-internal-mvp-manual-acceptance-scope-gate',
            'f0052943e5f53299b8c67c9cc11c3f5b093cc29d',
            'milestone-3dm-internal-mvp-manual-acceptance-authorization',
            'backend/docs/milestone_3dm_internal_mvp_manual_acceptance_authorization.md',
            'backend/tests/Feature/Milestone3dmInternalMvpManualAcceptanceAuthorizationTest.php',
            'Milestone 3DN - Internal MVP Manual Acceptance Execution',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dm_records_file_scope_without_policing_later_working_tree_changes(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'backend/docs/milestone_3dm_internal_mvp_manual_acceptance_authorization.md',
            'backend/tests/Feature/Milestone3dmInternalMvpManualAcceptanceAuthorizationTest.php',
            'Read-only inspection of existing frontend, backend, docs, and tests is allowed.',
            'app/page.tsx is not edited in 3DM.',
            'Frontend runtime is not edited in 3DM.',
            'Backend runtime is not edited in 3DM.',
            'Routes/controllers/requests/resources/services/models/migrations are not edited in 3DM.',
            'Older milestone tests are not edited in 3DM.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        foreach ($this->blockedPaths() as $path) {
            $this->assertStringNotContainsString("- {$path}", $document, "{$path} must not be listed as a 3DM editable file.");
        }
    }

    public function test_document_records_relationships_to_prior_lanes(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 2E recorded Member Module Closure.',
            'Milestones 3A through 3AF established finance foundation, payment recording, receipt, receipt PDF, and related closure lanes.',
            'Milestones 3AG through 3AI established Outstanding Dues and Member Finance Summary read API work.',
            'Milestones 3AJ through 3BZ covered Payment History, Import, roadmap, and related closure lanes where relevant.',
            'Milestones 3CT through 3DG covered Member Profile Documents backend and frontend lanes through metadata-only frontend closure.',
            'Milestone 3DK closed the MVP frontend usability implementation lane.',
            'Milestone 3DL defined the Internal MVP Manual Acceptance Scope Gate.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_3dn_execution_boundary_allowed_and_blocked_checks(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            '3DM authorizes this future execution milestone:',
            'Local development environment.',
            'Non-production database.',
            'Seeded/local test users.',
            'No real member financial records.',
            'Existing frontend only.',
            'Existing APIs only.',
            'Production database.',
            'Real club financial records.',
            'Real payment collection.',
            'Live club deployment.',
            'Login/session using local/test users.',
            'Dashboard usability.',
            'Internal MVP quick actions.',
            'Member create/edit.',
            'Current dues/member finance summary.',
            'Receipt PDF access where existing support allows.',
            'Profile Documents metadata list/create/edit.',
            'Backend authorization blocking unauthorized actions.',
            'Profile document upload.',
            'Profile document download.',
            'Profile document OCR.',
            'Profile document PDF generation.',
            'Deployment readiness.',
            'SaaS readiness.',
            'Production use.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_roles_local_users_evidence_result_categories_and_severity(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'President/Admin.',
            'Secretary.',
            'Treasurer.',
            'Normal Member.',
            'president@lcbeenachi.test',
            'secretary@lcbeenachi.test',
            'treasurer@lcbeenachi.test',
            'local.member@lcbeenachi.test',
            'The local/test-user requirement is to use existing seeded/local test users and documented local credentials only.',
            'Date/time of run.',
            'Branch/hash tested.',
            'Environment used.',
            'Database/data source confirmation.',
            'Role personas tested.',
            'Checklist results.',
            'Defects found.',
            'Final go/no-go recommendation.',
            'PASS - works as expected for internal MVP.',
            'PASS WITH MINOR NOTES - usable but needs polish later.',
            'BLOCKED - cannot test because prerequisite/setup/API/data is missing.',
            'FAIL - approved MVP behavior exists but does not work.',
            'NOT IN MVP SCOPE - intentionally excluded from internal MVP.',
            'Critical - blocks internal MVP use or creates wrong access/security behavior.',
            'Major - important MVP flow broken but workaround may exist.',
            'Minor - usability issue that does not block internal testing.',
            'Note - observation for later polish.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_stop_conditions_go_no_go_reporting_and_exclusions(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Wrong branch/hash.',
            'Production database detected.',
            'Real member financial data detected.',
            'Backend server/frontend server cannot start.',
            'Normal Member can access officer-only actions.',
            'Backend authorization appears bypassed.',
            'Profile documents imply upload/download/storage/OCR/PDF/delete support.',
            'President/Admin, Secretary, Treasurer, and Member login flows are testable.',
            'No unsupported upload/download/OCR/profile-document PDF/delete behavior appears.',
            'No critical defects remain open.',
            'Login/session cannot be tested.',
            'Finance/current dues display is misleading or unusable.',
            'Runtime errors prevent normal navigation.',
            '| ID | Area | Role | Test Step | Expected Result | Actual Result | Status | Severity | Notes |',
            'Total checks:',
            'Go/No-Go recommendation:',
            'Profile document storage/public/signed URLs.',
            'Member import with real files/data.',
            'Real club financial settlement.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_receipt_pdf_profile_document_risks_and_authorization_decision(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Receipt PDF remains allowed only because it belongs to the approved finance/receipt lane.',
            'Receipt PDF manual acceptance must be limited to already-supported payment receipt behavior.',
            'Receipt PDF wording must not be generalized to Profile Documents.',
            'Profile document upload/download/PDF/OCR remains out of scope.',
            'Profile document storage, public URLs, signed URLs, preview, file download, and DELETE behavior remain out of scope.',
            '3DM is not manual acceptance execution.',
            '3DM is not deployment readiness.',
            '3DM is not SaaS readiness.',
            '3DM does not certify production use.',
            'Manual acceptance still must be run in 3DN.',
            'Local/test data may not reveal all real-data issues.',
            'UI role hiding is not security; backend authorization must remain authoritative.',
            'Existing app/page.tsx is large and future changes must remain narrow.',
            'Missing backend/API gaps discovered during 3DN must become separate future milestones, not fake frontend behavior.',
            '3DN must stop if production data or real financial data is detected.',
            'Milestone 3DM authorizes Milestone 3DN - Internal MVP Manual Acceptance Execution under the boundaries in this document.',
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

    private function authorizationDocument(): string
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
            'backend/tests/Feature/Milestone3dkMvpFrontendUsabilityValidationAndClosureTest.php',
            'backend/tests/Feature/Milestone3djMvpFrontendUsabilityNarrowImplementationTest.php',
            'backend/tests/Feature/Milestone3diMvpFrontendUsabilityImplementationAuthorizationTest.php',
            'backend/tests/Feature/Milestone3dhMvpFrontendUsabilityReadinessScopeGateTest.php',
        ];
    }
}
