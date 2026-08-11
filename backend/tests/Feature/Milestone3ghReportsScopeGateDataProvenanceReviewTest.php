<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ghReportsScopeGateDataProvenanceReviewTest extends TestCase
{
    private const BASE = '980f4e3a82891d0be378db4064ceb79462c1be1d';
    private const SUBJECT = 'Add reports scope gate and provenance review';
    private const FILES = [
        'backend/docs/milestone_3gh_reports_scope_gate_data_provenance_review.md',
        'backend/tests/Feature/Milestone3ghReportsScopeGateDataProvenanceReviewTest.php',
    ];
    private string $repo = __DIR__.'/../../..';

    public function test_architecture_provenance_privacy_and_financial_deferral_are_explicit(): void
    {
        $doc = file_get_contents(__DIR__.'/../../docs/milestone_3gh_reports_scope_gate_data_provenance_review.md');
        foreach ([
            self::BASE, 'scope gate only', 'fixed report catalog', 'computed on demand',
            'no persisted `reports` or `generated_reports` table',
            '`membership-summary`', '`events-register`', '`projects-register`', '`reminders-register`',
            '`collections-summary`', 'Deferred', 'uses floating-point aggregation',
            '`reports.view`', '`reports.finance`', '| Normal Member (`member`) | No | No |',
            'Cross-club records are excluded before filtering',
            'unsupported reports never return fake zero output',
            'residential address', 'bank/UPI details', 'arbitrary columns/formulas/SQL',
            'GO - 3GI may open',
        ] as $expected) {
            $this->assertStringContainsString($expected, $doc);
        }
    }

    public function test_changed_file_scope_is_lifecycle_safe(): void
    {
        $commit = $this->findCommit();
        if ($commit === null) {
            $this->assertSame(self::BASE, trim($this->git(['rev-parse', 'HEAD'])));
            $files = $this->workingFiles();
        } else {
            $files = $this->gitLines(['diff-tree', '--no-commit-id', '--name-only', '-r', $commit]);
            $historical = $this->git(['show', $commit.':backend/docs/milestone_3gh_reports_scope_gate_data_provenance_review.md']);
            $this->assertStringContainsString('GO - 3GI may open', $historical);
        }
        sort($files);
        $expected = self::FILES;
        sort($expected);
        $this->assertSame($expected, $files);
    }

    private function findCommit(): ?string
    {
        $matches = [];
        foreach ($this->gitLines(['log', '--all', '--oneline']) as $line) {
            [$hash, $subject] = array_pad(explode(' ', $line, 2), 2, '');
            if ($subject === self::SUBJECT) $matches[] = trim($this->git(['rev-parse', $hash]));
        }
        $this->assertLessThanOrEqual(1, count($matches));
        return $matches[0] ?? null;
    }

    private function workingFiles(): array
    {
        return array_map(
            fn ($line) => str_replace('\\', '/', trim(substr($line, 3))),
            $this->gitLines(['status', '--porcelain=v1', '-uall'])
        );
    }

    private function gitLines(array $args): array
    {
        return array_values(array_filter(explode("\n", $this->git($args))));
    }

    private function git(array $args): string
    {
        $command = array_merge(['git', '-C', realpath($this->repo) ?: $this->repo], $args);
        exec(implode(' ', array_map('escapeshellarg', $command)).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
        return implode("\n", $output);
    }
}
