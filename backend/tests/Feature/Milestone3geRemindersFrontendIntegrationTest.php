<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3geRemindersFrontendIntegrationTest extends TestCase
{
    public function test_frontend_integration_is_backend_backed_and_truthful(): void
    {
        $component = file_get_contents(dirname(__DIR__, 3).'/app/RemindersBackend.tsx');
        $page = file_get_contents(dirname(__DIR__, 3).'/app/page.tsx');
        $document = file_get_contents(__DIR__.'/../../docs/milestone_3ge_reminders_frontend_integration.md');

        foreach ([
            '/reminders?', '/reminders/${id}', '/reminders/${selected.id}/${action}',
            'setRows([]); setMeta(null)', 'Create backend Draft', 'Verified backend read-only',
            'SENTINEL-3GE-REMINDERS-20260624', 'scrollWidth == clientWidth',
            'No notification delivery', 'No genuine record was mutated',
        ] as $expected) {
            $this->assertTrue(str_contains($component.$document, $expected), "Missing [$expected].");
        }

        $this->assertStringContainsString('import RemindersBackend from "./RemindersBackend";', $page);
        $this->assertStringContainsString('{ label: "Reminders", screen: "notifications-reminders" }', $page);
        $this->assertStringContainsString('<RemindersBackend role={role}', $page);
        $this->assertStringNotContainsString('localStorage', $component);
    }
}
