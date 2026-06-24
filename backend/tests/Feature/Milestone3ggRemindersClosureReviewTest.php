<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ggRemindersClosureReviewTest extends TestCase
{
    public function test_closure_document_confirms_all_critical_controls(): void
    {
        $doc = file_get_contents(__DIR__.'/../../docs/milestone_3gg_reminders_closure_review.md');
        foreach ([
            '744851bc46194bf09e026f4ce72ece30482a067c',
            'd70abfa004db0652da2e2096425fe76b7c4741d5',
            'club-scoped `ClubReminder`', '`reminders.view`', '`reminders.manage`',
            'Draft -> Active/Cancelled', 'no reopening', 'member-visible Active/Completed',
            'UTC timed `due_at`', 'Overdue: computed only for Active records',
            'no DELETE', 'transactional', 'deterministic 409',
            'duplicate-title safety', 'Cross-club', 'clears stale rows/meta',
            'Mobile 390px', 'inspected read-only', 'isolated synthetic SQLite',
            'Reports must remain locked', 'actual remotely verified 3GG hash',
            'PASS - Reminders is backend-backed', 'No unresolved closure-critical defect exists',
        ] as $expected) {
            $this->assertStringContainsString($expected, $doc);
        }
    }

    public function test_only_two_3gg_files_are_changed_lifecycle_safely(): void
    {
        $repo = dirname(__DIR__, 3);
        exec('git -C '.escapeshellarg($repo).' log --all --oneline 2>&1', $log, $logCode);
        $this->assertSame(0, $logCode);
        $commit = null;
        foreach ($log as $line) {
            [$shortHash, $subject] = array_pad(explode(' ', $line, 2), 2, '');
            if ($subject === 'Close reminders backend activation') {
                exec('git -C '.escapeshellarg($repo).' rev-parse '.escapeshellarg($shortHash).' 2>&1', $hashOutput, $hashCode);
                $this->assertSame(0, $hashCode);
                $commit = trim(implode("\n", $hashOutput));
            }
        }
        $command = $commit
            ? 'git -C '.escapeshellarg($repo).' diff-tree --no-commit-id --name-only -r '.escapeshellarg($commit).' 2>&1'
            : 'git -C '.escapeshellarg($repo).' status --porcelain=v1 -uall 2>&1';
        exec($command, $output, $code);
        $this->assertSame(0, $code);
        $files = $commit
            ? array_map(fn ($line) => str_replace('\\', '/', trim($line)), array_filter($output))
            : array_map(fn ($line) => str_replace('\\', '/', trim(substr($line, 3))), array_filter($output));
        sort($files);
        $expected = [
            'backend/docs/milestone_3gg_reminders_closure_review.md',
            'backend/tests/Feature/Milestone3ggRemindersClosureReviewTest.php',
        ];
        sort($expected);
        $this->assertSame($expected, $files);
    }
}