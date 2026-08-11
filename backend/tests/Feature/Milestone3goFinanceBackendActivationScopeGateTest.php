<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class Milestone3goFinanceBackendActivationScopeGateTest extends TestCase
{
    public function test_scope_gate_approves_safe_collection_architecture_and_deferrals(): void
    {
        $doc = file_get_contents(__DIR__.'/../../docs/milestone_3go_finance_backend_activation_scope_gate.md');

        foreach ([
            'member collections, not full accounting',
            'amount_paise',
            'JavaScript and PHP floating-point arithmetic are forbidden',
            'single-currency `INR`',
            'April–March',
            'membership_dues',
            'installation_contribution',
            'event_contribution',
            'service_project_contribution',
            'other_contribution',
            'Draft -> Issued',
            'Recorded -> Voided',
            'total allocation must exactly equal payment total',
            'unique club-scoped idempotency key',
            '`finance.self.view`',
            '`finance.club.view`',
            '`finance.manage`',
            '`finance.void`',
            'Secretary: self, club, manage; no void',
            'Normal Member: self only',
            'Financial Reports remain disabled',
            '`collections-summary` requires a separate post-3GU milestone',
            'no runtime Finance implementation',
        ] as $expected) {
            $this->assertStringContainsString($expected, $doc);
        }

        foreach (['double entry', 'payment gateways', 'receipt PDF', 'exports', 'deletion'] as $deferred) {
            $this->assertStringContainsStringIgnoringCase($deferred, $doc);
        }
    }

    public function test_scope_is_lifecycle_safe_and_contains_no_runtime_change(): void
    {
        $root = dirname(__DIR__, 3);
        $subject = 'Add finance backend activation scope gate';
        $log = $this->git($root, ['log', '--all', '--format=%H%x09%s']);
        $line = collect(preg_split('/\R/', trim($log)))->first(fn (string $row) => str_ends_with($row, "\t{$subject}"));

        if ($line) {
            [$hash] = explode("\t", $line, 2);
            $files = preg_split('/\R/', trim($this->git($root, ['diff-tree', '--no-commit-id', '--name-only', '-r', $hash])));
        } else {
            $status = preg_split('/\R/', trim($this->git($root, ['status', '--porcelain=v1'])));
            $files = array_map(fn (string $row) => str_replace('\\', '/', substr($row, 3)), $status);
        }

        $expected = [
            'backend/docs/milestone_3go_finance_backend_activation_scope_gate.md',
            'backend/tests/Feature/Milestone3goFinanceBackendActivationScopeGateTest.php',
        ];
        sort($expected);
        sort($files);
        $this->assertSame($expected, $files);
    }

    private function git(string $root, array $arguments): string
    {
        $process = new Process(['git', ...$arguments], $root);
        $process->setTimeout(30);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        return str_replace("\r\n", "\n", $process->getOutput());
    }
}
