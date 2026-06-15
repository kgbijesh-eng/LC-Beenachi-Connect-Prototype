<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3emMemberDirectorySelectedMemberReadFailureNarrowImplementationTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3em_member_directory_selected_member_read_failure_narrow_implementation.md';
    private string $appPagePath = __DIR__.'/../../../app/page.tsx';

    public function test_3em_creates_implementation_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EM — Member Directory Selected Member Read Failure Narrow Implementation',
            '3EL is closed, pushed, remote verified, and tracking clean',
            '3EL authorized the narrow selected-member read failure fix',
            '3EJ overall result remains FAIL',
            '3EK locked the 3EJ controlled-use failure record',
            'human operator controlled-use blocking defect',
            'Area: Member Directory / selected member read / member profile context',
            'MEM-002',
            'MEM-003',
            'MEM-004',
            'MEM-001',
            'Lion Secretary User',
            'Lion Treasurer User',
            'Lion Ajith Kumar',
            'Lion Bijesh',
            'Lion Bijesh / MEM-001 - President',
            'Selected member was not found in backend member records.',
            'Future Fixed Behavior',
            'Implementation Summary of the Narrow Fix',
            'No backend runtime fix was implemented',
            'No data model/migration/package/lock file changes',
            'No human/operator retest was executed',
            'Controlled internal MVP remains blocked',
            'Limited internal member pilot remains not allowed',
            'Production/SaaS/deployment/public launch remain NO-GO',
            'Milestone 3EN — Member Directory Selected Member Read Failure Fix Closure Review',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3em_app_page_contains_runtime_fix_evidence(): void
    {
        $this->assertFileExists($appPagePath = $this->appPagePath);
        $code = file_get_contents($appPagePath);

        // 1. selectedMember state declaration
        $this->assertTrue(
            str_contains($code, 'const [selectedMember, setSelectedMember] = useState') ||
            str_contains($code, 'const [selectedMember,setSelectedMember]=useState'),
            'app/page.tsx must contain selectedMember state declaration'
        );

        // 2. setSelectedMember usage
        $this->assertTrue(
            str_contains($code, 'setSelectedMember('),
            'app/page.tsx must call setSelectedMember'
        );

        // 3. MembersList accepting/passing selected member selection callback
        $this->assertTrue(
            str_contains($code, 'onSelectMember?:') ||
            str_contains($code, 'onSelectMember :') ||
            str_contains($code, 'onSelectMember?: (member: Member) => void'),
            'app/page.tsx MembersList must accept an onSelectMember callback prop'
        );

        // 4. Click handler setting selectedMember from clicked member
        $this->assertTrue(
            str_contains($code, 'onSelectMember(member)') ||
            str_contains($code, 'onSelectMember?.(member)') ||
            str_contains($code, 'onSelectMember('),
            'app/page.tsx row click handler must call onSelectMember callback'
        );

        // 5. MemberProfile receiving selectedMember falling back to activeMember / nullish fallback
        $this->assertTrue(
            str_contains($code, 'member={selectedMember ?? activeMember}') ||
            str_contains($code, 'member={selectedMember || activeMember}'),
            'app/page.tsx must pass selectedMember ?? activeMember or selectedMember || activeMember fallback to MemberProfile'
        );

        // 6. Direct My Profile navigation clearing selectedMember
        $this->assertTrue(
            str_contains($code, 'setSelectedMember(null)'),
            'app/page.tsx must clear selectedMember to null on navigation'
        );

        // 7. Role switch clearing selectedMember
        // We verify that setSelectedMember(null) occurs inside switchRole
        $switchRolePos = strpos($code, 'function switchRole(');
        $this->assertNotFalse($switchRolePos, 'app/page.tsx must define function switchRole');

        $switchRoleCode = substr($code, $switchRolePos, 300);
        $this->assertTrue(
            str_contains($switchRoleCode, 'setSelectedMember(null)'),
            'switchRole function must clear selectedMember to null'
        );
    }

    public function test_3em_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3em-member-directory-selected-member-read-failure-narrow-implementation') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'app/page.tsx',
            'backend/docs/milestone_3em_member_directory_selected_member_read_failure_narrow_implementation.md',
            'backend/tests/Feature/Milestone3emMemberDirectorySelectedMemberReadFailureNarrowImplementationTest.php',
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EM may change only app/page.tsx, 3EM docs, and 3EM tests.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EM.");
        }
    }

    private function scopeDocument(): string
    {
        $this->assertFileExists($this->docPath);
        return file_get_contents($this->docPath);
    }

    private function currentChangedFiles(): array
    {
        $repoRoot = dirname(__DIR__, 3);
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';

        return collect(preg_split('/\R/', trim($status)))
            ->filter()
            ->map(function (string $line): string {
                $path = preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line;
                $path = trim($path);
                if (str_contains($path, ' -> ')) {
                    $path = trim(substr($path, strrpos($path, ' -> ') + 4));
                }

                return str_replace('\\', '/', $path);
            })
            ->values()
            ->all();
    }

    private function blockedPaths(): array
    {
        return [
            'backend/routes/api.php',
            'backend/app/Http/Controllers',
            'backend/app/Http/Requests',
            'backend/app/Http/Resources',
            'backend/app/Services',
            'backend/app/Models',
            'backend/database/migrations',
            'backend/database/seeders',
            'backend/database/factories',
            'backend/app/Policies',
            'backend/app/Providers',
            'backend/app/Http/Middleware',
            'backend/config',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'pnpm-lock.yaml',
            'yarn.lock',
            'task.md',
            'walkthrough.md',
        ];
    }

    private function containsPathOrChild(array $changed, string $blockedPath): bool
    {
        return collect($changed)->contains(function (string $path) use ($blockedPath): bool {
            return $path === $blockedPath || str_starts_with($path, rtrim($blockedPath, '/').'/');
        });
    }
}
