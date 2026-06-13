<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3diMvpFrontendUsabilityImplementationAuthorizationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3di_mvp_frontend_usability_implementation_authorization.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3di_document_exists_and_is_docs_tests_only_authorization(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3DI - MVP Frontend Usability Implementation Authorization',
            'Milestone 3DI is a docs/tests-only MVP frontend usability implementation authorization milestone.',
            '3DI converts the 3DH MVP frontend usability readiness scope gate into a precise future implementation authorization boundary.',
            '3DI does not implement frontend runtime behavior.',
            '3DI does not edit app/page.tsx.',
            '3DI does not change backend runtime behavior.',
            '3DI must not start 3DJ.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3di_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'milestone-3dh-mvp-frontend-usability-readiness-scope-gate',
            'd0ec337f448ca5a1f2714e7474ccd7c7f396f207',
            'milestone-3di-mvp-frontend-usability-implementation-authorization',
            'backend/docs/milestone_3di_mvp_frontend_usability_implementation_authorization.md',
            'backend/tests/Feature/Milestone3diMvpFrontendUsabilityImplementationAuthorizationTest.php',
            'Milestone 3DJ - MVP Frontend Usability Narrow Implementation',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3di_current_file_changes_are_limited_to_approved_docs_and_test_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3di_mvp_frontend_usability_implementation_authorization.md',
            'backend/tests/Feature/Milestone3diMvpFrontendUsabilityImplementationAuthorizationTest.php',
        ];

        $repoRoot = dirname(__DIR__, 3);
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';
        $changed = collect(preg_split('/\R/', trim($status)))
            ->filter()
            ->map(function (string $line): string {
                $path = preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line;
                $path = trim($path);
                if (str_contains($path, ' -> ')) {
                    $path = trim(substr($path, strrpos($path, ' -> ') + 4));
                }

                return str_replace('\\', '/', $path);
            })
            ->values()
            ->all();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DI may change only the approved authorization doc and test files.');

        foreach ($this->blockedRuntimeAndOlderTestPaths() as $path) {
            $this->assertNotContains($path, $changed, "{$path} must not be edited in 3DI.");
        }
    }

    public function test_document_records_existing_foundation_relationships(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 1A created the UI Prototype foundation.',
            'Milestone 1B and 1C established backend readiness and Laravel foundation work.',
            'Milestones 2A through 2D built the member module foundation, create/edit foundation, frontend integration, and local role test users.',
            'Milestone 2E recorded the Member Module Closure Review',
            'Milestones 3A through 3AF established finance foundation, payment recording, receipt generation, receipt API, receipt PDF, and related closure lanes.',
            'Milestones 3AG through 3AI established Outstanding Dues and Member Finance Summary read API scope and implementation.',
            'Milestones 3BK through 3BQ covered Payment History backend and frontend lanes.',
            'Milestones 3BS through 3CQ covered Member Data Import preview/commit',
            'Milestones 3CT through 3DG covered Member Profile Documents database, read API, create/update API, frontend implementation, and frontend closure.',
            'Milestone 3DH recorded the MVP Frontend Usability Readiness Scope Gate.',
            '3DI does not reopen any closed lane.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_current_mvp_readiness_baseline(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'The current internal MVP baseline is ready for a narrow usability pass, not broad expansion.',
            'Authentication / login session visibility through local role test flows.',
            'Dashboard role-aware cards and quick access patterns.',
            'Member list / Active Members and member directory views.',
            'Member create/edit flows for authorized roles.',
            'Member profile/member detail flow.',
            'Member finance summary and Current Dues visibility.',
            'Payment recording frontend behavior for authorized roles where already backed by existing APIs.',
            'Receipt display and already-approved receipt PDF access where supported by existing backend behavior.',
            'Payment History visibility where supported by existing APIs and UI patterns.',
            'Member Profile Documents metadata list/create/edit frontend behavior.',
            'Role-aware navigation and controls across core flows.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_implementation_authorization_and_file_boundary(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3DJ may implement only targeted frontend usability improvements for the internal MVP.',
            '3DJ must use existing backend APIs already available in the project.',
            'If a needed frontend flow lacks a backend API, 3DJ must document the gap as missing and avoid fake behavior.',
            'Expected future frontend implementation target:',
            'app/page.tsx',
            '3DI must not edit app/page.tsx.',
            'If 3DJ later needs additional frontend files, that must be explicitly justified and kept narrow.',
            '3DJ must inspect existing API helper conventions in app/page.tsx and reuse them.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_allowed_frontend_usability_areas(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Authentication / login session visibility.',
            'Dashboard usability.',
            'Member list usability.',
            'Member create/edit flow clarity.',
            'Member profile/member detail flow.',
            'Member finance summary/current dues visibility.',
            'Payment recording frontend usability if already present or partially present.',
            'Receipt/receipt PDF frontend usability using existing backend capability.',
            'Payment History visibility if existing APIs/UI patterns support it.',
            'Member Profile Documents section usability.',
            'Role-aware navigation and controls.',
            'Loading states.',
            'Empty states.',
            'Safe error states.',
            'Validation message display.',
            'Manual internal-use checklist alignment.',
            '3DJ must not perform a broad redesign.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_blocked_behavior_and_runtime_exclusions(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Future blocked frontend/backend behavior:',
            'New backend APIs.',
            'New routes.',
            'New controllers, requests, resources, services, models, migrations, policies, middleware, seeders, factories, config, package files, or lock files.',
            'New database behavior.',
            'Committee, fellowship, activity, event, export, reminder, WhatsApp, SMS, email, or notification runtime modules.',
            'Future blocked upload/download/storage/OCR/PDF behavior:',
            'Profile document upload.',
            'Profile document storage.',
            'Profile document file download.',
            'Profile document public URLs.',
            'Profile document signed URLs.',
            'Profile document OCR.',
            'Profile document PDF generation.',
            'Profile document DELETE behavior.',
            'Receipt PDF is allowed only for the already-approved payment receipt PDF feature.',
            'Profile document upload/download/PDF/OCR remains out of scope.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_finance_import_deployment_saas_and_client_exclusions(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Future 3DJ must not add new finance backend mutation behavior.',
            'New payment mutation behavior.',
            'New ledger mutation behavior.',
            'New receipt generation mutation behavior.',
            'New finance recalculation behavior.',
            'New payment history mutation behavior.',
            'Frontend usability may only surface existing finance, payment, receipt, and Payment History capabilities already approved by earlier lanes.',
            'Member import behavior.',
            'Parser packages.',
            'Deployment automation.',
            'SaaS onboarding.',
            'Billing or subscriptions.',
            'Usage limits.',
            'Support/admin tooling.',
            'Multi-club onboarding work.',
            'Client-specific hardcoding.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_priority_order_role_api_and_ux_copy_boundaries(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Priority 1 - Make existing internal MVP flows usable:',
            'Priority 2 - Consistent state handling:',
            'Priority 3 - Role clarity:',
            'Priority 4 - Manual internal-use readiness:',
            'President/Admin can see relevant member-management and finance/admin controls already authorized by backend.',
            'Secretary can see relevant member-management/profile-document controls already authorized by backend.',
            'Treasurer can see relevant finance controls already authorized by backend.',
            'Normal Member can see only own allowed information and must not see officer-only controls.',
            'UI hiding is convenience only.',
            'Backend authorization remains the enforcement layer.',
            'Future 3DJ may use only existing backend APIs already available in the project.',
            '3DJ must not create or require new backend routes.',
            '3DJ must not authorize raw assumptions about missing endpoints.',
            'Future 3DJ may use direct internal-use wording such as:',
            'Add Document Metadata.',
            'Edit Metadata.',
            'Future 3DJ must avoid labels that imply unsupported behavior:',
            'Upload Document.',
            'Download Profile Document.',
            'Generate Profile PDF.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_manual_acceptance_checklist_risks_and_stop_conditions(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Future 3DJ must preserve and implement against this manual internal MVP acceptance checklist:',
            '1. President/Admin can log in and see a usable dashboard.',
            '8. Current dues/member finance summary is visible and understandable.',
            '10. Receipt/receipt PDF access is discoverable where already supported.',
            '12. Member profile document metadata list/create/edit is usable where authorized.',
            '14. Backend still blocks unauthorized actions.',
            '16. No unsupported upload/download/OCR/profile-document PDF behavior is introduced.',
            '17. No SaaS/deployment/client-specific behavior is introduced.',
            'app/page.tsx is already large; 3DJ must avoid broad refactoring.',
            'Frontend usability may expose gaps where backend APIs are missing; 3DJ must not fake unavailable backend behavior.',
            'Role hiding in UI is not security; backend authorization remains mandatory.',
            'Receipt PDF is allowed only because it is already approved in the finance/receipt lane.',
            'Profile document upload/download/PDF/OCR remains out of scope.',
            'SaaS/deployment work must not be mixed into MVP usability.',
            'Over-expanding 3DJ could delay internal MVP readiness.',
            'Future 3DJ must stop and report instead of implementing if:',
            'A required flow needs a new backend API.',
            'A required flow needs profile document upload, download, storage, public URL, signed URL, OCR, profile document PDF generation, or DELETE behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_current_frontend_contains_known_mvp_surfaces_read_only(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            'Dashboard',
            'Active Members',
            'Profile Documents',
            'Current Dues',
            'Record Payment',
            'Receipt List',
            'Download Receipt PDF',
            'Payment History',
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

    private function blockedRuntimeAndOlderTestPaths(): array
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
            'backend/tests/Feature/Milestone3dhMvpFrontendUsabilityReadinessScopeGateTest.php',
            'backend/tests/Feature/Milestone3dgMemberProfileDocumentsFrontendValidationAndClosureTest.php',
            'backend/tests/Feature/Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest.php',
            'backend/tests/Feature/Milestone3deMemberProfileDocumentsFrontendImplementationAuthorizationTest.php',
        ];
    }
}
