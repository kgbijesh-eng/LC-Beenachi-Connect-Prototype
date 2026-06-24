<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class Milestone3gxFinancialReportsClosureReviewTest extends TestCase
{
    private const BASE='1ffea59e5fc00a8a7299eab04a216b6e0bdba9b1';
    private const SUBJECT='Close financial reports activation';
    private const FILES=[
        'backend/docs/milestone_3gx_financial_reports_closure_review.md',
        'backend/tests/Feature/Milestone3gxFinancialReportsClosureReviewTest.php',
    ];

    public function test_closure_records_complete_safe_financial_reports(): void
    {
        $doc=file_get_contents(__DIR__.'/../../docs/milestone_3gx_financial_reports_closure_review.md');
        foreach([
            self::BASE,'0e1d69253347b9b1d51c54e504cdef1808c664e3','`collections-summary`','`member-outstanding-balances`',
            '`charge-collection-status`','`payment-register`','`receipt-register`','integer paise','`INR`','assessed','collected',
            'outstanding','Voided payments are excluded','stable receipt ID','President/Admin, Secretary, and Treasurer','Normal Member',
            'stable member and charge IDs','duplicate names and titles','cross-club','privacy','Unknown or irrelevant filters return 422',
            'pagination','clears stale summary, rows, pagination','1440px, 900px, and 390px','read-only transaction','disposable SQLite',
            'no unresolved closure-critical defect','Phase 1 is formally closed','3GY must begin from the actual remotely verified 3GX closure commit',
        ] as $expected)$this->assertStringContainsStringIgnoringCase($expected,$doc);
    }

    public function test_implementation_contract_and_closed_deferrals_remain_present(): void
    {
        $root=dirname(__DIR__,3);
        $catalog=file_get_contents($root.'/backend/app/Services/Reports/ReportCatalog.php');
        $service=file_get_contents($root.'/backend/app/Services/Reports/FinancialReportService.php');
        $frontend=file_get_contents($root.'/app/ReportsBackend.tsx');
        foreach(['collections-summary','member-outstanding-balances','charge-collection-status','payment-register','receipt-register'] as $key)$this->assertStringContainsString($key,$catalog.$service);
        foreach(['status!==\'recorded\'','outstanding_paise','currency\'=>\'INR\'','voided_paise','receipt_number','per_page'] as $rule)$this->assertStringContainsString($rule,$service);
        foreach(['clearOutput();setSelected(def);setState("loading")','integer paise · INR · no exports','Report unavailable. Previous rows and totals were cleared.'] as $rule)$this->assertStringContainsString($rule,$frontend);
        $this->assertFileDoesNotExist($root.'/backend/database/migrations/2026_06_25_000001_create_report_results_table.php');
    }

    public function test_changed_file_scope_is_lifecycle_safe(): void
    {
        $root=dirname(__DIR__,3);$commit=$this->findCommit($root);
        if($commit===null){$this->assertSame(self::BASE,trim($this->git($root,['rev-parse','HEAD'])));$lines=array_values(array_filter(explode("\n",rtrim($this->git($root,['status','--porcelain=v1','-uall']),"\r\n"))));$files=array_map(fn($line)=>str_replace('\\','/',substr($line,3)),$lines);}
        else{$files=$this->gitLines($root,['diff-tree','--no-commit-id','--name-only','-r',$commit]);$historical=$this->git($root,['show',$commit.':backend/docs/milestone_3gx_financial_reports_closure_review.md']);$this->assertStringContainsString('Phase 1 is formally closed',$historical);}
        sort($files);$expected=self::FILES;sort($expected);$this->assertSame($expected,$files);
    }

    private function findCommit(string $root): ?string{$matches=[];foreach($this->gitLines($root,['log','--all','--format=%H%x09%s']) as $line){[$hash,$subject]=array_pad(explode("\t",$line,2),2,'');if($subject===self::SUBJECT)$matches[]=$hash;}$this->assertLessThanOrEqual(1,count($matches));return $matches[0]??null;}
    private function gitLines(string $root,array $args): array{return array_values(array_filter(explode("\n",trim($this->git($root,$args)))));}
    private function git(string $root,array $args): string{$process=new Process(['git',...$args],$root);$process->setTimeout(30);$process->run();$this->assertTrue($process->isSuccessful(),$process->getErrorOutput());return str_replace("\r\n","\n",$process->getOutput());}
}
