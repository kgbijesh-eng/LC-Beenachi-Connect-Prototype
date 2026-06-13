<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3dhMvpFrontendUsabilityReadinessScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dh_mvp_frontend_usability_readiness_scope_gate.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3dh_document_exists_and_is_docs_tests_only_scope_gate(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DH - MVP Frontend Usability Readiness Scope Gate',
            'Milestone 3DH is a docs/tests-only MVP frontend usability readiness scope gate.',
            '3DH is a step-back readiness gate for internal LC Beenachi MVP use.',
            '3DH does not implement frontend runtime behavior.',
            '3DH does not edit app/page.tsx.',
            '3DH does not change backend runtime behavior.',
            '3DH must not start 3DI.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dh_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'milestone-3dg-member-profile-documents-frontend-validation-closure',
            '6270857cd2518df7818f726c74a340bad8fa7298',
            'milestone-3dh-mvp-frontend-usability-readiness-scope-gate',
            'backend/docs/milestone_3dh_mvp_frontend_usability_readiness_scope_gate.md',
            'backend/tests/Feature/Milestone3dhMvpFrontendUsabilityReadinessScopeGateTest.php',
            'Milestone 3DI - MVP Frontend Usability Implementation Authorization',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dh_current_file_changes_are_limited_to_approved_docs_and_test_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3dh_mvp_frontend_usability_readiness_scope_gate.md',
            'backend/tests/Feature/Milestone3dhMvpFrontendUsabilityReadinessScopeGateTest.php',
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

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DH may change only the approved scope doc and test files.');

        foreach ($this->blockedRuntimeAndOlderTestPaths() as $path) {
            $this->assertNotContains($path, $changed, "{$path} must not be edited in 3DH.");
        }
    }

    public function test_document_records_project_relationships_and_current_frontend_readiness_summary(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 1A created the UI Prototype foundation.',
            'Milestone 1B and 1C established backend readiness and Laravel foundation work.',
            'Milestones 2A through 2D built the member module foundation, create/edit foundation, frontend integration, and local role test users.',
            'Milestone 2E recorded the Member Module Closure Review',
            'Milestones 3A through 3AF established finance foundation, payment recording, receipt generation, receipt API, receipt PDF, and related closure lanes.',
            'Milestones 3AG through 3AI established Outstanding Dues and Member Finance Summary read API scope and implementation.',
            'Milestones 3AJ through 3AN validated and surfaced member finance summary/current dues frontend behavior.',
            'Milestones 3AP through 3AS covered payment recording frontend scope, implementation, and validation.',
            'Milestones 3AU through 3AX covered payment receipt frontend scope, implementation, and validation',
            'Milestones 3BK through 3BQ covered Payment History backend and frontend lanes.',
            'Milestone 3BR recorded Payment History lane closure.',
            'Milestones 3BS through 3CQ covered Member Data Import preview/commit',
            'Milestones 3CT through 3DG covered Member Profile Documents database, read API, create/update API, frontend implementation, and frontend closure.',
            'Current frontend readiness is mixed but close enough to define a focused internal MVP usability lane.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_readiness_buckets_and_checklists(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Ready enough for internal MVP:',
            'Partially ready:',
            'Backend exists but frontend missing/incomplete:',
            'Out of scope for MVP:',
            'Future SaaS/deployment work:',
            'Login/session usable.',
            'Member list usable.',
            'Member create/edit usable for authorized roles.',
            'Member profile/detail view usable.',
            'Current dues/finance summary visible.',
            'Payment recording usable by authorized roles.',
            'Receipt view/PDF download usable by authorized roles.',
            'Member profile documents metadata UI usable.',
            'Role-based controls understandable.',
            'Safe loading/empty/error/validation states.',
            'Basic dashboard cards.',
            'Quick links for common actions.',
            'Readable mobile/desktop layout.',
            'Clear no-data states.',
            'Simple role label display.',
            'Manual test checklist.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_post_mvp_saas_boundaries_future_lane_and_blocked_behavior(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Announcements.',
            'Meeting notices.',
            'Board minutes/general body minutes.',
            'Activity reports.',
            'Committee module.',
            'Fellowship/sponsorship queue.',
            'Blood donor consent/search.',
            'Excel/PDF reports.',
            'WhatsApp/SMS/email reminders.',
            'Multi-club onboarding.',
            'Subscriptions/billing.',
            'Club settings panel.',
            'Backup/restore.',
            'Deployment automation.',
            'Usage limits.',
            'Support/admin tooling.',
            'Client-specific customization.',
            'A future MVP frontend usability lane may improve only narrowly necessary internal-use frontend behavior.',
            'Clearer navigation between Members, Finance, Receipts, Payment History, and Profile Documents.',
            'More usable member selection/member detail flow.',
            'Basic dashboard summary using existing APIs only.',
            '3DH does not authorize broad refactoring.',
            '3DH does not authorize redesigning the full application.',
            '3DH does not authorize new modules unrelated to MVP internal use.',
            'Future blocked backend behavior:',
            'Future blocked upload/download/storage/OCR/PDF behavior:',
            'Future blocked finance backend mutation behavior:',
            'Future blocked import/deployment/SaaS/client-specific behavior:',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_role_state_manual_acceptance_risks_and_closure_criteria(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'President/Admin should see internal MVP controls needed for member management',
            'Secretary should see authorized member management',
            'Treasurer should see authorized finance actions',
            'Normal Member should see only own allowed information',
            'Backend authorization remains authoritative.',
            'Loading state while backend context or lists load.',
            'Empty state when no records exist.',
            'Safe error state when a backend call fails or authorization blocks access.',
            'Validation message display from backend responses.',
            'Future manual internal MVP acceptance checklist:',
            '1. President/Admin can log in and see dashboard.',
            '10. Member profile document metadata list/create/edit works where authorized.',
            '14. No upload/download/OCR/PDF behavior is introduced beyond already approved receipt PDF.',
            '15. No SaaS/deployment/client-specific behavior is introduced.',
            'Receipt PDF is already an approved finance/receipt feature.',
            'Profile document PDF/upload/download remains out of scope.',
            'The largest MVP risk is not missing backend capability; it is fragmented frontend usability after many narrow milestones.',
            'A future MVP frontend usability lane should close only when:',
            'No backend runtime files are changed without a new authorization.',
            'No profile document upload/download/storage/OCR/PDF behavior is introduced.',
            'No SaaS/deployment/client-specific behavior is introduced.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_current_frontend_contains_known_mvp_surfaces_read_only(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            'LoginScreen',
            'Dashboard',
            'Active Members',
            'Member Directory',
            'MemberProfile',
            'Member Finance Summary',
            'Current Dues',
            'Record Member Payment',
            'Receipt List',
            'Download Receipt PDF',
            'Payment History',
            'Profile Documents',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $frontend);
        }
    }

    public function test_document_records_runtime_exclusions_and_no_3di_start(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            '3DH does not implement frontend runtime behavior.',
            '3DH does not edit app/page.tsx.',
            '3DH does not change backend runtime behavior.',
            '3DH does not add routes, controllers, requests, resources, services, models, migrations, policies, middleware, seeders, factories, config, package files, or lock files.',
            'Profile document upload.',
            'Profile document storage.',
            'Profile document download.',
            'Public URLs.',
            'Signed URLs.',
            'OCR.',
            'PDF generation outside already-approved receipt PDF.',
            'DELETE behavior.',
            'Member import behavior.',
            'Deployment automation.',
            'SaaS onboarding, billing, usage limits, support/admin tooling.',
            'Client-specific hardcoding.',
            '3DH must not start 3DI.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
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
            'backend/tests/Feature/Milestone3dgMemberProfileDocumentsFrontendValidationAndClosureTest.php',
            'backend/tests/Feature/Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest.php',
            'backend/tests/Feature/Milestone3deMemberProfileDocumentsFrontendImplementationAuthorizationTest.php',
        ];
    }
}
