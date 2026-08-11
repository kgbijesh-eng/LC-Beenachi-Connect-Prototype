<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3heProfileUpdateRequestClosureReviewTest extends TestCase
{
    private const BASE = '297e650eda5e9ef3273f71fee45f5715924e538a';
    private const SUBJECT = 'Close profile update request workflow';

    public function test_closure_review_evidence_conforms(): void
    {
        $document = file_get_contents(__DIR__.'/../../docs/milestone_3he_profile_update_request_closure_review.md');

        foreach ([
            'Milestone 3HE', 'Formal Closure Check Table',
            'allowed-field contract', 'prohibited-field protection',
            'duplicate Pending protection', 'stale-member conflict',
            'optimistic locking', 'audit logging', 'privacy masking', 'cross-club protection',
        ] as $expected) {
            $this->assertTrue(str_contains($document, $expected), "Missing [$expected] in document.");
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
            'backend/docs/milestone_3he_profile_update_request_closure_review.md',
            'backend/tests/Feature/Milestone3heProfileUpdateRequestClosureReviewTest.php'
        ];
        sort($files);
        sort($allowed);
        $this->assertSame($allowed, $files);
    }
}
