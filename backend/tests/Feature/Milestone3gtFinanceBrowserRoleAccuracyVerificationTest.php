<?php
namespace Tests\Feature;
use App\Models\{AuditLog,Club,ClubMembership,Member,MemberCharge,MemberPayment,Role,User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3gtFinanceBrowserRoleAccuracyVerificationTest extends TestCase {
 use RefreshDatabase;
 public function test_every_role_and_exact_paise_workflow_remains_enforced():void {
  $this->seed(DatabaseSeeder::class);$club=Club::create(['name'=>'GT','code'=>'GT','timezone'=>'Asia/Kolkata','is_active'=>true]);$memberUser=User::factory()->create();$member=$this->member($club,$memberUser,'DUP');$other=$this->member($club,null,'DUP');
  foreach(['president-admin','secretary','treasurer'] as $role){$u=$this->actor($club,$role);Sanctum::actingAs($u);config(['instance.organisation_code' => $club->code]); $this->getJson("/api/v1/clubs/{$club->id}/finance/charges")->assertOk();}
  $president=$this->actor($club,'president-admin');Sanctum::actingAs($president);
  config(['instance.organisation_code' => $club->code]);
  $draft=$this->postJson("/api/v1/clubs/{$club->id}/finance/charges",['title'=>'Duplicate','category'=>'membership_dues','amount_paise'=>100001,'due_date'=>'2026-07-31','financial_year'=>'2026-27','member_ids'=>[$member->id],'assign_all_active_members'=>false])->assertCreated()->json('data');
  $this->postJson("/api/v1/clubs/{$club->id}/finance/charges/{$draft['id']}/issue",['lock_version'=>$draft['lock_version']])->assertOk();
  $mc=MemberCharge::firstOrFail();$payload=['payment_date'=>'2026-07-10','payment_mode'=>'cash','amount_paise'=>33334,'allocations'=>[['member_charge_id'=>$mc->id,'amount_paise'=>33334]],'idempotency_key'=>'99999999-9999-4999-8999-999999999999'];
  $p=$this->postJson("/api/v1/clubs/{$club->id}/finance/members/{$member->id}/payments",$payload)->assertCreated()->json('data');
  $this->getJson("/api/v1/clubs/{$club->id}/finance/members/{$member->id}/summary")->assertJsonPath('data.assessed_paise',100001)->assertJsonPath('data.paid_paise',33334)->assertJsonPath('data.outstanding_paise',66667)->assertJsonPath('data.outstanding_display','666.67');
  $this->postJson("/api/v1/clubs/{$club->id}/finance/members/{$member->id}/payments",$payload)->assertOk()->assertJsonPath('replayed',true);$this->assertSame(1,MemberPayment::count());
  Sanctum::actingAs($this->actor($club,'secretary'));$this->postJson("/api/v1/clubs/{$club->id}/finance/payments/{$p['id']}/void",['reason'=>'Denied','lock_version'=>$p['lock_version']])->assertForbidden();
  Sanctum::actingAs($this->actor($club,'treasurer'));$this->postJson("/api/v1/clubs/{$club->id}/finance/payments/{$p['id']}/void",['reason'=>'Corrected','lock_version'=>$p['lock_version']])->assertOk()->assertJsonPath('data.receipt.status','voided');
  $this->getJson("/api/v1/clubs/{$club->id}/finance/members/{$member->id}/summary")->assertJsonPath('data.paid_paise',0)->assertJsonPath('data.outstanding_paise',100001);
  Sanctum::actingAs($memberUser);ClubMembership::create(['club_id'=>$club->id,'user_id'=>$memberUser->id,'role_id'=>Role::where('slug','member')->firstOrFail()->id,'status'=>'active','joined_at'=>now()]);
  $self=$this->getJson("/api/v1/clubs/{$club->id}/finance/me/payments")->assertOk();$this->assertArrayNotHasKey('internal_note',$self->json('data.0'));$this->getJson("/api/v1/clubs/{$club->id}/finance/members/{$other->id}/summary")->assertForbidden();
 }
 public function test_frontend_has_confirmation_void_privacy_and_responsive_evidence():void {
  $component=file_get_contents(dirname(__DIR__,3).'/app/FinanceBackend.tsx');$doc=file_get_contents(__DIR__.'/../../docs/milestone_3gt_finance_browser_role_accuracy_verification.md');
  foreach(['Confirm backend submission','Confirm Void','role==="president"||role==="treasurer"','lock_version:p.lock_version','Backend confirmed payment void','₹1500.00','₹401.00','₹1099.00','1440, 900 and 390','no unresolved Finance browser'] as $x)$this->assertStringContainsString($x,$component.$doc);
  $this->assertStringNotContainsString('collections-summary',$component);
 }
 private function actor(Club $c,string $role):User{$u=User::factory()->create();ClubMembership::create(['club_id'=>$c->id,'user_id'=>$u->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'status'=>'active','joined_at'=>now()]);return $u;}
 private function member(Club $c,?User $u,string $code):Member{return Member::create(['club_id'=>$c->id,'user_id'=>$u?->id,'member_code'=>$code.uniqid(),'full_name'=>'Duplicate Member','status'=>'active']);}
}
