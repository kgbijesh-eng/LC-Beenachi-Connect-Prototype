<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3anMemberFinanceSummaryFrontendValidationAndClosureTest extends TestCase
{
    private string $docPath = 'docs/milestone_3an_member_finance_summary_frontend_validation_and_closure.md';

    public function test_milestone_3an_closure_document_exists(): void
    {
        $this->assertFileExists(base_path($this->docPath));
    }

    public function test_milestone_3an_is_docs_and_tests_only(): void
    {
        $document = file_get_contents(base_path($this->docPath));

        $this->assertStringContainsString('This milestone is docs/tests-only.', $document);

        $this->assertStringContainsString(
            'backend/docs/milestone_3an_member_finance_summary_frontend_validation_and_closure.md',
            $document
        );

        $this->assertStringContainsString(
            'backend/tests/Feature/Milestone3anMemberFinanceSummaryFrontendValidationAndClosureTest.php',
            $document
        );

        $this->assertStringContainsString('No other files are in scope for this milestone.', $document);
    }

    public function test_milestone_3an_validates_read_only_frontend_scope(): void
    {
        $document = file_get_contents(base_path($this->docPath));

        $this->assertStringContainsString('read-only member finance summary view', $document);
        $this->assertStringContainsString('It must not introduce any finance write action.', $document);
        $this->assertStringContainsString('Milestone 3AN does not add, modify, or broaden any API route.', $document);
    }

    public function test_milestone_3an_blocks_payment_receipt_report_reminder_excel_and_ledger_drift(): void
    {
        $document = file_get_contents(base_path($this->docPath));

        $blockedItems = [
            'Frontend changes',
            'Backend route changes',
            'Backend controller changes',
            'Backend service changes',
            'Backend resource changes',
            'Backend request changes',
            'Backend model changes',
            'Backend migration changes',
            'Backend seeder changes',
            'Payment button',
            'Receipt generation button',
            'Report implementation',
            'Dashboard implementation',
            'Reminder implementation',
            'Excel import',
            'Ledger mutation',
            'Special collection workflow',
            'Fellowship allocation workflow',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $document);
        }
    }

    public function test_milestone_3an_records_required_frontend_and_backend_validation(): void
    {
        $document = file_get_contents(base_path($this->docPath));

        $this->assertStringContainsString('npm run build', $document);
        $this->assertStringContainsString(
            'php artisan test --filter=Milestone3anMemberFinanceSummaryFrontendValidationAndClosureTest',
            $document
        );
        $this->assertStringContainsString('php artisan test', $document);

        $this->assertStringContainsString('Frontend build passes', $document);
        $this->assertStringContainsString('Focused 3AN test passes', $document);
        $this->assertStringContainsString('Backend suite passes', $document);
        $this->assertStringContainsString('Git diff remains limited to the two allowed files', $document);
    }
}