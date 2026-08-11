<?php
namespace Tests\Feature;
use App\Models\{Club,ClubCharge,ClubMembership,Member,MemberCharge,MemberPayment,PaymentAllocation,PaymentReceipt,Role,User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Route,Schema};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Symfony\Component\Process\Process;

class Milestone3gqFinanceDatabaseReadApiFoundationTest extends TestCase {
    use RefreshDatabase;
    public function test_schema_permissions_and_get_only_routes():void {
        $this->seed(DatabaseSeeder::class);
        foreach(['club_charges','member_charges','member_payments','payment_allocations','payment_receipts'] as $table)$this->assertTrue(Schema::hasTable($table));
        foreach(['president-admin','treasurer'] as $r)foreach(['finance.self.view','finance.club.view','finance.manage','finance.void'] as $p)$this->assertTrue(Role::where('slug',$r)->firstOrFail()->permissions->contains('slug',$p));
        foreach(['finance.self.view','finance.club.view','finance.manage'] as $p)$this->assertTrue(Role::where('slug','secretary')->firstOrFail()->permissions->contains('slug',$p));
        $this->assertFalse(Role::where('slug','secretary')->firstOrFail()->permissions->contains('slug','finance.void'));
        $this->assertSame(['finance.self.view'],Role::where('slug','member')->firstOrFail()->permissions->where('group','finance')->pluck('slug')->values()->all());
        $root=dirname(__DIR__,3); $process=new Process(['git','show','d590768ff55edcda95330abd225e64adcc472b12:backend/routes/api.php'],$root); $process->run();
        $this->assertTrue($process->isSuccessful(),$process->getErrorOutput()); $historical=$process->getOutput();
        $this->assertStringNotContainsString('FinanceWriteController',$historical);
    }
    public function test_management_and_self_reads_are_exact_private_and_club_scoped():void {
        $this->seed(DatabaseSeeder::class); [$club,$manager]=$this->actor('president-admin','A'); [$other]=$this->actor('president-admin','B');
        $user=User::factory()->create(); ClubMembership::create(['club_id'=>$club->id,'user_id'=>$user->id,'role_id'=>Role::where('slug','member')->firstOrFail()->id,'status'=>'active','joined_at'=>now()]); $member=$this->member($club,$user,'DUPLICATE'); $otherMember=$this->member($other,null,'DUPLICATE');
        $charge=ClubCharge::create(['club_id'=>$club->id,'title'=>'Duplicate Charge','category'=>'membership_dues','amount_paise'=>125050,'due_date'=>'2026-07-31','financial_year'=>'2026-27','status'=>'issued','assignment_member_ids'=>[$member->id],'created_by_user_id'=>$manager->id]);
        $mc=MemberCharge::create(['club_id'=>$club->id,'club_charge_id'=>$charge->id,'member_id'=>$member->id,'original_amount_paise'=>125050]);
        $payment=MemberPayment::create(['club_id'=>$club->id,'member_id'=>$member->id,'amount_paise'=>25025,'payment_date'=>'2026-07-10','payment_mode'=>'upi','external_reference'=>'SAFE-REF','internal_note'=>'MANAGEMENT ONLY','idempotency_key'=>'11111111-1111-4111-8111-111111111111','request_hash'=>str_repeat('a',64),'status'=>'recorded','recorded_by_user_id'=>$manager->id]);
        PaymentAllocation::create(['club_id'=>$club->id,'member_payment_id'=>$payment->id,'member_charge_id'=>$mc->id,'amount_paise'=>25025]);
        $receipt=PaymentReceipt::create(['club_id'=>$club->id,'member_payment_id'=>$payment->id,'receipt_number'=>'A/2026-27/RCP/0001','receipt_date'=>'2026-07-10','financial_year'=>'2026-27','status'=>'issued','issued_by_user_id'=>$manager->id]);
        ClubCharge::create(['club_id'=>$other->id,'title'=>'Duplicate Charge','category'=>'membership_dues','amount_paise'=>999999,'due_date'=>'2026-07-31','financial_year'=>'2026-27','status'=>'issued','assignment_member_ids'=>[$otherMember->id],'created_by_user_id'=>$manager->id]);
        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/clubs/{$club->id}/finance/members/{$member->id}/summary")->assertOk()->assertJsonPath('data.assessed_paise',125050)->assertJsonPath('data.paid_paise',25025)->assertJsonPath('data.outstanding_paise',100025)->assertJsonPath('data.outstanding_display','1000.25');
        $this->getJson("/api/v1/clubs/{$club->id}/finance/charges?category=membership_dues")->assertOk()->assertJsonPath('meta.total',1)->assertJsonPath('data.0.id',$charge->id);
        $this->getJson("/api/v1/clubs/{$club->id}/finance/payments/{$payment->id}")->assertOk()->assertJsonPath('data.receipt.id',$receipt->id)->assertJsonPath('data.idempotency_key','11111111-1111-4111-8111-111111111111');
        $this->getJson("/api/v1/clubs/{$club->id}/finance/members/{$otherMember->id}/summary")->assertNotFound();
        Sanctum::actingAs($user);
        $self=$this->getJson("/api/v1/clubs/{$club->id}/finance/me/payments")->assertOk()->assertJsonPath('meta.total',1);
        $this->assertArrayNotHasKey('internal_note',$self->json('data.0')); $this->assertArrayNotHasKey('idempotency_key',$self->json('data.0'));
        $this->getJson("/api/v1/clubs/{$club->id}/finance/members/{$member->id}/summary")->assertForbidden();
    }
    public function test_voided_allocations_do_not_count_and_invalid_filters_fail():void {
        $this->seed(DatabaseSeeder::class); [$club,$manager]=$this->actor('treasurer','C'); $member=$this->member($club,null,'C1');
        $charge=ClubCharge::create(['club_id'=>$club->id,'title'=>'C','category'=>'other_contribution','amount_paise'=>10000,'due_date'=>'2026-08-01','financial_year'=>'2026-27','status'=>'issued','assignment_member_ids'=>[$member->id],'created_by_user_id'=>$manager->id]);
        $mc=MemberCharge::create(['club_id'=>$club->id,'club_charge_id'=>$charge->id,'member_id'=>$member->id,'original_amount_paise'=>10000]);
        $p=MemberPayment::create(['club_id'=>$club->id,'member_id'=>$member->id,'amount_paise'=>10000,'payment_date'=>'2026-07-10','payment_mode'=>'cash','idempotency_key'=>'22222222-2222-4222-8222-222222222222','request_hash'=>str_repeat('b',64),'status'=>'voided','recorded_by_user_id'=>$manager->id]);
        PaymentAllocation::create(['club_id'=>$club->id,'member_payment_id'=>$p->id,'member_charge_id'=>$mc->id,'amount_paise'=>10000]);
        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/clubs/{$club->id}/finance/members/{$member->id}/summary")->assertJsonPath('data.paid_paise',0)->assertJsonPath('data.outstanding_paise',10000);
        $this->getJson("/api/v1/clubs/{$club->id}/finance/charges?not_a_filter=x")->assertUnprocessable();
    }
    private function actor(string $role,string $suffix):array{$club=Club::create(['name'=>"Finance $suffix",'code'=>"F$suffix",'timezone'=>'Asia/Kolkata','is_active'=>true]);$u=User::factory()->create();ClubMembership::create(['club_id'=>$club->id,'user_id'=>$u->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'status'=>'active','joined_at'=>now()]);return[$club,$u];}
    private function member(Club $c,?User $u,string $code):Member{return Member::create(['club_id'=>$c->id,'user_id'=>$u?->id,'member_code'=>$code.uniqid(),'full_name'=>'Duplicate Member','status'=>'active']);}
}
