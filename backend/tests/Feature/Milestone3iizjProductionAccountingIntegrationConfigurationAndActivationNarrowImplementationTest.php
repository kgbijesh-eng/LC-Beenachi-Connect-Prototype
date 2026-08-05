<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingIntegrationProposal;
use App\Models\ClubAccountingIntegrationProposalDecision;
use App\Models\ClubMembership;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\AccountingIntegrationProposalService;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone3iizjProductionAccountingIntegrationConfigurationAndActivationNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private Permission $permission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->club = Club::create(['name'=>'Beenachi','code'=>'LCB']);
        $this->permission = Permission::where('slug', AccountingIntegrationProposalService::PERMISSION)->firstOrFail();
    }

    public function test_permission_is_catalogued_but_not_granted_to_any_default_role(): void
    {
        $this->assertSame('finance.accounting.integration.configure', $this->permission->slug);
        $this->assertCount(0, $this->permission->roles);
    }

    public function test_only_active_same_club_president_or_treasurer_with_exact_permission_can_read(): void
    {
        $withoutPermission=$this->officer('president-admin', false);
        $this->actingAs($withoutPermission)->getJson($this->base().'/readiness')->assertForbidden();
        foreach (['president-admin','treasurer'] as $slug) {
            $actor=$this->officer($slug, true);
            $this->actingAs($actor)->getJson($this->base().'/readiness')->assertOk()->assertJsonPath('data.club.id',$this->club->id)->assertJsonMissing(['member_name'=>'x','payment'=>'x','receipt'=>'x']);
        }
        foreach (['secretary','member'] as $slug) {
            $actor=$this->officer($slug, true);
            $this->actingAs($actor)->getJson($this->base().'/readiness')->assertForbidden();
        }
        $inactive=$this->officer('treasurer', true, 'inactive');
        $this->actingAs($inactive)->getJson($this->base().'/readiness')->assertForbidden();
    }

    public function test_readiness_and_configuration_are_side_effect_free_and_publish_stable_status_vocabulary(): void
    {
        $actor=$this->officer('president-admin', true);
        $before=[ClubAccountingIntegrationProposal::count(),AuditLog::count()];
        $response=$this->actingAs($actor)->getJson($this->base().'/readiness')->assertOk();
        foreach (['Ready','Not ready','Already active','Revoked','Missing mappings','Invalid mappings','Unintegrated source inside coverage','Reconciliation defect','Closed or missing accounting period','Opening package not posted'] as $code) $this->assertContains($code,$response->json('data.readiness_codes'));
        $this->actingAs($actor)->getJson($this->base().'/configuration')->assertOk()->assertJsonMissingPath('data.statement');
        $this->assertSame($before,[ClubAccountingIntegrationProposal::count(),AuditLog::count()]);
    }

    public function test_mapping_proposal_is_club_scoped_validated_audited_and_idempotent(): void
    {
        $actor=$this->officer('treasurer', true);
        $account=$this->account($this->club, 'DUES-INCOME', LedgerAccount::TYPE_INCOME);
        $payload=$this->mappingPayload($account);
        $first=$this->actingAs($actor)->postJson($this->base().'/mapping-proposals',$payload)->assertCreated();
        $second=$this->actingAs($actor)->postJson($this->base().'/mapping-proposals',$payload)->assertCreated();
        $this->assertSame($first->json('data.id'),$second->json('data.id'));
        $this->assertDatabaseCount('club_accounting_integration_proposals',1);
        $this->assertDatabaseCount('audit_logs',1);
        $changed=$payload; $changed['mapping_version']='v2';
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals',$changed)->assertConflict();
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals',$payload+['status'=>'approved'])->assertUnprocessable();
    }

    public function test_invalid_and_cross_club_mapping_inputs_fail_closed(): void
    {
        $actor=$this->officer('president-admin',true);
        $other=Club::create(['name'=>'Other','code'=>'OTH']);
        $foreign=$this->account($other,'OTHER-INCOME',LedgerAccount::TYPE_INCOME);
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals',$this->mappingPayload($foreign))->assertUnprocessable();
        $bad=$this->account($this->club,'BAD-ASSET',LedgerAccount::TYPE_ASSET);
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals',$this->mappingPayload($bad, (string) Str::uuid()))->assertUnprocessable();
        $payload=$this->mappingPayload($this->account($this->club,'VALID-INCOME',LedgerAccount::TYPE_INCOME),(string)Str::uuid()); $payload['source_key']='unsupported';
        $this->actingAs($actor)->postJson($this->base().'/mapping-proposals',$payload)->assertUnprocessable();
    }

    public function test_proposer_cannot_decide_but_independent_officer_can_reject_once(): void
    {
        $proposer=$this->officer('president-admin',true);
        $approver=$this->officer('treasurer',true);
        $account=$this->account($this->club,'DUES-INCOME',LedgerAccount::TYPE_INCOME);
        $created=$this->actingAs($proposer)->postJson($this->base().'/mapping-proposals',$this->mappingPayload($account))->assertCreated();
        $url=$this->base().'/proposals/'.$created->json('data.id');
        $this->actingAs($proposer)->postJson($url.'/approve',['lock_version'=>0])->assertForbidden();
        $this->actingAs($approver)->postJson($url.'/reject',['lock_version'=>0,'reason'=>'Independent review rejected the mapping.'])->assertOk()->assertJsonPath('data.status','rejected');
        $this->actingAs($approver)->postJson($url.'/reject',['lock_version'=>1,'reason'=>'again'])->assertConflict();
        $this->assertDatabaseCount('club_accounting_integration_proposal_decisions',1);
        $decision=ClubAccountingIntegrationProposalDecision::firstOrFail();
        $this->assertSame('rejected',$decision->decision);
        $this->expectException(\LogicException::class); $decision->update(['reason'=>'changed']);
    }

    public function test_only_proposer_can_withdraw_and_terminal_proposal_is_immutable(): void
    {
        $proposer=$this->officer('treasurer',true); $other=$this->officer('president-admin',true);
        $created=$this->actingAs($proposer)->postJson($this->base().'/mapping-proposals',$this->mappingPayload($this->account($this->club,'DUES-INCOME',LedgerAccount::TYPE_INCOME)))->assertCreated();
        $url=$this->base().'/proposals/'.$created->json('data.id').'/withdraw';
        $this->actingAs($other)->postJson($url,['lock_version'=>0])->assertForbidden();
        $this->actingAs($proposer)->postJson($url,['lock_version'=>0,'reason'=>'No longer required.'])->assertOk()->assertJsonPath('data.status','withdrawn');
        $this->actingAs($proposer)->postJson($url,['lock_version'=>1])->assertConflict();
    }

    public function test_independent_approval_uses_existing_mapping_service_and_commits_audit_atomically(): void
    {
        $proposer=$this->officer('president-admin',true); $approver=$this->officer('treasurer',true);
        $created=$this->actingAs($proposer)->postJson($this->base().'/mapping-proposals',$this->mappingPayload($this->account($this->club,'DUES-INCOME',LedgerAccount::TYPE_INCOME)))->assertCreated();
        $this->actingAs($approver)->postJson($this->base().'/proposals/'.$created->json('data.id').'/approve',['lock_version'=>0])->assertOk()->assertJsonPath('data.status','approved');
        $this->assertDatabaseCount('club_accounting_source_mappings',1);
        $this->assertDatabaseCount('club_accounting_integration_proposal_decisions',1);
        $this->assertDatabaseCount('audit_logs',2);
        $this->assertSame('member_charge',ClubAccountingIntegrationProposal::firstOrFail()->source_type);
    }

    public function test_routes_are_narrow_and_no_statement_or_direct_mutation_surface_exists(): void
    {
        $uris=collect(app('router')->getRoutes()->getRoutes())->map(fn($route)=>implode('|',$route->methods()).' '.$route->uri())->filter(fn($uri)=>str_contains($uri,'finance/accounting/integration'))->values();
        $this->assertCount(8,$uris);
        foreach (['readiness','configuration','mapping-proposals','activation-proposals','revocation-proposals','approve','reject','withdraw'] as $part) $this->assertTrue($uris->contains(fn($uri)=>str_contains($uri,$part)));
        $this->assertFalse($uris->contains(fn($uri)=>str_contains($uri,'statement')||str_contains($uri,'journal')||str_contains($uri,'delete')||str_contains($uri,'restore')));
    }

    private function base(): string { return '/api/v1/clubs/'.$this->club->id.'/finance/accounting/integration'; }
    private function officer(string $roleSlug,bool $grant,string $status='active'): User { $role=Role::where('slug',$roleSlug)->firstOrFail(); if($grant)$role->permissions()->syncWithoutDetaching([$this->permission->id]); $user=User::factory()->create(); ClubMembership::create(['club_id'=>$this->club->id,'user_id'=>$user->id,'role_id'=>$role->id,'status'=>$status,'joined_at'=>now()]); return $user; }
    private function account(Club $club,string $code,string $type): LedgerAccount { return LedgerAccount::create(['club_id'=>$club->id,'code'=>$code,'name'=>$code,'account_type'=>$type,'normal_balance'=>$type===LedgerAccount::TYPE_INCOME?'credit':'debit','currency'=>'INR','is_active'=>true,'created_by_user_id'=>User::factory()->create()->id]); }
    private function mappingPayload(LedgerAccount $account,?string $requestId=null): array { return ['client_request_id'=>$requestId??(string)Str::uuid(),'proposal_type'=>'mapping_create','source_type'=>'member_charge','source_key'=>'membership_dues','ledger_account_id'=>$account->id,'mapping_version'=>'v1','effective_date'=>'2027-07-01','coverage_start_date'=>'2027-07-01','reference'=>'MAP-'.Str::upper(Str::random(12))]; }
}
