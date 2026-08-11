<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3caMemberDataImportFrontendScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3ca_member_data_import_frontend_scope_gate.md';

    public function test_3ca_document_exists_and_is_docs_tests_only_scope_gate(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3CA - Member Data Import Frontend Scope Gate', $document);
        $this->assertStringContainsString('docs/tests-only scope gate', $document);
        $this->assertStringContainsString('It does not implement frontend upload UI.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change API behavior.', $document);
    }

    public function test_3ca_records_base_branch_and_allowed_files(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3BZ - Member Data Import API Validation and Closure', $document);
        $this->assertStringContainsString('milestone-3bz-member-data-import-api-validation-and-closure', $document);
        $this->assertStringContainsString('milestone-3ca-member-data-import-frontend-scope-gate', $document);
        $this->assertStringContainsString('backend/docs/milestone_3ca_member_data_import_frontend_scope_gate.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3caMemberDataImportFrontendScopeGateTest.php', $document);
    }

    public function test_3ca_blocks_runtime_frontend_backend_api_import_file_queue_and_finance_changes(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'app/page.tsx',
            'frontend runtime files',
            'backend routes',
            'backend controllers',
            'backend requests',
            'backend resources',
            'backend services',
            'backend models',
            'backend middleware',
            'backend migrations',
            'backend seeders',
            'backend config',
            'composer.json',
            'composer.lock',
            'API behavior',
            'member import API implementation files',
            'member import preview service',
            'file upload files',
            'file storage files',
            'Excel package files',
            'Excel parser integration files',
            'queue files',
            'job files',
            'finance files',
            'payment files',
            'receipt files',
            'payment-history files',
            'ledger files',
            'committee import files',
            'fellowship import files',
            'activity import files',
            'event import files',
            'export files',
            'PDF files',
            'reminder files',
            'deployment files',
            'SaaS files',
            'client-specific hardcoding files',
            '3CA must not implement frontend upload UI.',
            '3CA must not implement import commit UI.',
            '3CA must not add frontend file parsing.',
            '3CA must not add backend routes or API changes.',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $document);
        }
    }

    public function test_3ca_limits_future_frontend_to_existing_3by_preview_route(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'POST /api/v1/clubs/{club}/members/import-preview',
            'must not create or require new backend API routes',
            'must not change the API response contract',
            'backend as the source of truth',
            'spreadsheet-like rows as JSON arrays only if a later implementation milestone explicitly authorizes the input mechanism',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }

        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'import'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'POST api/v1/clubs/{club}/members/import-preview',
            'POST api/v1/clubs/{club}/members/import-commit',
        ], $routes);
    }

    public function test_3ca_defines_normal_member_no_import_frontend_boundary(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Must not see member import preview UI.',
            'Must not upload member data.',
            'Must not paste import rows.',
            'Must not preview imports.',
            'Must not commit imports.',
            'Must not load import preview data into hidden frontend state.',
        ] as $roleRule) {
            $this->assertStringContainsString($roleRule, $document);
        }
    }

    public function test_3ca_defines_officer_preview_only_frontend_boundary(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'President/Admin, Secretary, and Treasurer',
            'May be considered for future officer-only import preview UI.',
            'Must remain scoped to the active club authorized by the backend.',
            'Must use only the existing 3BY preview endpoint.',
            'Must not receive cross-club import preview data.',
            'Must not receive commit/import mutation controls.',
            'Backend authorization remains the source of truth for every frontend visibility decision.',
        ] as $roleRule) {
            $this->assertStringContainsString($roleRule, $document);
        }
    }

    public function test_3ca_blocks_upload_parser_template_commit_and_member_mutation_ui(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'file upload UI',
            'drag-and-drop upload',
            'xlsx parsing in the browser',
            'CSV parsing in the browser',
            'template download',
            'sample file generation',
            'import commit',
            'member create/update/delete action',
            'raw file storage',
            'Any future upload or parser experience must be separately scoped after preview UI closure.',
        ] as $blockedInput) {
            $this->assertStringContainsString($blockedInput, $document);
        }
    }

    public function test_3ca_defines_preview_safe_frontend_metadata_without_inventing_fields(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'dry_run',
            'committed',
            'summary counts',
            'row number',
            'row validity',
            'intent',
            'normalized member fields returned by the backend',
            'row-level validation errors',
            'duplicate row warnings',
            'existing-member match warnings',
            'absent, null, forbidden, or empty',
            'safe unavailable or empty state',
            'must not invent fields, matches, member statuses, duplicate decisions, balances, totals, labels, or import outcomes',
        ] as $safeText) {
            $this->assertStringContainsString($safeText, $document);
        }
    }

    public function test_3ca_blocks_hidden_finance_internals_cross_club_matches_and_client_specific_content(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'hidden columns',
            'raw uploaded file contents beyond submitted preview rows',
            'finance fields',
            'ledger fields',
            'payment fields',
            'receipt fields',
            'payment-history fields',
            'cancellation internals',
            'reversal internals',
            'allocation internals',
            'unrelated member private data',
            'global cross-club member matches',
            'client-specific labels or logic',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3ca_requires_safe_future_frontend_states(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'loading',
            'empty or no rows entered',
            'validation errors returned by backend',
            'duplicate warnings returned by backend',
            'existing-member match warnings returned by backend',
            'missing active club context',
            'unauthenticated or 401',
            'forbidden or 403',
            'general API error',
            'successful preview display',
            'missing or null backend fields',
            'must not expose raw technical errors to users',
        ] as $safeState) {
            $this->assertStringContainsString($safeState, $document);
        }
    }

    public function test_3ca_blocks_import_commit_finance_export_reminder_deployment_and_saas_ui(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'frontend implementation',
            'app/page.tsx changes',
            'backend route changes',
            'controller changes',
            'request class changes',
            'resource changes',
            'service changes',
            'model changes',
            'middleware changes',
            'migration changes',
            'seeder changes',
            'file upload UI',
            'parser UI',
            'import commit UI',
            'member create UI through import',
            'member update UI through import',
            'member delete UI through import',
            'silent overwrite UI',
            'finance import UI',
            'dues import UI',
            'ledger import UI',
            'payment import UI',
            'receipt import UI',
            'payment-history import UI',
            'committee import UI',
            'fellowship import UI',
            'activity import UI',
            'event import UI',
            'export UI',
            'PDF UI',
            'Excel UI',
            'reminder UI',
            'notification UI',
            'deployment implementation',
            'SaaS implementation',
            'client-specific hardcoding',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3ca_does_not_add_frontend_import_runtime_files(): void
    {
        foreach ([
            base_path('../app/member-import'),
            base_path('../app/import'),
            base_path('../app/members/import'),
            base_path('../components/member-import'),
            base_path('../components/import-preview'),
            base_path('../lib/member-import.ts'),
            base_path('../lib/import-preview.ts'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3ca_selects_3cb_frontend_implementation_authorization_candidate_without_starting_it(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3CB - Member Data Import Frontend Implementation Authorization', $document);
        $this->assertStringContainsString('3CB must not start from 3CA unless explicitly approved.', $document);
        $this->assertStringContainsString('3CB should remain docs/tests-only unless the human explicitly authorizes a different scope.', $document);
        $this->assertStringContainsString('3CB may authorize a future frontend preview implementation only if it stays inside the 3CA frontend scope gate.', $document);
    }

    private function scopeGateDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
