<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3gbRemindersAuthorizationContractApprovalTest extends TestCase
{
    private const BASE = 'dc72dd039c5f4112efc1944e72b3f86f040fd98a';
    private const SUBJECT = 'Approve reminders authorization contract';
    private const FILES = [
        'backend/docs/milestone_3gb_reminders_authorization_contract_approval.md',
        'backend/tests/Feature/Milestone3gbRemindersAuthorizationContractApprovalTest.php',
    ];
    private string $repo = __DIR__.'/../../..';
    private string $doc = __DIR__.'/../../docs/milestone_3gb_reminders_authorization_contract_approval.md';

    public function test_contract_decisions_are_complete(): void
    {
        $d = $this->document();
        foreach ([
            self::BASE, '`reminders.view`', '`reminders.manage`',
            '| President/Admin (`president-admin`) | Yes | Yes |',
            '| Secretary (`secretary`) | Yes | Yes |',
            '| Treasurer (`treasurer`) | Yes | No |',
            '| Normal Member (`member`) | Yes | No |',
            '| Draft | either | Visible | Hidden |',
            '| Active | members | Visible | Visible |',
            '| Cancelled Active | members | Visible | Visible |',
            'No DELETE route', 'Create requires `reminders.manage`',
            '`is_all_day=true`', '`is_all_day=false`',
            'Payload must not control `club_id`', 'Generic update rejects status',
            'Draft -> Active', 'Active -> Completed',
            'Completion before due is explicitly allowed.',
            'Draft -> Cancelled or Active -> Cancelled',
            'deterministic 409', '`per_page`, default 25, maximum 100',
            'Active overdue reminders', 'numeric ID tie-breaker',
            'Only Active records can be overdue.', 'Cross-club IDs fail closed.',
            '`reminders.created`', '`reminders.cancelled`',
            'Mutation and audit are atomic.', '3GC may implement only',
            '3GD may begin only from remotely verified clean 3GC',
            'GO - 3GC may open',
        ] as $expected) {
            $this->assertStringContainsString($expected, $d);
        }
    }

    public function test_only_contract_files_change_with_lifecycle_safe_history(): void
    {
        $commit = $this->findCommit();
        if ($commit !== null) {
            $this->assertSameSorted(self::FILES, $this->gitLines(['diff-tree', '--no-commit-id', '--name-only', '-r', $commit]));
            $historical = $this->git(['show', $commit.':backend/docs/milestone_3gb_reminders_authorization_contract_approval.md']);
            $this->assertStringContainsString('runtime implementation is authorized', $historical);
            $this->assertStringContainsString('GO - 3GC may open', $historical);
            return;
        }
        $this->assertSame(self::BASE, trim($this->git(['rev-parse', 'HEAD'])));
        $this->assertSameSorted(self::FILES, $this->workingFiles());
    }

    public function test_runtime_implementation_is_not_authorized(): void
    {
        $d = $this->document();
        $this->assertStringContainsString('No migration, model, permission seeding, route, controller, request, resource, service, factory, frontend, database mutation, or runtime implementation is authorized.', $d);
        foreach (($this->findCommit() ? $this->gitLines(['diff-tree', '--no-commit-id', '--name-only', '-r', $this->findCommit()]) : $this->workingFiles()) as $file) {
            $this->assertFalse(str_starts_with($file, 'backend/app/'));
            $this->assertFalse(str_starts_with($file, 'backend/database/'));
            $this->assertNotSame('backend/routes/api.php', $file);
            $this->assertNotSame('app/page.tsx', $file);
        }
    }

    private function document(): string
    {
        $this->assertFileExists($this->doc);
        return file_get_contents($this->doc);
    }

    private function findCommit(): ?string
    {
        $matches = [];
        foreach ($this->gitLines(['log', '--all', '--oneline']) as $line) {
            [$hash, $subject] = array_pad(explode(' ', $line, 2), 2, '');
            if ($subject === self::SUBJECT) {
                $matches[] = trim($this->git(['rev-parse', $hash]));
            }
        }
        $this->assertLessThanOrEqual(1, count($matches));
        return $matches[0] ?? null;
    }

    private function workingFiles(): array
    {
        $files = [];
        foreach ($this->gitLines(['status', '--porcelain=v1']) as $line) {
            $path = trim(substr($line, 3));
            $files[] = str_replace('\\', '/', str_contains($path, ' -> ') ? substr($path, strpos($path, ' -> ')+4) : $path);
        }
        return $files;
    }

    private function gitLines(array $args): array
    {
        return array_values(array_filter(explode("\n", $this->git($args))));
    }

    private function git(array $args): string
    {
        $command = array_merge(['git', '-C', realpath($this->repo) ?: $this->repo], $args);
        exec(implode(' ', array_map('escapeshellarg', $command)).' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
        return implode("\n", $out);
    }

    private function assertSameSorted(array $expected, array $actual): void
    {
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }
}
