<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3iiziProductionAccountingIntegrationAuthorityAndDeliveryContractTest extends TestCase
{
    private string $contract = 'docs/finance/milestone-3iizi-production-accounting-integration-authority-and-delivery-contract.md';

    private function repositoryFile(string $path): string
    {
        return dirname(base_path()).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function contract(): string
    {
        $path = $this->repositoryFile($this->contract);
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    public function test_contract_records_milestone_identity_dependency_and_current_runtime_boundary(): void
    {
        $content = $this->contract();

        foreach ([
            'docs-and-tests-only',
            '`bc5ba7967591ffef46736691bd6b8beef0e42fba`',
            'Milestone 3IIZH — Charge and Payment Accounting Integration is the completed dependency',
            'accounting integration remains disabled by default',
            'production configuration authority is not currently implemented',
            'This milestone introduces no runtime behavior',
        ] as $required) {
            $this->assertStringContainsString($required, $content);
        }
    }

    public function test_contract_approves_dual_control_and_future_permission_without_a_grant(): void
    {
        $content = $this->contract();

        foreach ([
            'Only a President or Treasurer',
            'one proposer and one independent approver',
            'The same user must never perform both roles',
            '`finance.accounting.integration.configure`',
            'Possession of this permission alone is insufficient',
            'does not seed, grant, or assign the permission to any default role',
            'no administrator bypass and no single-user approval',
        ] as $required) {
            $this->assertStringContainsString($required, $content);
        }
    }

    public function test_contract_defines_readiness_reconciliation_and_approval_prerequisites(): void
    {
        $content = $this->contract();

        foreach ([
            'Readiness is read-only',
            '`Ready`',
            '`Not ready`',
            '`Already active`',
            '`Revoked`',
            '`Missing mappings`',
            '`Invalid mappings`',
            '`Unintegrated source inside coverage`',
            '`Reconciliation defect`',
            '`Closed or missing accounting period`',
            '`Opening package not posted`',
            'the club accounting cutover package is posted',
            'the opening journal exists and remains valid',
            '`MEMBER-RECEIVABLES` exists and is active',
            'existing accounting reconciliation service reports no blocking defect',
            'Any failure stops approval without partial persistence',
        ] as $required) {
            $this->assertStringContainsString($required, $content);
        }
    }

    public function test_contract_defines_lifecycle_staleness_idempotency_concurrency_and_audit(): void
    {
        $content = $this->contract();

        foreach ([
            'pending → approved',
            'pending → rejected',
            'pending → withdrawn',
            'terminal and immutable',
            'A stale proposal cannot be approved',
            'canonical proposal UUID and client request UUID',
            'exact replay behavior and changed-payload conflict behavior',
            'optimistic locking and club-level serialization',
            'database uniqueness as the final safeguard',
            'append-only audit record',
            'before and after state',
            'Audit records must not contain private member payment data',
        ] as $required) {
            $this->assertStringContainsString($required, $content);
        }
    }

    public function test_candidate_api_is_documented_but_not_registered_and_statements_remain_blocked(): void
    {
        $content = $this->contract();

        foreach ([
            '/api/v1/clubs/{club}/finance/accounting/integration',
            'GET    /readiness',
            'POST   /mapping-proposals',
            'POST   /proposals/{proposal}/approve',
            'not registered routes in 3IIZI',
            'Club Account Statement projection, statement APIs, and statement frontend work remain blocked',
            'Milestone 3IIZJ — Production Accounting Integration Configuration and Activation Narrow Implementation',
        ] as $required) {
            $this->assertStringContainsString($required, $content);
        }

        $repoRoot = dirname(base_path());
        $changedIn3iizi = shell_exec('git -C '.escapeshellarg($repoRoot).' diff-tree --no-commit-id --name-only -r 3d64f1ab58c69a761bf55661e0d16b122a4e4d0a') ?? '';
        $this->assertStringNotContainsString('backend/routes/api.php', $changedIn3iizi);

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'finance/accounting/integration'))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->sort()
            ->values()
            ->all();
        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/finance/accounting/integration/configuration',
            'GET|HEAD api/v1/clubs/{club}/finance/accounting/integration/readiness',
            'POST api/v1/clubs/{club}/finance/accounting/integration/activation-proposals',
            'POST api/v1/clubs/{club}/finance/accounting/integration/mapping-proposals',
            'POST api/v1/clubs/{club}/finance/accounting/integration/proposals/{proposal}/approve',
            'POST api/v1/clubs/{club}/finance/accounting/integration/proposals/{proposal}/reject',
            'POST api/v1/clubs/{club}/finance/accounting/integration/proposals/{proposal}/withdraw',
            'POST api/v1/clubs/{club}/finance/accounting/integration/revocation-proposals',
        ], $routes);
    }

    public function test_milestone_scope_allows_only_the_contract_and_focused_test(): void
    {
        $content = $this->contract();

        $this->assertStringContainsString('adds only this document and its focused feature test', $content);
        $this->assertStringContainsString('adds no migration, model, controller, request, resource, service, route, permission seed, command, job, scheduler, frontend, deployment change, or other runtime file', $content);

        $repoRoot = dirname(base_path());
        $allowed = [
            'backend/tests/Feature/Milestone3iiziProductionAccountingIntegrationAuthorityAndDeliveryContractTest.php',
            'docs/finance/milestone-3iizi-production-accounting-integration-authority-and-delivery-contract.md',
        ];
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';
        $changed = collect(preg_split('/\R/', trim($status)))->filter()->map(function (string $line): string {
            $path = preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line;

            return str_replace('\\', '/', trim($path));
        })->values()->all();

        $this->assertSame([], array_values(array_diff($changed, $allowed)));
        $this->assertNotContains('backend/routes/api.php', $changed);
        $this->assertNotContains('backend/app', $changed);
    }
}
