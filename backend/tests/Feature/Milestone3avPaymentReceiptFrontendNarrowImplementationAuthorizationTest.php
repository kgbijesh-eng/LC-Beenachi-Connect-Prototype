<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3avPaymentReceiptFrontendNarrowImplementationAuthorizationTest extends TestCase
{
    public function test_milestone_3av_authorization_document_exists(): void
    {
        $path = base_path('docs/milestone_3av_payment_receipt_frontend_narrow_implementation_authorization.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3AV — Payment Receipt Frontend Narrow Implementation Authorization', $content);
        $this->assertStringContainsString('Status: AUTHORIZATION ONLY', $content);
        $this->assertStringContainsString('docs/tests-only', $content);
        $this->assertStringContainsString('No implementation is allowed in 3AV.', $content);
    }

    public function test_milestone_3av_declares_only_allowed_files(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3av_payment_receipt_frontend_narrow_implementation_authorization.md'));

        $this->assertStringContainsString('backend/docs/milestone_3av_payment_receipt_frontend_narrow_implementation_authorization.md', $content);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3avPaymentReceiptFrontendNarrowImplementationAuthorizationTest.php', $content);
    }

    public function test_milestone_3av_authorizes_future_3aw_candidate_without_implementation(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3av_payment_receipt_frontend_narrow_implementation_authorization.md'));

        $this->assertStringContainsString('3AV authorizes a later narrow implementation milestone', $content);
        $this->assertStringContainsString('Milestone 3AW — Payment Receipt Frontend Narrow Implementation', $content);
        $this->assertStringContainsString('app/page.tsx', $content);
        $this->assertStringContainsString('No other frontend file is authorized unless a later scope gate explicitly expands the boundary.', $content);
    }

    public function test_milestone_3av_limits_future_frontend_to_existing_backend_apis(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3av_payment_receipt_frontend_narrow_implementation_authorization.md'));

        $this->assertStringContainsString('Existing member finance summary API', $content);
        $this->assertStringContainsString('Existing receipt JSON view API', $content);
        $this->assertStringContainsString('Existing receipt PDF download API', $content);
        $this->assertStringContainsString('The future frontend must not add backend routes or change backend behavior.', $content);
    }

    public function test_milestone_3av_defines_role_visibility_boundary(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3av_payment_receipt_frontend_narrow_implementation_authorization.md'));

        $this->assertStringContainsString('Normal member:', $content);
        $this->assertStringContainsString('May access only their own issued receipts', $content);
        $this->assertStringContainsString('Must not generate receipts', $content);
        $this->assertStringContainsString('President/Admin, Secretary, Treasurer:', $content);
        $this->assertStringContainsString('Must remain blocked from cross-club receipt access', $content);
    }

    public function test_milestone_3av_blocks_runtime_frontend_backend_and_workflow_drift(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3av_payment_receipt_frontend_narrow_implementation_authorization.md'));

        $blockedItems = [
            'app/page.tsx',
            'routes',
            'controllers',
            'requests',
            'resources',
            'services',
            'models',
            'migrations',
            'seeders',
            'receipt frontend implementation',
            'payment history implementation',
            'reports',
            'reminders',
            'Excel import',
            'special collection allocation',
            'fellowship allocation',
            'payment cancellation',
            'payment reversal',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $content);
        }
    }

    public function test_milestone_3av_records_future_3aw_blocked_scope(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3av_payment_receipt_frontend_narrow_implementation_authorization.md'));

        $this->assertStringContainsString('Blocked in future 3AW unless separately authorized', $content);
        $this->assertStringContainsString('Receipt generation from frontend', $content);
        $this->assertStringContainsString('Payment history implementation', $content);
        $this->assertStringContainsString('No frontend or backend runtime file is changed.', $content);
    }
}