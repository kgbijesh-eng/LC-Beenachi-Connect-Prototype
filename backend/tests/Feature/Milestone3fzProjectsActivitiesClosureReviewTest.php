<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3fzProjectsActivitiesClosureReviewTest extends TestCase
{
    private const BASE = '1f6c4ffddb50018ea5d04b8a92541c8d5f2a7ce9';
    private const SUBJECT = 'Close projects activities backend activation';
    private const FILES = ['backend/docs/milestone_3fz_projects_activities_closure_review.md', 'backend/tests/Feature/Milestone3fzProjectsActivitiesClosureReviewTest.php'];
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 3);
    }

    public function test_closure_document_contains_every_required_section_and_verified_hash(): void
    {
        $document = $this->document();
        for ($section = 1; $section <= 29; $section++) $this->assertStringContainsString('## '.$section.'.', $document);
        foreach (['a18c77616ff4ac806920e75474c4f1765e970e53', 'f584592e5c0873d4ab94a8b7a76167b0bf7ccef0', 'c4c557a956bb082d2dbb842974bc5390a4a91426', '096eb072b66376cd2c05540c8f39aa9df2477cb0', '6b3a870f7b008bb63cac10a50f8e5b3e2990b0f1', self::BASE] as $hash) $this->assertStringContainsString($hash, $document);
    }

    public function test_closure_records_final_safety_contract(): void
    {
        $document = $this->document();
        foreach (['projects.view', 'projects.manage', 'No DELETE route exists', 'Cancelled Draft remains management-only', 'row locking and optimistic `lock_version`', 'Project `#10` and Activity `#11`', 'No Projects dummy fallback', '1440px, 900px and 390px', 'genuine PostgreSQL database was inspected read-only', 'no unresolved closure-critical defect', 'formally closed'] as $evidence) $this->assertStringContainsString($evidence, $document);
    }

    public function test_3fz_changes_only_the_two_authorized_files(): void
    {
        $commit = $this->commit();
        if ($commit !== null) {
            $this->sameFiles(self::FILES, $this->commitFiles($commit));
            return;
        }
        $this->assertSame(self::BASE, trim($this->git(['rev-parse', 'HEAD'])));
        $this->sameFiles(self::FILES, $this->workingFiles());
    }

    private function document(): string
    {
        $commit = $this->commit();
        return $commit ? $this->normalize($this->git(['show', $commit.':backend/docs/milestone_3fz_projects_activities_closure_review.md'])) : $this->normalize((string) file_get_contents($this->root.'/backend/docs/milestone_3fz_projects_activities_closure_review.md'));
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
