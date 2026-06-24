<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3gaRemindersBackendActivationScopeGateTest extends TestCase
{
    private const BASE_COMMIT = '2a7ce32a15a9d53b3e49716ebe3613d3a6518d1a';
    private const COMMIT_SUBJECT = 'Add reminders backend scope gate';
    private const AUTHORIZED_FILES = [
        'backend/docs/milestone_3ga_reminders_backend_activation_scope_gate.md',
        'backend/tests/Feature/Milestone3gaRemindersBackendActivationScopeGateTest.php',
    ];

    private string $docPath = __DIR__.'/../../docs/milestone_3ga_reminders_backend_activation_scope_gate.md';
    private string $repoPath = __DIR__.'/../../..';

    public function test_scope_architecture_lifecycle_visibility_and_permissions_are_approved(): void
    {
        $document = $this->document();

        foreach ([
            'documentation-and-governance scope gate only',
            'No runtime Reminders authorization',
            '`App\\Models\\ClubReminder`, backed by `club_reminders`',
            'It does not send notifications.',
            'Draft -> Active',
            'Draft -> Cancelled',
            'Active -> Completed',
            'Active -> Cancelled',
            'Completed and Cancelled are terminal.',
            'generic update cannot mutate status',
            '`members`',
            '`management`',
            'Cancelled Active `members` reminders remain member-readable.',
            '`reminders.view`',
            '`reminders.manage`',
            '| President/Admin (`president-admin`) | Yes | Yes |',
            '| Secretary (`secretary`) | Yes | Yes |',
            '| Treasurer (`treasurer`) | Yes | No |',
            '| Normal Member (`member`) | Yes | No |',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_due_contract_identity_api_audit_and_non_goals_are_explicit(): void
    {
        $document = $this->document();

        foreach ([
            '`is_all_day: true`',
            '`due_date` in ISO `YYYY-MM-DD`',
            '`is_all_day: false`',
            'stored in UTC',
            'club timezone',
            'Overdue is computed, never stored as a mutable status.',
            '`normal`, `high`, and `urgent`',
            'optimistic concurrency `lock_version`',
            'stable numeric backend IDs',
            'Duplicate titles are valid',
            'GET /api/v1/clubs/{club}/reminders',
            'POST /api/v1/clubs/{club}/reminders/{reminder}/cancel',
            'No DELETE route is approved.',
            '`reminders.created`',
            '`reminders.cancelled`',
            'deterministic 409',
            'email;',
            'WhatsApp;',
            'member-specific assignments;',
            'reports;',
            'deletion.',
            'No delivery or notification claim is authorized.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_repository_truth_and_next_boundary_are_recorded(): void
    {
        $document = $this->document();

        foreach ([
            'Reminders can be created only after a verified persistence workflow is connected.',
            '`verifiedOperationalSourceConnected("reminders")` remains false',
            'Prototype arrays and preview controls exist',
            'No `ClubReminder` model',
            'Reports are outside this roadmap.',
            'Do not restore dummy reminders',
            '3GA authorizes 3GB only.',
            'It does not authorize runtime implementation.',
            'GO - Milestone 3GB may open',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_changed_file_scope_is_lifecycle_safe(): void
    {
        $commit = $this->findUniqueCommitBySubject(self::COMMIT_SUBJECT);

        if ($commit !== null) {
            $this->assertSameSorted(self::AUTHORIZED_FILES, $this->changedFilesInCommit($commit));
            $historical = $this->normalize($this->gitOutput(['show', $commit.':backend/docs/milestone_3ga_reminders_backend_activation_scope_gate.md']));
            $this->assertStringContainsString('3GA authorizes 3GB only.', $historical);
            $this->assertStringContainsString('No runtime Reminders authorization', $historical);

            return;
        }

        $this->assertSame(self::BASE_COMMIT, trim($this->gitOutput(['rev-parse', 'HEAD'])));
        $this->assertSameSorted(self::AUTHORIZED_FILES, $this->currentChangedFiles());
    }

    public function test_no_runtime_reminders_files_or_routes_are_authorized(): void
    {
        $commit = $this->findUniqueCommitBySubject(self::COMMIT_SUBJECT);
        $files = $commit === null ? $this->currentChangedFiles() : $this->changedFilesInCommit($commit);

        foreach ($files as $file) {
            $this->assertFalse(str_starts_with($file, 'backend/app/'));
            $this->assertFalse(str_starts_with($file, 'backend/database/'));
            $this->assertNotSame('backend/routes/api.php', $file);
            $this->assertNotSame('app/page.tsx', $file);
        }
    }

    private function document(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    /** @param array<int, string> $expected @param array<int, string> $actual */
    private function assertSameSorted(array $expected, array $actual): void
    {
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    private function findUniqueCommitBySubject(string $subject): ?string
    {
        $matches = [];
        foreach (array_filter(explode("\n", $this->gitOutput(['log', '--all', '--oneline']))) as $line) {
            [$shortHash, $foundSubject] = array_pad(explode(' ', $line, 2), 2, '');
            if ($foundSubject === $subject) {
                $matches[] = trim($this->gitOutput(['rev-parse', $shortHash]));
            }
        }
        $this->assertLessThanOrEqual(1, count($matches));

        return $matches[0] ?? null;
    }

    /** @return array<int, string> */
    private function changedFilesInCommit(string $commit): array
    {
        return array_values(array_filter(explode("\n", $this->gitOutput([
            'diff-tree', '--no-commit-id', '--name-only', '-r', $commit,
        ]))));
    }

    /** @return array<int, string> */
    private function currentChangedFiles(): array
    {
        $files = [];
        foreach (array_filter(explode("\n", $this->gitOutput(['status', '--porcelain=v1']))) as $line) {
            $path = trim(substr($line, 3));
            if (str_contains($path, ' -> ')) {
                $path = substr($path, strpos($path, ' -> ')+4);
            }
            $files[] = str_replace('\\', '/', $path);
        }

        return $files;
    }

    private function gitOutput(array $arguments): string
    {
        $repoRoot = realpath($this->repoPath) ?: $this->repoPath;
        $command = array_merge(['git', '-C', $repoRoot], $arguments);
        $escaped = implode(' ', array_map('escapeshellarg', $command));
        exec($escaped.' 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, $escaped."\n".implode("\n", $output));

        return implode("\n", $output);
    }

    private function normalize(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
