<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3fyProjectsActivitiesBrowserRoleVerificationTest extends TestCase
{
    private const BASE = '6b3a870f7b008bb63cac10a50f8e5b3e2990b0f1';
    private const SUBJECT = 'Fix projects activities verification defects';
    private const FILES = ['app/page.tsx', 'backend/docs/milestone_3fy_projects_activities_browser_role_verification.md', 'backend/tests/Feature/Milestone3fyProjectsActivitiesBrowserRoleVerificationTest.php'];
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 3);
    }

    public function test_document_records_actual_browser_role_workflow_and_responsive_evidence(): void
    {
        $document = $this->document();
        foreach (['President/Admin', 'Secretary', 'Treasurer', 'Normal Member', 'Unauthenticated', 'Inactive member', 'Another-club user', 'Create Project Draft', 'Create Activity Draft', 'Edit Draft exact ID', 'Planned to Active', 'Planned to Completed', 'Active to Completed', 'Cancel Draft', 'Cancel Planned', 'Cancel Active', 'Early completion', 'Stale lock version', '1440px', '900px', '390px', 'No document-level horizontal overflow'] as $evidence) {
            $this->assertStringContainsString($evidence, $document);
        }
    }

    public function test_document_records_identity_data_truth_and_findings(): void
    {
        $document = $this->document();
        foreach (['stable ID `13`', 'duplicate title records `#10` Project and `#11` Activity', 'no dummy Projects fallback', 'genuine empty state', 'explicit forbidden/conflict states', 'IN-SCOPE DEFECT — FIXED', 'OUT-OF-SCOPE FINDING — DEFERRED', 'BLOCKED — NOT SAFELY TESTABLE', 'PASS — Milestone 3FZ closure review may open'] as $evidence) {
            $this->assertStringContainsString($evidence, $document);
        }
    }

    public function test_network_failure_defect_has_explicit_recovery(): void
    {
        $source = $this->source();
        foreach (['async function safeProjectFetch', 'The selected record is unavailable.', 'The backend did not save this record.', 'The backend did not complete the ${action} transition.', 'if (!response) return;'] as $evidence) {
            $this->assertStringContainsString($evidence, $source);
        }
    }

    public function test_3fy_scope_is_lifecycle_safe(): void
    {
        $commit = $this->commit();
        if ($commit !== null) {
            $this->sameFiles(self::FILES, $this->commitFiles($commit));
            $this->assertStringContainsString('Milestone 3FZ closure review may open', $this->normalize($this->git(['show', $commit.':backend/docs/milestone_3fy_projects_activities_browser_role_verification.md'])));
            return;
        }
        $this->assertSame(self::BASE, trim($this->git(['rev-parse', 'HEAD'])));
        $this->sameFiles(self::FILES, $this->workingFiles());
    }

    private function document(): string
    {
        $commit = $this->commit();
        return $commit ? $this->normalize($this->git(['show', $commit.':backend/docs/milestone_3fy_projects_activities_browser_role_verification.md'])) : $this->normalize((string) file_get_contents($this->root.'/backend/docs/milestone_3fy_projects_activities_browser_role_verification.md'));
    }

    private function source(): string
    {
        $commit = $this->commit();
        return $commit ? $this->normalize($this->git(['show', $commit.':app/page.tsx'])) : $this->normalize((string) file_get_contents($this->root.'/app/page.tsx'));
    }

    private function commit(): ?string
    {
        $matches = [];
        foreach (array_filter(explode("\n", $this->git(['log', '--all', '--oneline']))) as $line) {
            [$hash, $subject] = array_pad(explode(' ', $line, 2), 2, '');
            if ($subject === self::SUBJECT) $matches[] = trim($this->git(['rev-parse', $hash]));
        }
        $this->assertLessThanOrEqual(1, count($matches));
        return $matches[0] ?? null;
    }

    private function commitFiles(string $commit): array
    {
        $files = array_values(array_filter(explode("\n", $this->git(['diff-tree', '--no-commit-id', '--name-only', '-r', $commit]))));
        sort($files); return $files;
    }

    private function workingFiles(): array
    {
        $files = [];
        foreach (array_filter(explode("\n", $this->git(['status', '--porcelain=v1', '-uall']))) as $line) $files[] = str_replace('\\', '/', trim(substr($line, 3)));
        sort($files); return $files;
    }

    private function sameFiles(array $expected, array $actual): void
    {
        sort($expected); sort($actual); $this->assertSame($expected, $actual);
    }

    private function git(array $args): string
    {
        $command = array_merge(['git', '-C', realpath($this->root) ?: $this->root], $args);
        exec(implode(' ', array_map('escapeshellarg', $command)).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
        return implode("\n", $output);
    }

    private function normalize(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
