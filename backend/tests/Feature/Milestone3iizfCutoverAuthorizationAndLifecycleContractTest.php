<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3iizfCutoverAuthorizationAndLifecycleContractTest extends TestCase
{
    private string $contract = 'docs/finance/club-accounting-cutover-package-activation-contract.md';

    private function repositoryFile(string $path): string
    {
        return dirname(base_path()).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function test_contract_approves_exact_role_permission_and_separation_boundaries(): void
    {
        $content = file_get_contents($this->repositoryFile($this->contract));

        foreach ([
            'Approved authorization and lifecycle contract',
            '`finance.cutover.package.prepare`',
            '`finance.cutover.package.validate`',
            '`finance.cutover.package.verify`',
            '`finance.cutover.package.accounting-review`',
            '`finance.cutover.package.business-approve`',
            '`finance.cutover.package.dry-run-accept`',
            '`finance.cutover.package.activate-authorize`',
            '`finance.cutover.package.execute`',
            '`finance.cutover.package.rollback-authorize`',
            '`finance.cutover.package.view-restricted-manifest`',
            'Treasurer / appointed preparer',
            'Secretary / appointed internal verifier',
            'Independent Chartered Accountant / accounting reviewer',
            'President / authorised club body',
            'Technical operator',
            'Normal Member',
            'Explicitly denied every cutover permission',
            'Conflicts are evaluated by authenticated actor identity',
        ] as $required) {
            $this->assertStringContainsString($required, $content);
        }
    }

    public function test_contract_defines_each_controlled_transition_and_audit_requirement(): void
    {
        $content = file_get_contents($this->repositoryFile($this->contract));

        foreach ([
            '`DRAFT` | `PREPARED`',
            '`PREPARED` | `INTERNALLY_VERIFIED`',
            '`INTERNALLY_VERIFIED` | `ACCOUNTING_REVIEWED`',
            '`ACCOUNTING_REVIEWED` | `BUSINESS_APPROVED`',
            '`BUSINESS_APPROVED` | `DRY_RUN_ACCEPTED`',
            '`DRY_RUN_ACCEPTED` | `ACTIVATION_AUTHORIZED`',
            '`ACTIVATION_AUTHORIZED` | `POSTED`',
            'Required audit events',
            'append-only events',
            'prior/resulting state and lock version',
            'Sensitive evidence content is prohibited in audit payloads',
        ] as $required) {
            $this->assertStringContainsString($required, $content);
        }
    }

    public function test_contract_milestone_adds_no_operational_surface_or_permission_grant(): void
    {
        $milestone = file_get_contents($this->repositoryFile('docs/finance/milestone-3iizf-cutover-authorization-and-lifecycle-contract.md'));
        foreach (['DR-033 remains Pending', 'adds no activation', 'API route', 'opening-journal posting', 'accounting-coverage change', 'permission seed'] as $required) {
            $this->assertStringContainsString($required, $milestone);
        }

        $cutoverRoutes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'cutover'));
        $this->assertCount(0, $cutoverRoutes);
        $seeder = file_get_contents(base_path('database/seeders/DefaultRolesAndPermissionsSeeder.php'));
        $this->assertStringNotContainsString('finance.cutover.package.prepare', $seeder);
        $this->assertStringNotContainsString('finance.cutover.package.activate-authorize', $seeder);
    }

    public function test_3iizf_changes_are_limited_to_contract_documents_and_test(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' branch --show-current') ?? '');
        if ($branch !== 'milestone-3iizf-cutover-authorization-and-lifecycle-contract') {
            $this->assertTrue(true);

            return;
        }

        $allowed = [
            'backend/tests/Feature/Milestone3iizfCutoverAuthorizationAndLifecycleContractTest.php',
            'docs/finance/club-accounting-cutover-decision-register.md',
            'docs/finance/club-accounting-cutover-package-activation-contract.md',
            'docs/finance/milestone-3iizf-cutover-authorization-and-lifecycle-contract.md',
        ];
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';
        $changed = collect(preg_split('/\R/', trim($status)))->filter()->map(function (string $line): string {
            $path = preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line;

            return str_replace('\\', '/', trim($path));
        })->values()->all();

        $this->assertSame([], array_values(array_diff($changed, $allowed)));
        $this->assertNotContains('backend/routes/api.php', $changed);
        $this->assertNotContains('backend/database/seeders/DefaultRolesAndPermissionsSeeder.php', $changed);
        $this->assertNotContains('app/page.tsx', $changed);
    }
}
