<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3auPaymentReceiptFrontendScopeGateTest extends TestCase
{
    public function test_milestone_3au_scope_gate_document_exists(): void
    {
        $path = base_path('docs/milestone_3au_payment_receipt_frontend_scope_gate.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3AU — Payment Receipt Frontend Scope Gate', $content);
        $this->assertStringContainsString('docs/tests-only', $content);
        $this->assertStringContainsString('No implementation is allowed.', $content);
    }

    public function test_milestone_3au_declares_only_allowed_files(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3au_payment_receipt_frontend_scope_gate.md'));

        $this->assertStringContainsString('backend/docs/milestone_3au_payment_receipt_frontend_scope_gate.md', $content);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3auPaymentReceiptFrontendScopeGateTest.php', $content);
    }

    public function test_milestone_3au_blocks_frontend_and_backend_runtime_changes(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3au_payment_receipt_frontend_scope_gate.md'));

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

    public function test_milestone_3au_keeps_receipt_frontend_as_future_work(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3au_payment_receipt_frontend_scope_gate.md'));

        $this->assertStringContainsString('3AU does not add any frontend screen', $content);
        $this->assertStringContainsString('Frontend implementation must happen only in a later implementation milestone', $content);
        $this->assertStringContainsString('Existing receipt APIs, payment APIs, PDF receipt APIs, and member finance summary APIs remain unchanged.', $content);
    }
}