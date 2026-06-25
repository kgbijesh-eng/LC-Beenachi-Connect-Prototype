<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3hcProfileUpdateRequestFrontendIntegrationTest extends TestCase
{
    private const BASE = 'c5658b354dff855d7799e524de9b70ce91194148';
    private const SUBJECT = 'Connect profile update request frontend';

    public function test_frontend_integration_is_backend_backed_and_truthful(): void
    {
        $component = file_get_contents(dirname(__DIR__, 3).'/app/ProfileUpdatesBackend.tsx');
        $page = file_get_contents(dirname(__DIR__, 3).'/app/page.tsx');
        $document = file_get_contents(__DIR__.'/../../docs/milestone_3hc_profile_update_request_frontend_integration.md');

        foreach ([
            '/profile-update-requests', 'RESTRICTED', 'showSensitive',
            'Withdraw Request', 'Approve Request', 'Reject', 'rejection_reason',
            'PROFILE-UPDATE-SENTINEL-3HC', 'Duplicate Profile Member',
        ] as $expected) {
            $this->assertTrue(str_contains($component.$document, $expected), "Missing [$expected].");
        }

        $this->assertStringContainsString('import ProfileUpdatesBackend from "./ProfileUpdatesBackend";', $page);
        $this->assertStringContainsString('<ProfileUpdatesBackend role={role} mode="own" />', $page);
        $this->assertStringContainsString('<ProfileUpdatesBackend role={role} mode="review" />', $page);
    }

    public function test_changed_file_scope_is_lifecycle_safe(): void
    {
        $root = dirname(__DIR__, 3);
        $subject = self::SUBJECT;
        exec('git -C "'.$root.'" log --all --format=%H%x09%s', $logs, $code);
        $this->assertSame(0, $code);
        $matches = [];
        foreach ($logs as $line) {
            [$h, $s] = array_pad(explode("	", $line, 2), 2, '');
            if ($s === $subject) {
                $matches[] = $h;
            }
        }
        if ($matches) {
            exec('git -C "'.$root.'" diff-tree --no-commit-id --name-only -r '.$matches[0], $files, $status);
            $this->assertSame(0, $status);
        } else {
            $this->assertSame(self::BASE, trim(shell_exec('git -C "'.$root.'" rev-parse HEAD')));
            exec('git -C "'.$root.'" status --porcelain=v1 -uall', $raw, $status);
            $files = array_map(fn($x) => str_replace('\\', '/', substr($x, 3)), $raw);
        }
        $allowed = [
            'app/ProfileUpdatesBackend.tsx',
            'app/page.tsx',
            'backend/docs/milestone_3hc_profile_update_request_frontend_integration.md',
            'backend/tests/Feature/Milestone3hcProfileUpdateRequestFrontendIntegrationTest.php'
        ];
        sort($files);
        sort($allowed);
        $this->assertSame($allowed, $files);
    }
}
