<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3gfRemindersBrowserRoleVerificationTest extends TestCase
{
    public function test_verification_evidence_covers_roles_truth_identity_and_responsiveness(): void
    {
        $doc = file_get_contents(__DIR__.'/../../docs/milestone_3gf_reminders_browser_role_verification.md');
        foreach ([
            'sentinel ID 3', 'duplicate-title IDs 9 and 10', 'activated Draft stable ID 1',
            'Member had reachable Reminders navigation', 'no create control',
            'cleared privileged selected reminder state', 'displayed failure and Retry',
            'scroll width equal to client width', 'President and Secretary',
            'Treasurer and Member', '403 on mutation', 'cross-club concealment',
            'No genuine data', 'No closure-critical defect remains',
            'GO - 3GG may open',
        ] as $expected) {
            $this->assertStringContainsString($expected, $doc);
        }
    }
}
