<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3bbClubFinanceReportsBalanceSheetReadApiAuthorizationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bb_club_finance_reports_balance_sheet_read_api_authorization.md';

    public function test_authorization_document_exists(): void
    {
        $this->assertFileExists($this->docPath);
    }

    public function test_authorizes_future_read_only_club_finance_reports_api(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Milestone 3BB — Club Finance Reports and Balance Sheet Read API Authorization', $document);
        $this->assertStringContainsString('AUTHORIZED FOR FUTURE NARROW IMPLEMENTATION', $document);
        $this->assertStringContainsString('read-only Club Finance Reports and Balance Sheet API access', $document);
        $this->assertStringContainsString('The future implementation may include only:', $document);
        $this->assertStringContainsString('Read-only report endpoints', $document);
        $this->assertStringContainsString('Club-scoped financial aggregation from existing approved finance records', $document);
        $this->assertStringContainsString('Cross-club fail-closed behavior', $document);
    }

    public function test_future_report_categories_are_defined_without_implementation(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Club finance overview', $document);
        $this->assertStringContainsString('Outstanding dues summary', $document);
        $this->assertStringContainsString('Member-wise dues summary', $document);
        $this->assertStringContainsString('Payment collection summary', $document);
        $this->assertStringContainsString('Receipt summary', $document);
        $this->assertStringContainsString('Balance sheet summary', $document);
        $this->assertStringContainsString('This milestone is docs/tests-only. It does not implement the API.', $document);
    }

    public function test_balance_sheet_boundary_is_read_only(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('The future balance sheet API must be read-only.', $document);
        $this->assertStringContainsString('It may present summarized financial position using approved club finance records', $document);
        $this->assertStringContainsString('must not introduce accounting mutation workflows', $document);
        $this->assertStringContainsString('must not create, edit, reverse, cancel, allocate, or export financial records', $document);
    }

    public function test_access_boundary_is_defined(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('President/Admin: allowed to view club finance reports', $document);
        $this->assertStringContainsString('Secretary: allowed to view club finance reports', $document);
        $this->assertStringContainsString('Treasurer: allowed to view club finance reports', $document);
        $this->assertStringContainsString('Member: blocked from club-level reports', $document);
        $this->assertStringContainsString('Cross-club access must fail closed.', $document);
    }

    public function test_runtime_and_implementation_files_are_explicitly_blocked(): void
    {
        $document = file_get_contents($this->docPath);

        $blockedItems = [
            'routes',
            'controllers',
            'requests',
            'resources',
            'services',
            'models',
            'migrations',
            'seeders',
            'app/page.tsx',
            'report implementation',
            'balance sheet implementation',
            'exports',
            'reminders',
            'Excel import',
            'frontend screens',
            'payment history',
            'allocation workflows',
            'payment cancellation',
            'payment reversal',
            'receipt sharing',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $document);
        }

        $this->assertStringContainsString('Milestone 3BB must not change runtime behavior.', $document);
        $this->assertStringContainsString('No API route is added here.', $document);
    }

    public function test_authorizes_next_narrow_implementation_milestone_only(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Milestone 3BC — Club Finance Reports and Balance Sheet Read API Narrow Implementation', $document);
        $this->assertStringContainsString('Milestone 3BC may implement the read-only API within the boundaries defined above.', $document);
    }

    public function test_closure_rule_requires_docs_tests_only_and_full_validation(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Only this documentation file and its matching validation test are added', $document);
        $this->assertStringContainsString('No runtime files are changed', $document);
        $this->assertStringContainsString('The validation test confirms the authorization boundary', $document);
        $this->assertStringContainsString('The full backend suite passes', $document);
    }
}
