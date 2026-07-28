<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3enMemberDirectorySelectedMemberReadFailureFixClosureReviewTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3en_member_directory_selected_member_read_failure_fix_closure_review.md';

    private string $appPagePath = __DIR__.'/../../../app/page.tsx';

    private string $authSessionPath = __DIR__.'/../../../app/DevAuthSession.tsx';

    public function test_3en_creates_closure_review_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EN — Member Directory Selected Member Read Failure Fix Closure Review',
            '3EM is closed, pushed, remote verified, and tracking clean',
            '65540ad9806c7f08b7986476095c119d6e34fda7',
            'narrow implementation',
            'narrow runtime fix only in',
            'backend/docs/milestone_3em_member_directory_selected_member_read_failure_narrow_implementation.md',
            'backend/tests/Feature/Milestone3emMemberDirectorySelectedMemberReadFailureNarrowImplementationTest.php',
            'did not modify backend runtime files',
            'did not modify package/lock files',
            'did not modify data model, migrations, seeders, routes, controllers, models, resources, requests, or services',
            'did not execute human/operator retest',
            '3EJ overall result remains FAIL',
            '3EK locked the failure record',
            '3EL authorized the fix',
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
            '3EM Implementation Closure Summary',
            'selectedMember',
            'selectAndOpenMemberProfile',
            'directory',
            'fallback',
            'clear',
            'switcher',
            'Expected Behavior After 3EM',
            'Secretary’s own read/detail page',
            'Treasurer’s own read/detail page',
            'Ajith Kumar’s own read/detail page',
            'Bijesh’s own read/detail page',
            'Known backend member records must not fall back to My Profile',
            'Known backend member records must not fall back to My Profile and must not show the warning: "Selected member was not found in backend member records."',
            '3EM Validation Record',
            'npm run build',
            'Focused milestone tests: **PASS**',
            'Full PHPUnit suite: **PASS**',
            '1487 tests, 18959 assertions',
            'accepted as code-complete pending human/operator retest',
            'Controlled internal MVP execution: FAIL (Blocked)',
            'Limited internal member pilot: NO-GO',
            'Production / SaaS / Deployment / Public Launch: NO-GO',
            'Milestone 3EO — Member Directory Selected Member Read Human Operator Retest Scope Gate',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3en_app_page_contains_3em_runtime_fix_evidence(): void
    {
        $this->assertFileExists($appPagePath = $this->appPagePath);
        $code = file_get_contents($appPagePath);

        // 1. selectedMember state exists
        $this->assertTrue(
            str_contains($code, 'const [selectedMember, setSelectedMember] = useState') ||
            str_contains($code, 'const [selectedMember,setSelectedMember]=useState'),
            'app/page.tsx must contain selectedMember state'
        );

        // 2. selectAndOpenMemberProfile directory click path exists
        $this->assertTrue(
            str_contains($code, 'selectAndOpenMemberProfile'),
            'app/page.tsx must define selectAndOpenMemberProfile'
        );

        // 3. Directory selection never falls back to a caller-supplied active member for personal profile reads.
        $this->assertStringContainsString(
            'selectedMember ? <MemberProfile key={selectedMember.backendId} member={selectedMember} role={role} /> : <PersonalMemberProfile role={role} />',
            $code
        );
        $this->assertStringContainsString('const clubId = memberships?.[0]?.club?.id;', $code);
        $this->assertStringContainsString('if (role === "member") {', $code);
        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + clubId + "/members/me"', $code);
        $this->assertStringContainsString(
            'if (!selfRecord || Number(selfRecord.id) !== Number(linkedMemberInfo.id))',
            $code
        );
        $this->assertStringContainsString('if (importedRecords.length !== 47)', $code);
        $this->assertStringContainsString(
            'records = importedRecords.map((record) => Number(record.id) === Number(selfRecord.id) ? selfRecord : record);',
            $code
        );
        $this->assertStringContainsString('filtered.map((member) => role === "member" ? (', $code);
        $this->assertStringContainsString('<div key={member.id} className="staticRow">', $code);
        $this->assertStringNotContainsString('records = [selfRecord];', $code);
        $this->assertStringNotContainsString('API_BASE_URL + "/clubs/" + loadedClubId + "/members/me"', $code);

        // 4. general/direct navigation clears selectedMember
        $this->assertTrue(
            str_contains($code, 'setSelectedMember(null)'),
            'app/page.tsx must clear selectedMember to null'
        );

        // 5. The verified session remounts identity-scoped workspace state.
        $this->assertStringContainsString('const auth = useDevAuth();', $code);
        $this->assertStringContainsString(
            'if (!auth.session) {',
            $code
        );
        $this->assertStringContainsString(
            'return <PrototypeWorkspace key={auth.session.identity} identity={auth.session.identity} role={auth.session.role} initialScreen="dashboard" />;',
            $code
        );
        $this->assertStringContainsString(
            'const [selectedMember, setSelectedMember] = useState<Member | null>(null);',
            $code
        );

        $this->assertFileExists($authSessionPath = $this->authSessionPath);
        $authCode = file_get_contents($authSessionPath);
        $this->assertStringContainsString('const verified = await verifySession(payload.token);', $authCode);
        $this->assertStringContainsString('setSession(verified);', $authCode);
        $this->assertStringNotContainsString('switchIdentity', $authCode);
    }

    public function test_3en_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3en-member-directory-selected-member-read-failure-fix-closure-review') {
            $this->assertTrue(true);

            return;
        }

        $allowed = [
            'backend/docs/milestone_3en_member_directory_selected_member_read_failure_fix_closure_review.md',
            'backend/tests/Feature/Milestone3enMemberDirectorySelectedMemberReadFailureFixClosureReviewTest.php',
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EN may change only approved 3EN docs/tests.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EN.");
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
            'app/page.tsx',
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
