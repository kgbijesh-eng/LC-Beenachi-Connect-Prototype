<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\ClubReminder;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3gdRemindersLifecycleBackendWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    public function test_management_can_create_update_and_payload_guards_apply(): void
    {
        foreach(['president-admin','secretary'] as $role){
            $club=$this->club($role); $user=$this->user($club,$role); Sanctum::actingAs($user);
            config(['instance.organisation_code' => $club->code]);
            $response=$this->postJson("/api/v1/clubs/{$club->id}/reminders",[
                'title'=>'Draft Reminder','description'=>'Synthetic','priority'=>'high','visibility'=>'members',
                'is_all_day'=>true,'due_date'=>'2026-07-10',
            ])->assertCreated()->assertJsonPath('data.status','draft')->assertJsonPath('data.lock_version',1);
            $id=$response->json('data.id');
            $this->assertDatabaseHas('audit_logs',['action'=>'reminders.created','auditable_id'=>$id]);
            $this->patchJson("/api/v1/clubs/{$club->id}/reminders/{$id}",['lock_version'=>1,'title'=>'Updated'])
                ->assertOk()->assertJsonPath('data.lock_version',2);
            $this->assertDatabaseHas('audit_logs',['action'=>'reminders.updated','auditable_id'=>$id]);
        }
        $club=$this->club('guard'); Sanctum::actingAs($this->user($club,'president-admin'));
        config(['instance.organisation_code' => $club->code]);
        $this->postJson("/api/v1/clubs/{$club->id}/reminders",[
            'title'=>'Bad','priority'=>'invalid','visibility'=>'members','is_all_day'=>true,
            'due_date'=>'2026-07-10','due_at'=>'2026-07-10T10:00:00+05:30','club_id'=>999,'status'=>'active',
        ])->assertUnprocessable();
    }

    public function test_read_only_unauthenticated_inactive_and_cross_club_mutations_are_denied(): void
    {
        $club=$this->club(); $other=$this->club('other');
        $payload=['title'=>'Blocked','priority'=>'normal','visibility'=>'members','is_all_day'=>true,'due_date'=>'2026-07-10'];
        config(['instance.organisation_code' => $club->code]);
        $this->postJson("/api/v1/clubs/{$club->id}/reminders",$payload)->assertUnauthorized();
        foreach(['treasurer','member'] as $role){ Sanctum::actingAs($this->user($club,$role)); config(['instance.organisation_code' => $club->code]); $this->postJson("/api/v1/clubs/{$club->id}/reminders",$payload)->assertForbidden(); }
        Sanctum::actingAs($this->user($club,'president-admin','inactive')); config(['instance.organisation_code' => $club->code]); $this->postJson("/api/v1/clubs/{$club->id}/reminders",$payload)->assertForbidden();
        Sanctum::actingAs($this->user($other,'president-admin')); $this->postJson("/api/v1/clubs/{$club->id}/reminders",$payload)->assertForbidden();
    }

    public function test_activate_complete_cancel_conflicts_audit_and_member_visibility(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00 UTC');
        $club=$this->club(); $officer=$this->user($club,'president-admin'); $member=$this->user($club,'member');
        $draft=$this->reminder($club); Sanctum::actingAs($officer);
        config(['instance.organisation_code' => $club->code]);
        $this->postJson("/api/v1/clubs/{$club->id}/reminders/{$draft->id}/activate",['lock_version'=>1])
            ->assertOk()->assertJsonPath('data.status','active')->assertJsonPath('data.lock_version',2);
        $this->postJson("/api/v1/clubs/{$club->id}/reminders/{$draft->id}/activate",['lock_version'=>2])->assertStatus(409);
        $this->postJson("/api/v1/clubs/{$club->id}/reminders/{$draft->id}/complete",['lock_version'=>2])
            ->assertOk()->assertJsonPath('data.status','completed');
        $this->postJson("/api/v1/clubs/{$club->id}/reminders/{$draft->id}/complete",['lock_version'=>3])->assertStatus(409);
        foreach(['reminders.activated','reminders.completed'] as $action) $this->assertDatabaseHas('audit_logs',['action'=>$action,'auditable_id'=>$draft->id]);

        $cancel=$this->reminder($club,['visibility'=>'members']);
        $this->postJson("/api/v1/clubs/{$club->id}/reminders/{$cancel->id}/cancel",['lock_version'=>1])->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/reminders/{$cancel->id}/cancel",['lock_version'=>1,'cancellation_reason'=>'No longer needed'])
            ->assertOk()->assertJsonPath('data.status','cancelled');
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/reminders/{$cancel->id}")->assertNotFound();

        $active=$this->reminder($club,['status'=>'active','activated_at'=>now()]); Sanctum::actingAs($officer);
        $this->postJson("/api/v1/clubs/{$club->id}/reminders/{$active->id}/cancel",['lock_version'=>1,'cancellation_reason'=>'Changed'])
            ->assertOk();
        Sanctum::actingAs($member); $this->getJson("/api/v1/clubs/{$club->id}/reminders/{$active->id}")->assertOk();
    }

    public function test_stale_terminal_status_cross_club_and_audit_integrity(): void
    {
        $club=$this->club(); $other=$this->club('x'); $officer=$this->user($club,'president-admin');
        $draft=$this->reminder($club); $terminal=$this->reminder($club,['status'=>'completed','completed_at'=>now()]);
        $foreign=$this->reminder($other); Sanctum::actingAs($officer);
        config(['instance.organisation_code' => $club->code]);
        $this->patchJson("/api/v1/clubs/{$club->id}/reminders/{$draft->id}",['lock_version'=>99,'title'=>'Stale'])->assertStatus(409);
        $this->patchJson("/api/v1/clubs/{$club->id}/reminders/{$draft->id}",['lock_version'=>1,'status'=>'active'])->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/{$club->id}/reminders/{$terminal->id}",['lock_version'=>1,'title'=>'No'])->assertStatus(409);
        $this->patchJson("/api/v1/clubs/{$club->id}/reminders/{$foreign->id}",['lock_version'=>1,'title'=>'Cross'])->assertNotFound();
        $this->assertSame(0,AuditLog::where('auditable_id',$draft->id)->count());
        $this->deleteJson("/api/v1/clubs/{$club->id}/reminders/{$draft->id}")->assertMethodNotAllowed();
    }

    private function club(string $code='gd'): Club { $this->seed(DatabaseSeeder::class); return Club::create(['name'=>'3GD Club','code'=>$code.uniqid(),'timezone'=>'Asia/Kolkata','is_active'=>true]); }
    private function user(Club $club,string $role,string $status='active'): User { $u=User::factory()->create(); ClubMembership::create(['club_id'=>$club->id,'user_id'=>$u->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'status'=>$status,'joined_at'=>now()]); return $u; }
    private function reminder(Club $club,array $overrides=[]): ClubReminder { return ClubReminder::create(array_merge(['club_id'=>$club->id,'title'=>'Synthetic','priority'=>'normal','visibility'=>'members','status'=>'draft','is_all_day'=>true,'due_date'=>'2026-07-10','lock_version'=>1],$overrides)); }
}
