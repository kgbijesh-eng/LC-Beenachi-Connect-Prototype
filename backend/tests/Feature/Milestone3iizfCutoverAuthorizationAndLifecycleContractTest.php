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
            '| President |',
            'no club body, delegate, or other officer substitutes for the President',
            'Technical operator',
            'Normal Member',
            'Explicitly denied every cutover permission',
            'Conflicts are evaluated by authenticated actor identity',
            'the appointed preparer may supersede only a `DRAFT` or `PREPARED` package',
            'once a package is `INTERNALLY_VERIFIED`, the preparer has no supersession authority',
            'each holding `rollback-authorize`',
            'Either the President or independent CA may revoke their own unconsumed',
            'one withdrawal immediately invalidates',
            'after `POSTED`, supersession is prohibited',
            'The Treasurer/preparer, Secretary/internal verifier, Normal Member, and technical operator have no rollback-authorize',
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
    }

    public function test_3iizf_contract_does_not_define_an_operational_delivery_surface(): void
    {
        $content = file_get_contents($this->repositoryFile($this->contract));
        $this->assertStringContainsString('No transition in this table creates an API or command in this milestone', $content);
        $this->assertCount(0, collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'cutover')));
    }
}
