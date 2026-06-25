<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3hdProfileUpdateRequestBrowserRoleVerificationTest extends TestCase
{
    private const BASE = 'e8a695655f89a146e7d034c05b734fec553eeace';
    private const SUBJECT = 'Verify profile update request workflows';

    public function test_browser_and_role_verification_evidence_conforms(): void
    {
        $document = file_get_contents(__DIR__.'/../../docs/milestone_3hd_profile_update_request_browser_role_verification.md');
        $component = file_get_contents(dirname(__DIR__, 3).'/app/ProfileUpdatesBackend.tsx');

        foreach ([
            'President/Admin', 'Secretary', 'Treasurer', 'Normal Member',
            'Inactive Member', 'Another-Club Member', '1440px', '900px', '390px',
            'Duplicate Pending Protection', 'Stale-Member Conflict',
        ] as $expected) {
            $this->assertTrue(str_contains($document.$component, $expected), "Missing [$expected] in document or component.");
        }
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
            'backend/docs/milestone_3hd_profile_update_request_browser_role_verification.md',
            'backend/tests/Feature/Milestone3hdProfileUpdateRequestBrowserRoleVerificationTest.php'
        ];
        sort($files);
        sort($allowed);
        $this->assertSame($allowed, $files);
    }
}
