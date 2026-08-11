<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3fxProjectsActivitiesFrontendIntegrationTest extends TestCase
{
    private const BASE_COMMIT = '096eb072b66376cd2c05540c8f39aa9df2477cb0';
    private const COMMIT_SUBJECT = 'Connect projects activities frontend';
    private const AUTHORIZED_FILES = [
        'app/page.tsx',
        'backend/tests/Feature/Milestone3fxProjectsActivitiesFrontendIntegrationTest.php',
    ];

    private string $repoPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoPath = dirname(__DIR__, 3);
    }

    public function test_frontend_uses_club_scoped_projects_api_and_stable_ids(): void
    {
        $source = $this->milestoneSource();

        $this->assertStringContainsString('`${API_BASE_URL}/clubs/${activeClubId}/projects?${params}`', $source);
        $this->assertStringContainsString('`${API_BASE_URL}/clubs/${clubId}/projects/${id}`', $source);
        $this->assertStringContainsString('key={project.id}', $source);
        $this->assertStringContainsString('View Record #{project.id}', $source);
        $this->assertStringContainsString('Stable backend ID', $source);
    }

    public function test_frontend_exposes_approved_role_safe_workflows_and_truthful_states(): void
    {
        $source = $this->milestoneSource();

        $this->assertStringContainsString('const canManage = role === "president" || role === "secretary"', $source);
        $this->assertStringContainsString('Backend read-only', $source);
        $this->assertStringContainsString('Create Draft', $source);
        foreach (['plan', 'start', 'complete', 'cancel'] as $transition) {
            $this->assertStringContainsString('"'.$transition.'"', $source);
        }
        $this->assertStringContainsString('statusCode === 403 ? "forbidden"', $source);
        $this->assertStringContainsString('statusCode === 409 ? "conflict"', $source);
        $this->assertStringContainsString('No Projects or Activities match the current filters.', $source);
        $this->assertStringContainsString('The backend did not save this record.', $source);
        $this->assertStringContainsString('{ label: "Projects / Activities", screen: "projects" }', $source);
    }

    public function test_frontend_has_no_projects_dummy_fallback(): void
    {
        $source = $this->milestoneSource();
        $connectedStart = strpos($source, 'function ProjectsActivities({ role, setScreen }');
        $activityCardStart = strpos($source, 'function ActivityCard');
        $this->assertNotFalse($connectedStart);
        $this->assertNotFalse($activityCardStart);
        $connected = substr($source, $connectedStart, $activityCardStart - $connectedStart);

        $this->assertStringNotContainsString('function LegacyProjectsActivities', $source);
        $this->assertStringNotContainsString('activities.map(', $connected);
        $this->assertStringNotContainsString('localStorage', $connected);
        $this->assertStringNotContainsString('verifiedOperationalSourceConnected("projects")', $connected);
        $this->assertStringContainsString('This screen uses club-scoped backend records only.', $connected);
    }

    public function test_3fx_changed_file_scope_is_lifecycle_safe(): void
    {
        $commit = $this->findUniqueCommitBySubject(self::COMMIT_SUBJECT);
        if ($commit !== null) {
            $this->assertSameSorted(self::AUTHORIZED_FILES, $this->changedFilesInCommit($commit));
            $source = $this->normalize($this->gitOutput(['show', $commit.':app/page.tsx']));
            $this->assertStringContainsString('function ProjectsActivities({ role, setScreen }', $source);
            $this->assertStringContainsString('Backend management', $source);
            return;
        }

        $this->assertSame(self::BASE_COMMIT, trim($this->gitOutput(['rev-parse', 'HEAD'])));
        $this->assertSameSorted(self::AUTHORIZED_FILES, $this->currentChangedFiles());
    }

    private function milestoneSource(): string
    {
        $commit = $this->findUniqueCommitBySubject(self::COMMIT_SUBJECT);
        return $commit !== null
            ? $this->normalize($this->gitOutput(['show', $commit.':app/page.tsx']))
            : $this->normalize((string) file_get_contents($this->repoPath.'/app/page.tsx'));
    }

    private function findUniqueCommitBySubject(string $subject): ?string
    {
        $matches = [];
        foreach (array_filter(explode("\n", $this->gitOutput(['log', '--all', '--oneline']))) as $line) {
            [$hash, $foundSubject] = array_pad(explode(' ', $line, 2), 2, '');
            if ($foundSubject === $subject) $matches[] = trim($this->gitOutput(['rev-parse', $hash]));
        }
        $this->assertLessThanOrEqual(1, count($matches));
        return $matches[0] ?? null;
    }

    private function changedFilesInCommit(string $commit): array
    {
        $files = array_values(array_filter(explode("\n", $this->gitOutput(['diff-tree', '--no-commit-id', '--name-only', '-r', $commit]))));
        sort($files);
        return $files;
    }

    private function currentChangedFiles(): array
    {
        $files = [];
        foreach (array_filter(explode("\n", $this->gitOutput(['status', '--porcelain=v1', '-uall']))) as $line) {
            $path = trim(substr($line, 3));
            if (str_contains($path, ' -> ')) $path = substr($path, strpos($path, ' -> ') + 4);
            $files[] = str_replace('\\', '/', $path);
        }
        sort($files);
        return $files;
    }

    private function assertSameSorted(array $expected, array $actual): void
    {
        sort($expected); sort($actual);
        $this->assertSame($expected, $actual);
    }

    private function gitOutput(array $arguments): string
    {
        $command = array_merge(['git', '-C', realpath($this->repoPath) ?: $this->repoPath], $arguments);
        exec(implode(' ', array_map('escapeshellarg', $command)).' 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $output));
        return implode("\n", $output);
    }

    private function normalize(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
