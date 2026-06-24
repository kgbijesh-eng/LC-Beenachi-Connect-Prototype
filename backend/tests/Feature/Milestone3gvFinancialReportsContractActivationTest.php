<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class Milestone3gvFinancialReportsContractActivationTest extends TestCase
{
    private const BASE = '653cd5448108094e8314570cb681a2b6fad2c249';
    private const SUBJECT = 'Approve financial reports activation contract';
    private const FILES = [
        'backend/docs/milestone_3gv_financial_reports_contract_activation.md',
        'backend/tests/Feature/Milestone3gvFinancialReportsContractActivationTest.php',
    ];

    public function test_contract_approves_only_proven_financial_reports_and_rules(): void
    {
        $doc = file_get_contents(__DIR__.'/../../docs/milestone_3gv_financial_reports_contract_activation.md');
        foreach ([
            self::BASE,
            'contract-only',
            '`collections-summary`',
            '`member-outstanding-balances`',
            '`charge-collection-status`',
            '`payment-register`',
            '`receipt-register`',
            'integer paise',
            '`INR`',
            'only allocations whose payment status is `recorded` count',
            "voided payment's original receipt remains stable",
            'President/Admin, Secretary, Treasurer',
            'Normal Member denied',
            'Cross-club rows must be impossible',
            'irrelevant or unknown filters return 422',
            'No export, PDF, Excel, CSV',
            'GO - 3GW may open',
        ] as $expected) {
            $this->assertStringContainsStringIgnoringCase($expected, $doc);
        }
    }

    public function test_changed_file_scope_is_lifecycle_safe(): void
    {
        $root = dirname(__DIR__, 3);
        $commit = $this->findCommit($root);
        if ($commit === null) {
            $this->assertSame(self::BASE, trim($this->git($root, ['rev-parse', 'HEAD'])));
            $files = $this->gitLines($root, ['status', '--porcelain=v1', '-uall']);
            $files = array_map(fn (string $line) => str_replace('\\', '/', substr($line, 3)), $files);
        } else {
            $files = $this->gitLines($root, ['diff-tree', '--no-commit-id', '--name-only', '-r', $commit]);
            $historical = $this->git($root, ['show', $commit.':backend/docs/milestone_3gv_financial_reports_contract_activation.md']);
            $this->assertStringContainsString('GO - 3GW may open', $historical);
        }
        sort($files);
        $expected = self::FILES;
        sort($expected);
        $this->assertSame($expected, $files);
    }

    private function findCommit(string $root): ?string
    {
        $matches = [];
        foreach ($this->gitLines($root, ['log', '--all', '--format=%H%x09%s']) as $line) {
            [$hash, $subject] = array_pad(explode("\t", $line, 2), 2, '');
            if ($subject === self::SUBJECT) {
                $matches[] = $hash;
            }
        }
        $this->assertLessThanOrEqual(1, count($matches));
        return $matches[0] ?? null;
    }

    private function gitLines(string $root, array $args): array
    {
        return array_values(array_filter(explode("\n", trim($this->git($root, $args)))));
    }

    private function git(string $root, array $args): string
    {
        $process = new Process(['git', ...$args], $root);
        $process->setTimeout(30);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        return str_replace("\r\n", "\n", $process->getOutput());
    }
}
