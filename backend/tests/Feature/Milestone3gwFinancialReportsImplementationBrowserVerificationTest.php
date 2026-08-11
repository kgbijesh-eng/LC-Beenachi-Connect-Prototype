<?php

namespace Tests\Feature;

use App\Models\{Club,ClubCharge,ClubMembership,Member,MemberCharge,MemberPayment,PaymentAllocation,PaymentReceipt,Role,User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Milestone3gwFinancialReportsImplementationBrowserVerificationTest extends TestCase
{
    use RefreshDatabase;
    private const BASE='0e1d69253347b9b1d51c54e504cdef1808c664e3';
    private const SUBJECT='Activate and verify financial reports';

    public function test_exact_financial_reports_roles_filters_voids_privacy_and_club_scope(): void
    {
        $this->seed(DatabaseSeeder::class);$club=$this->club('GW');$other=$this->club('OTHER');$actor=$this->actor($club,'president-admin');Sanctum::actingAs($actor);
        $m1=$this->member($club,'DUP-1','Duplicate Member');$m2=$this->member($club,'DUP-2','Duplicate Member');$m3=$this->member($club,'UNPAID','Unpaid Member');
        $c1=$this->charge($club,$actor,'Duplicate Charge','membership_dues','2026-07-10',[$m1,$m2]);$o11=$this->obligation($c1,$m1,100001);$o12=$this->obligation($c1,$m2,100001);
        $c2=$this->charge($club,$actor,'Contribution','event_contribution','2026-07-11',[$m3]);$this->obligation($c2,$m3,50000);
        $c3=$this->charge($club,$actor,'Duplicate Charge','membership_dues','2026-07-12',[$m1]);$o13=$this->obligation($c3,$m1,20000);
        $p1=$this->payment($club,$m1,$actor,33334,'recorded','GW-1');$this->allocation($p1,$o11,33334);$r1=$this->receipt($club,$p1,$actor,'GW-2026-0001','issued');
        $p2=$this->payment($club,$m2,$actor,100001,'recorded','GW-2');$this->allocation($p2,$o12,100001);$this->receipt($club,$p2,$actor,'GW-2026-0002','issued');
        $p3=$this->payment($club,$m1,$actor,1000,'voided','GW-VOID');$this->allocation($p3,$o11,1000);$r3=$this->receipt($club,$p3,$actor,'GW-2026-0003','voided');
        $p4=$this->payment($club,$m1,$actor,10000,'recorded','GW-MULTI');$this->allocation($p4,$o11,5000);$this->allocation($p4,$o13,5000);$this->receipt($club,$p4,$actor,'GW-2026-0004','issued');
        $crossActor=$this->actor($other,'president-admin');$crossMember=$this->member($other,'CROSS','FINANCIAL-REPORT-SENTINEL-CROSS');$crossCharge=$this->charge($other,$crossActor,'Cross Charge','membership_dues','2026-07-10',[$crossMember]);$this->obligation($crossCharge,$crossMember,99999999);

        foreach(['president-admin','secretary','treasurer'] as $role){Sanctum::actingAs($this->actor($club,$role));$keys=array_column($this->getJson("/api/v1/clubs/{$club->id}/reports")->assertOk()->json('data'),'key');foreach(['collections-summary','member-outstanding-balances','charge-collection-status','payment-register','receipt-register'] as $key)$this->assertContains($key,$keys);}
        Sanctum::actingAs($actor);
        $summary=$this->getJson("/api/v1/clubs/{$club->id}/reports/collections-summary?date_from=2026-07-01&date_to=2026-07-31")->assertOk();
        $summary->assertJsonPath('data.summary.currency','INR')->assertJsonPath('data.summary.assessed_paise',270002)->assertJsonPath('data.summary.collected_paise',143335)->assertJsonPath('data.summary.outstanding_paise',126667)->assertJsonPath('data.summary.voided_payment_paise',1000)->assertJsonPath('data.summary.fully_paid_count',1)->assertJsonPath('data.summary.partially_paid_count',1)->assertJsonPath('data.summary.unpaid_count',1);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/collections-summary?category=event_contribution")->assertOk()->assertJsonPath('data.summary.voided_payment_paise',0);
        $members=$this->getJson("/api/v1/clubs/{$club->id}/reports/member-outstanding-balances?member_search=Duplicate&per_page=1&page=1")->assertOk()->assertJsonPath('meta.total',2)->assertJsonPath('meta.last_page',2);
        $this->assertNotSame($m1->id,$m2->id);$this->assertNotSame($c1->id,$c3->id);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/charge-collection-status?charge_search=Duplicate")->assertOk()->assertJsonPath('meta.total',2);
        $payments=$this->getJson("/api/v1/clubs/{$club->id}/reports/payment-register?per_page=2&page=1")->assertOk()->assertJsonPath('data.summary.recorded_paise',143335)->assertJsonPath('data.summary.voided_paise',1000)->assertJsonPath('data.summary.payment_count',4)->assertJsonPath('meta.last_page',2);
        $receipts=$this->getJson("/api/v1/clubs/{$club->id}/reports/receipt-register?payment_status=voided")->assertOk()->assertJsonPath('data.rows.0.id',$r3->id)->assertJsonPath('data.rows.0.state','voided')->assertJsonPath('data.rows.0.receipt_number','GW-2026-0003');
        $this->getJson("/api/v1/clubs/{$club->id}/reports/payment-register?category=membership_dues")->assertUnprocessable();
        $this->getJson("/api/v1/clubs/{$club->id}/reports/payment-register?unknown_filter=x")->assertUnprocessable();
        $serialized=json_encode([$summary->json('data'),$members->json('data'),$payments->json('data'),$receipts->json('data')]);foreach(['phone','email','address','birth_date','internal_note','idempotency_key','request_hash','void_reason','actor_id','FINANCIAL-REPORT-SENTINEL-CROSS'] as $private)$this->assertStringNotContainsString($private,$serialized);
        Sanctum::actingAs($this->actor($club,'member'));$this->getJson("/api/v1/clubs/{$club->id}/reports")->assertForbidden();$this->getJson("/api/v1/clubs/{$club->id}/reports/collections-summary")->assertForbidden();
        $inactive=$this->actor($club,'secretary','inactive');Sanctum::actingAs($inactive);$this->getJson("/api/v1/clubs/{$club->id}/reports/collections-summary")->assertForbidden();
    }

    public function test_frontend_and_evidence_enforce_truthful_financial_states(): void
    {
        $component=file_get_contents(dirname(__DIR__,3).'/app/ReportsBackend.tsx');$doc=file_get_contents(__DIR__.'/../../docs/milestone_3gw_financial_reports_implementation_browser_verification.md');
        foreach(['integer paise · INR · no exports','payment_status','member_search','clearOutput();setSelected(def);setState("loading")','Report unavailable. Previous rows and totals were cleared.','1440px','900px','390px','Genuine PostgreSQL','isolated SQLite'] as $expected)$this->assertStringContainsString($expected,$component.$doc);
    }

    public function test_changed_file_scope_is_lifecycle_safe(): void
    {
        $root=dirname(__DIR__,3);$commit=$this->findCommit($root);$statusLines=array_values(array_filter(explode("\n",rtrim($this->git($root,['status','--porcelain=v1','-uall']),"\r\n"))));$files=$commit?$this->gitLines($root,['diff-tree','--no-commit-id','--name-only','-r',$commit]):array_map(fn($line)=>str_replace('\\','/',substr($line,3)),$statusLines);
        $allowed=['app/ReportsBackend.tsx','backend/app/Http/Controllers/Api/V1/Reports/ClubReportController.php','backend/app/Http/Requests/Reports/RunClubReportRequest.php','backend/app/Services/Reports/FinancialReportService.php','backend/app/Services/Reports/ReportCatalog.php','backend/docs/milestone_3gw_financial_reports_implementation_browser_verification.md','backend/tests/Feature/Milestone3gkVerifiedReportSetBackendActivationTest.php','backend/tests/Feature/Milestone3gmReportsBrowserRoleVerificationTest.php','backend/tests/Feature/Milestone3gwFinancialReportsImplementationBrowserVerificationTest.php'];sort($files);sort($allowed);$this->assertSame($allowed,$files);
        if(!$commit)$this->assertSame(self::BASE,trim($this->git($root,['rev-parse','HEAD'])));
    }

    private function findCommit(string $root): ?string {$matches=[];foreach($this->gitLines($root,['log','--all','--format=%H%x09%s']) as $line){[$hash,$subject]=array_pad(explode("\t",$line,2),2,'');if($subject===self::SUBJECT)$matches[]=$hash;}$this->assertLessThanOrEqual(1,count($matches));return $matches[0]??null;}
    private function gitLines(string $root,array $args): array{return array_values(array_filter(explode("\n",trim($this->git($root,$args)))));}
    private function git(string $root,array $args): string{$process=new Process(['git',...$args],$root);$process->setTimeout(30);$process->run();$this->assertTrue($process->isSuccessful(),$process->getErrorOutput());return str_replace("\r\n","\n",$process->getOutput());}
    private function club(string $code): Club{return Club::create(['name'=>'Financial '.$code,'code'=>$code.uniqid(),'timezone'=>'Asia/Kolkata','is_active'=>true]);}
    private function actor(Club $club,string $role,string $status='active'): User{$user=User::factory()->create();ClubMembership::create(['club_id'=>$club->id,'user_id'=>$user->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'status'=>$status,'joined_at'=>now()]);return $user;}
    private function member(Club $club,string $code,string $name): Member{return Member::create(['club_id'=>$club->id,'member_code'=>$code,'full_name'=>$name,'status'=>'active']);}
    private function charge(Club $club,User $actor,string $title,string $category,string $due,array $members): ClubCharge{return ClubCharge::create(['club_id'=>$club->id,'title'=>$title,'category'=>$category,'amount_paise'=>100,'due_date'=>$due,'financial_year'=>'2026-27','status'=>'issued','assignment_member_ids'=>array_map(fn($m)=>$m->id,$members),'created_by_user_id'=>$actor->id,'issued_at'=>now(),'issued_by_user_id'=>$actor->id,'lock_version'=>1]);}
    private function obligation(ClubCharge $charge,Member $member,int $amount): MemberCharge{return MemberCharge::create(['club_id'=>$charge->club_id,'club_charge_id'=>$charge->id,'member_id'=>$member->id,'original_amount_paise'=>$amount,'lock_version'=>1]);}
    private function payment(Club $club,Member $member,User $actor,int $amount,string $status,string $reference): MemberPayment{return MemberPayment::create(['club_id'=>$club->id,'member_id'=>$member->id,'amount_paise'=>$amount,'payment_date'=>'2026-07-15','payment_mode'=>'cash','external_reference'=>$reference,'idempotency_key'=>fake()->uuid(),'request_hash'=>str_repeat('a',64),'status'=>$status,'recorded_by_user_id'=>$actor->id,'voided_at'=>$status==='voided'?now():null,'voided_by_user_id'=>$status==='voided'?$actor->id:null,'void_reason'=>$status==='voided'?'Correction':null,'lock_version'=>1]);}
    private function allocation(MemberPayment $payment,MemberCharge $charge,int $amount): void{PaymentAllocation::create(['club_id'=>$payment->club_id,'member_payment_id'=>$payment->id,'member_charge_id'=>$charge->id,'amount_paise'=>$amount]);}
    private function receipt(Club $club,MemberPayment $payment,User $actor,string $number,string $status): PaymentReceipt{return PaymentReceipt::create(['club_id'=>$club->id,'member_payment_id'=>$payment->id,'receipt_number'=>$number,'receipt_date'=>'2026-07-15','financial_year'=>'2026-27','status'=>$status,'issued_by_user_id'=>$actor->id,'voided_at'=>$status==='voided'?now():null]);}
}
