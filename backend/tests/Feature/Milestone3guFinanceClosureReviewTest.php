<?php
namespace Tests\Feature;
use App\Models\Role;
use App\Services\Reports\ReportCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Route,Schema};
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Milestone3guFinanceClosureReviewTest extends TestCase {
 use RefreshDatabase;
 private const REPORT_KEYS=['membership-summary','events-register','projects-register','reminders-register','collections-summary','member-outstanding-balances','charge-collection-status','payment-register','receipt-register'];
 private const CHAIN=[
  '65b675a0d1e01dd0834bb42b629635cee1a12e59'=>'Add finance backend activation scope gate',
  '1a81e959ba5bee963bf97baae7b0d9f6bd79c089'=>'Approve finance authorization and API contract',
  'd590768ff55edcda95330abd225e64adcc472b12'=>'Add finance database and read API foundation',
  '452b8fe9be931cce5cb18432e45458792a66551a'=>'Add finance collection and receipt workflows',
  'aacac26ce1071122e50804365366a8dd058779ab'=>'Connect finance frontend',
  'bfd3aa097ca32953f07a36e7cd221a511e4cf69b'=>'Verify finance browser and accuracy workflows',
 ];
 public function test_domain_permissions_routes_reports_and_deferrals_are_closed():void {
  $this->seed(DatabaseSeeder::class);foreach(['club_charges','member_charges','member_payments','payment_allocations','payment_receipts'] as $t)$this->assertTrue(Schema::hasTable($t));
  foreach(['president-admin','treasurer'] as $r)foreach(['finance.self.view','finance.club.view','finance.manage','finance.void'] as $p)$this->assertTrue(Role::where('slug',$r)->firstOrFail()->permissions->contains('slug',$p));
  $this->assertFalse(Role::where('slug','secretary')->firstOrFail()->permissions->contains('slug','finance.void'));$this->assertSame(['finance.self.view'],Role::where('slug','member')->firstOrFail()->permissions->where('group','finance')->pluck('slug')->values()->all());
  $routes=collect(Route::getRoutes())->filter(fn($r)=>str_contains($r->uri(),'finance/'))->map(fn($r)=>implode('|',$r->methods()).' '.$r->uri());$this->assertFalse($routes->contains(fn($r)=>str_starts_with($r,'DELETE ')));
  $this->assertSame(self::REPORT_KEYS,array_column(app(ReportCatalog::class)->definitions(),'key'));
  $doc=file_get_contents(__DIR__.'/../../docs/milestone_3gu_finance_closure_review.md');foreach(['member collections, not full accounting','integer paise','Recorded -> Voided','no unallocated credit','unique club-scoped idempotency keys','Secretary has self, club and manage but no void','₹401.00 paid/₹1099.00 outstanding','lc_beenachi_3gs_finance_isolated.sqlite','PASS.'] as $x)$this->assertStringContainsStringIgnoringCase($x,$doc);
  $financialClosureDoc=file_get_contents(__DIR__.'/../../docs/milestone_3gx_financial_reports_closure_review.md');foreach(['collections-summary','Normal Member','read-only','Phase 1 is formally closed'] as $x)$this->assertStringContainsString($x,$financialClosureDoc);
 }
 public function test_history_and_two_file_closure_scope_are_lifecycle_safe():void {
  $root=dirname(__DIR__,3);$log=$this->git($root,['log','--all','--format=%H%x09%s']);foreach(self::CHAIN as $h=>$s)$this->assertStringContainsString("$h\t$s",$log);$hashes=array_keys(self::CHAIN);for($i=0;$i<count($hashes)-1;$i++){$p=new Process(['git','merge-base','--is-ancestor',$hashes[$i],$hashes[$i+1]],$root);$p->run();$this->assertTrue($p->isSuccessful());}
  $line=collect(preg_split('/\R/',trim($log)))->first(fn($r)=>str_ends_with($r,"\tClose finance backend activation"));if($line){[$h]=explode("\t",$line,2);$files=preg_split('/\R/',trim($this->git($root,['diff-tree','--no-commit-id','--name-only','-r',$h])));}else{$files=array_map(fn($r)=>str_replace('\\\\','/',substr($r,3)),preg_split('/\R/',trim($this->git($root,['status','--porcelain=v1']))));}$expected=['backend/docs/milestone_3gu_finance_closure_review.md','backend/tests/Feature/Milestone3guFinanceClosureReviewTest.php'];sort($files);sort($expected);$this->assertSame($expected,$files);
 }
 private function git(string $root,array $args):string{$p=new Process(['git',...$args],$root);$p->setTimeout(30);$p->run();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());return str_replace("\r\n","\n",$p->getOutput());}
}
