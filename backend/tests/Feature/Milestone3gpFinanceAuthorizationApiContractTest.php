<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class Milestone3gpFinanceAuthorizationApiContractTest extends TestCase
{
    public function test_contract_freezes_permissions_routes_money_and_conflicts(): void
    {
        $doc = file_get_contents(__DIR__.'/../../docs/milestone_3gp_finance_authorization_api_contract.md');

        foreach ([
            '`finance.self.view`', '`finance.club.view`', '`finance.manage`', '`finance.void`',
            'Secretary | yes | yes | yes | no',
            'integer `amount_paise`',
            '`*_display`',
            'GET /api/v1/clubs/{club}/finance/charges',
            'GET /api/v1/clubs/{club}/finance/me/summary',
            'POST  /api/v1/clubs/{club}/finance/members/{member}/payments',
            'POST  /api/v1/clubs/{club}/finance/payments/{payment}/void',
            'There is no DELETE',
            'Exactly one assignment mode is required',
            'Allocations must be unique, positive, same-club, same-member',
            'exact integer sum equals payment amount',
            'canonical request hash',
            'identical replay returns HTTP 200',
            'Reuse with a different canonical payload returns 409',
            'Receipt remains readable',
            'date ranges are inclusive',
            'may not exceed 366 days',
            'Reports `collections-summary`',
            'No runtime implementation is authorized in 3GP',
        ] as $expected) {
            $this->assertStringContainsStringIgnoringCase($expected, $doc);
        }

        foreach (['401 unauthenticated', '403 inactive', '404 unknown', '422 field validation', '409 stale version'] as $status) {
            $this->assertStringContainsString($status, $doc);
        }
    }

    public function test_3gp_and_3go_are_docs_tests_only_with_lifecycle_safe_scope(): void
    {
        $root = dirname(__DIR__, 3);
        $current = $this->filesForSubjectOrWorkingTree($root, 'Approve finance authorization and API contract');
        $expected = [
            'backend/docs/milestone_3gp_finance_authorization_api_contract.md',
            'backend/tests/Feature/Milestone3gpFinanceAuthorizationApiContractTest.php',
        ];
        sort($current); sort($expected);
        $this->assertSame($expected, $current);

        $log = $this->git($root, ['log', '--all', '--format=%H%x09%s']);
        $this->assertStringContainsString("\tAdd finance backend activation scope gate", $log);
    }

    private function filesForSubjectOrWorkingTree(string $root, string $subject): array
    {
        $log = $this->git($root, ['log', '--all', '--format=%H%x09%s']);
        $line = collect(preg_split('/\R/', trim($log)))->first(fn (string $row) => str_ends_with($row, "\t{$subject}"));
        if ($line) {
            [$hash] = explode("\t", $line, 2);
            return preg_split('/\R/', trim($this->git($root, ['diff-tree', '--no-commit-id', '--name-only', '-r', $hash])));
        }
        return array_map(
            fn (string $row) => str_replace('\\', '/', substr($row, 3)),
            preg_split('/\R/', trim($this->git($root, ['status', '--porcelain=v1'])))
        );
    }

    private function git(string $root, array $arguments): string
    {
        $process = new Process(['git', ...$arguments], $root);
        $process->setTimeout(30); $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        return str_replace("\r\n", "\n", $process->getOutput());
    }
}
