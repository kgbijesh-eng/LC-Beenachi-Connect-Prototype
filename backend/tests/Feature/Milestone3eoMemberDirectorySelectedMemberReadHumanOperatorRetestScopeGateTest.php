<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3eoMemberDirectorySelectedMemberReadHumanOperatorRetestScopeGateTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3eo_member_directory_selected_member_read_human_operator_retest_scope_gate.md';

    private string $appPagePath = __DIR__.'/../../../app/page.tsx';

    private string $authSessionPath = __DIR__.'/../../../app/DevAuthSession.tsx';

    public function test_3eo_creates_scope_gate_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3EO — Member Directory Selected Member Read Human Operator Retest Scope Gate',
            '3EN is closed, pushed, remote verified, and tracking clean',
            'c42eb58ef3d93d2410b02e1247462323e07041d9',
            '3EN closed 3EM as code-complete pending human/operator retest',
            '3EM implemented the narrow selected-member read fix',
            '65540ad9806c7f08b7986476095c119d6e34fda7',
            'app/page.tsx',
            'backend/docs/milestone_3em_member_directory_selected_member_read_failure_narrow_implementation.md',
            'backend/tests/Feature/Milestone3emMemberDirectorySelectedMemberReadFailureNarrowImplementationTest.php',
            '3EJ remains FAIL until human/operator retest proves otherwise',
            '3EK locked the failure record',
            '3EL authorized the fix',
            '3EO does not execute the retest',
            '3EO does not modify runtime files',
            '3EO does not modify',
            'Defect classification: Human operator controlled-use blocking defect',
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
            '“Selected member was not found in backend member records.”',
            'Retest Objective',
            'Human/operator retest role',
            'Human/operator retest entry point',
            'Required Retest Actions',
            'Pass/Fail Criteria',
            'Stop Condition',
            'Retest Evidence Requirements',
            'Controlled internal MVP remains blocked',
            'Limited internal member pilot remains not allowed',
            'Production/SaaS/deployment/public launch remain NO-GO',
            'Milestone 3EP — Member Directory Selected Member Read Human Operator Retest Execution',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3eo_app_page_contains_3em_runtime_fix_evidence(): void
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

        // 3. Directory selection and ownership-derived personal profile reads remain separate.
        $this->assertStringContainsString(
            'selectedMember ? <MemberProfile key={selectedMember.backendId} member={selectedMember} role={role} /> : <PersonalMemberProfile role={role} />',
            $code
        );
        $this->assertStringContainsString('const clubId = session.organisation.id;', $code);
        $this->assertStringContainsString('const activeMembership = session.membership;', $code);
        $this->assertStringNotContainsString('const clubId = memberships?.[0]?.club?.id;', $code);
        $this->assertStringContainsString('if (role === "member") {', $code);
        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + clubId + "/members/me"', $code);
        $this->assertStringContainsString('const selfRecord = profilePayload.member;', $code);
        $this->assertStringContainsString(
            'if (!isBackendMemberRecord(selfRecord) || Number(selfRecord.id) !== Number(linkedMemberInfo.id))',
            $code
        );
        $this->assertStringContainsString('throw new Error("No genuine club member profile is linked.");', $code);
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
        $this->assertStringContainsString('const verified = await verifySession(payload.token, controller.signal, attempt);', $authCode);
        $this->assertStringContainsString('if (generation.current !== attempt) return false;', $authCode);
        $this->assertStringContainsString('controller.abort()', $authCode);
        $this->assertStringContainsString('session.organisation.id', $authCode);
        $this->assertStringContainsString('setSession(verified);', $authCode);
        $this->assertStringNotContainsString('switchIdentity', $authCode);
    }

    public function test_3eo_current_file_changes_are_limited_to_approved_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3eo-member-directory-selected-member-read-human-operator-retest-scope-gate') {
            $this->assertTrue(true);

            return;
        }

        $allowed = [
            'backend/docs/milestone_3eo_member_directory_selected_member_read_human_operator_retest_scope_gate.md',
            'backend/tests/Feature/Milestone3eoMemberDirectorySelectedMemberReadHumanOperatorRetestScopeGateTest.php',
        ];

        $changed = $this->currentChangedFiles();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3EO may change only approved 3EO docs/tests.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3EO.");
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
