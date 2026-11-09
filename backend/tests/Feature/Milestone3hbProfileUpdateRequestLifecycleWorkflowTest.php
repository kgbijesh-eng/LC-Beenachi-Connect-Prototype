<?php
namespace Tests\Feature;
use App\Models\{AuditLog,Club,ClubMembership,Member,ProfileUpdateRequest,Role,User};use Database\Seeders\DatabaseSeeder;use Laravel\Sanctum\Sanctum;use Tests\Support\FinancialPostgresTestCase;
class Milestone3hbProfileUpdateRequestLifecycleWorkflowTest extends FinancialPostgresTestCase{
 public function test_all_roles_submit_own_and_validation_duplicate_and_audit():void{$this->seed(DatabaseSeeder::class);foreach(['president-admin','secretary','treasurer','member'] as $i=>$role){$c=$this->club('S'.$i);[$u,$m]=$this->actor($c,$role,'M'.$i);Sanctum::actingAs($u);config(['instance.organisation_code' => $c->code]); $r=$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests",['changes'=>['phone'=>' 9000 '.$i.' ','occupation'=>'Teacher']])->assertCreated()->assertJsonPath('data.status','pending');$id=$r->json('data.id');$this->assertDatabaseHas('profile_update_requests',['id'=>$id,'member_id'=>$m->id,'requester_user_id'=>$u->id,'status'=>'pending']);$this->assertDatabaseHas('audit_logs',['action'=>'profile_updates.submitted','auditable_id'=>$id]);$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests",['changes'=>['phone'=>'8']])->assertConflict();}$c=$this->club('V');[$u]=$this->actor($c,'member','V');Sanctum::actingAs($u);config(['instance.organisation_code' => $c->code]); $this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests",['changes'=>['member_code'=>'X']])->assertUnprocessable();$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests",['member_id'=>99,'changes'=>['phone'=>'1']])->assertUnprocessable();$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests",['club_id'=>99,'changes'=>['phone'=>'1']])->assertUnprocessable();$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests",['changes'=>['phone'=>null]])->assertUnprocessable();}
 public function test_president_and_secretary_approve_exact_changes_and_stale_conflict():void{$this->seed(DatabaseSeeder::class);foreach(['president-admin','secretary'] as $role){$c=$this->club('A'.$role);[$memberUser,$m]=$this->actor($c,'member','M');[$reviewer]=$this->actor($c,$role,'R');Sanctum::actingAs($memberUser);config(['instance.organisation_code' => $c->code]); $request=$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests",['changes'=>['occupation'=>'Engineer','phone'=>'777']])->assertCreated()->json('data');Sanctum::actingAs($reviewer);$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests/{$request['id']}/approve",['lock_version'=>$request['lock_version']])->assertOk()->assertJsonPath('data.status','approved');$m->refresh();$this->assertSame('Engineer',$m->occupation);$this->assertSame('777',$m->phone);$this->assertDatabaseHas('audit_logs',['action'=>'profile_updates.approved','auditable_id'=>$request['id']]);$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests/{$request['id']}/approve",['lock_version'=>2])->assertConflict();}
 $c=$this->club('STALE');[$memberUser,$m]=$this->actor($c,'member','S');[$president]=$this->actor($c,'president-admin','P');Sanctum::actingAs($memberUser);config(['instance.organisation_code' => $c->code]); $r=$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests",['changes'=>['occupation'=>'New']])->assertCreated()->json('data');$m->update(['occupation'=>'Intervening']);Sanctum::actingAs($president);$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests/{$r['id']}/approve",['lock_version'=>1])->assertConflict();$this->assertSame('Intervening',$m->refresh()->occupation);$this->assertDatabaseHas('profile_update_requests',['id'=>$r['id'],'status'=>'pending']);$this->assertDatabaseHas('audit_logs',['action'=>'profile_updates.stale_conflict','auditable_id'=>$r['id']]);}
 public function test_reject_withdraw_permissions_terminal_and_no_member_mutation():void{$this->seed(DatabaseSeeder::class);$c=$this->club('RW');[$u,$m]=$this->actor($c,'member','M');[$president]=$this->actor($c,'president-admin','P');[$treasurer]=$this->actor($c,'treasurer','T');Sanctum::actingAs($u);config(['instance.organisation_code' => $c->code]); $r=$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests",['changes'=>['occupation'=>'Rejected']])->assertCreated()->json('data');Sanctum::actingAs($treasurer);$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests/{$r['id']}/reject",['lock_version'=>1,'reason'=>'No'])->assertForbidden();Sanctum::actingAs($president);$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests/{$r['id']}/reject",['lock_version'=>1,'reason'=>'Need supporting correction'])->assertOk()->assertJsonPath('data.status','rejected');$this->assertNull($m->refresh()->occupation);$this->assertDatabaseHas('audit_logs',['action'=>'profile_updates.rejected','auditable_id'=>$r['id']]);Sanctum::actingAs($u);$r2=$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests",['changes'=>['occupation'=>'Withdrawn']])->assertCreated()->json('data');Sanctum::actingAs($president);$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests/{$r2['id']}/withdraw",['lock_version'=>1])->assertForbidden();Sanctum::actingAs($u);$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests/{$r2['id']}/withdraw",['lock_version'=>1])->assertOk()->assertJsonPath('data.status','withdrawn');$this->assertNull($m->refresh()->occupation);$this->postJson("/api/v1/clubs/{$c->id}/profile-update-requests/{$r2['id']}/withdraw",['lock_version'=>2])->assertConflict();$this->assertDatabaseHas('audit_logs',['action'=>'profile_updates.withdrawn','auditable_id'=>$r2['id']]);}

 public function test_profile_approval_audit_failure_rolls_back_and_stale_audit_failure_takes_precedence(): void
 {
     $club = $this->club('audit');
     config(['instance.organisation_code' => $club->code]);
     $this->seed(DatabaseSeeder::class);
     [$user, $member] = $this->actor($club, 'member', 'M');
     [$reviewer] = $this->actor($club, 'president-admin', 'R');
     $service = app(\App\Services\ProfileUpdates\ProfileUpdateWriteService::class);
     $request = $service->submit($club, $user, ['changes' => ['phone' => '777']]);

     $failOn = 'profile_updates.approved';
     $depths = [];
     AuditLog::creating(function (AuditLog $audit) use (&$failOn, &$depths, $club, $request): void {
         if ($audit->action !== $failOn) {
             return;
         }
         $this->assertSame($club->id, $audit->club_id);
         $this->assertSame($request->id, $audit->auditable_id);
         if ($failOn === 'profile_updates.stale_conflict') {
             $this->assertSame(['field_keys' => ['phone']], $audit->metadata);
         }
         $depths[] = \Illuminate\Support\Facades\DB::transactionLevel();
         throw new \LogicException('Synthetic audit failure');
     });
     foreach (['profile_updates.approved', 'profile_updates.stale_conflict'] as $action) {
         $failOn = $action;
         $original = $action === 'profile_updates.approved' ? null : '999';
         $member->update(['phone' => $original]);
         try {
             $service->approve($club, $reviewer, $request, 1);
             $this->fail('Expected the audit failure.');
         } catch (\LogicException $exception) {
             $this->assertSame('Synthetic audit failure', $exception->getMessage());
         }
         $this->assertSame($original, $member->refresh()->phone);
         $this->assertSame('pending', $request->refresh()->status);
         $this->assertSame(1, $request->lock_version);
         $this->assertDatabaseMissing('audit_logs', ['action' => $action, 'auditable_id' => $request->id]);
     }
     $this->assertSame([1, 0], $depths);
 }

 public function test_profile_writes_refuse_ambient_and_suspended_admission(): void
 {
     $club = $this->club('admission');
     config(['instance.organisation_code' => $club->code]);
     $this->seed(DatabaseSeeder::class);
     [$user] = $this->actor($club, 'member', 'M');
     $service = app(\App\Services\ProfileUpdates\ProfileUpdateWriteService::class);
     $request = $service->submit($club, $user, ['changes' => ['occupation' => 'Proposed']]);
     $db = \Illuminate\Support\Facades\DB::connection();
     foreach (['ORGANISATION_TRANSACTION_UNSUPPORTED', 'ORGANISATION_SUSPENDED'] as $reason) {
         if ($reason === 'ORGANISATION_TRANSACTION_UNSUPPORTED') {
             $db->beginTransaction();
         } else {
             Club::whereKey($club->id)->update(['is_active' => false]);
         }
         try {
             foreach ([
                 fn () => $service->submit($club, $user, ['changes' => ['occupation' => 'Other']]),
                 fn () => $service->approve($club, $user, $request, 1),
                 fn () => $service->reject($club, $user, $request, 1, 'Synthetic'),
                 fn () => $service->withdraw($club, $user, $request, 1),
             ] as $write) {
                 try {
                     $write();
                     $this->fail('Expected admission to fail.');
                 } catch (\App\Services\Access\OrganisationAccessRejected $exception) {
                     $this->assertSame($reason, $exception->reason);
                 }
             }
         } finally {
             if ($reason === 'ORGANISATION_TRANSACTION_UNSUPPORTED') {
                 $this->assertSame(1, $db->transactionLevel());
                 $db->rollBack();
             }
         }
     }
     $this->assertSame('pending', $request->refresh()->status);
     $this->assertSame(1, $request->lock_version);
 }

 public function test_foreign_profile_resources_are_rejected_while_locked_by_another_session(): void
 {
     $club = $this->club('local');
     $foreign = $this->club('foreign');
     $this->seed(DatabaseSeeder::class);
     [$user, $member] = $this->actor($foreign, 'member', 'F');
     $service = app(\App\Services\ProfileUpdates\ProfileUpdateWriteService::class);
     config(['instance.organisation_code' => $foreign->code]);
     $request = $service->submit($foreign, $user, ['changes' => ['occupation' => 'Proposed']]);
     config(['instance.organisation_code' => $club->code]);

     $db = \Illuminate\Support\Facades\DB::connection();
     $holder = \Illuminate\Support\Facades\DB::build([...$db->getConfig(), 'name' => 'profile_foreign_lock_holder']);
     $holder->beginTransaction();
     try {
         $holder->table('clubs')->where('id', $foreign->id)->lockForUpdate()->first();
         $holder->table('members')->where('id', $member->id)->lockForUpdate()->first();
         $holder->table('profile_update_requests')->where('id', $request->id)->lockForUpdate()->first();
         $db->statement("SET lock_timeout = '1s'");
         foreach ([
             fn () => $service->approve($club, $user, $request, 1),
             fn () => $service->reject($club, $user, $request, 1, 'Synthetic'),
             fn () => $service->withdraw($club, $user, $request, 1),
         ] as $write) {
             try {
                 $write();
                 $this->fail('Expected the foreign request to be excluded.');
             } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                 $this->assertSame(0, $db->transactionLevel());
             }
         }
         try {
             $service->submit($foreign, $user, ['changes' => ['occupation' => 'Other']]);
             $this->fail('Expected the foreign organisation to be excluded.');
         } catch (\App\Services\Access\OrganisationAccessRejected $exception) {
             $this->assertSame('ORGANISATION_FOREIGN', $exception->reason);
         }
     } finally {
         $holder->rollBack();
         $holder->disconnect();
         $db->statement('RESET lock_timeout');
     }
     $this->assertSame('pending', $request->refresh()->status);
 }

 public function test_admitted_stale_attempt_keeps_narrow_audit_after_suspension(): void
 {
     $club = $this->club('stale-suspension');
     config(['instance.organisation_code' => $club->code]);
     $this->seed(DatabaseSeeder::class);
     [$user, $member] = $this->actor($club, 'member', 'M');
     [$reviewer] = $this->actor($club, 'president-admin', 'R');
     $service = app(\App\Services\ProfileUpdates\ProfileUpdateWriteService::class);
     $request = $service->submit($club, $user, ['changes' => ['phone' => '777']]);
     $member->update(['phone' => '999']);

     AuditLog::creating(function (AuditLog $audit) use ($club): void {
         if ($audit->action === 'profile_updates.stale_conflict') {
             $this->assertSame(0, \Illuminate\Support\Facades\DB::transactionLevel());
             Club::whereKey($club->id)->update(['is_active' => false]);
         }
     });
     try {
         $service->approve($club, $reviewer, $request, 1);
         $this->fail('Expected the stale approval conflict.');
     } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $exception) {
         $this->assertSame('Member profile changed after submission. Withdraw and resubmit.', $exception->getMessage());
     }
     $this->assertFalse($club->refresh()->is_active);
     $this->assertSame('999', $member->refresh()->phone);
     $this->assertSame('pending', $request->refresh()->status);
     $audit = AuditLog::where('action', 'profile_updates.stale_conflict')->sole();
     $this->assertSame($club->id, $audit->club_id);
     $this->assertSame($request->id, $audit->auditable_id);
     $this->assertSame(['field_keys' => ['phone']], $audit->metadata);
 }

 public function test_changed_file_scope_is_lifecycle_safe():void{$r=dirname(__DIR__,3);$subject='Add profile update request workflows';exec('git -C "'.$r.'" log --all --format=%H%x09%s',$logs,$code);$this->assertSame(0,$code);$matches=[];foreach($logs as $line){[$h,$s]=array_pad(explode("	",$line,2),2,'');if($s===$subject)$matches[]=$h;}if($matches){exec('git -C "'.$r.'" diff-tree --no-commit-id --name-only -r '.$matches[0],$files,$status);$this->assertSame(0,$status);}else{$this->assertSame('ac6b6d43b63791c94e29c8d597cde187ccf7920a',trim(shell_exec('git -C "'.$r.'" rev-parse HEAD')));exec('git -C "'.$r.'" status --porcelain=v1 -uall',$raw,$status);$files=array_map(fn($x)=>str_replace('\\','/',substr($x,3)),$raw);}$allowed=['backend/tests/Feature/AuthMeRoleAwareResponseTest.php','backend/app/Http/Controllers/Api/V1/ProfileUpdates/ProfileUpdateRequestController.php','backend/app/Http/Requests/ProfileUpdates/RejectProfileUpdateRequest.php','backend/app/Http/Requests/ProfileUpdates/StoreProfileUpdateRequest.php','backend/app/Http/Requests/ProfileUpdates/TransitionProfileUpdateRequest.php','backend/app/Services/ProfileUpdates/ProfileUpdateWriteService.php','backend/docs/milestone_3hb_profile_update_request_lifecycle_workflow.md','backend/routes/api.php','backend/tests/Feature/Milestone3haProfileUpdateRequestDatabaseReadFoundationTest.php','backend/tests/Feature/Milestone3hbProfileUpdateRequestLifecycleWorkflowTest.php'];sort($files);sort($allowed);$this->assertSame($allowed,$files);}
 private function club($x):Club{return Club::create(['name'=>$x,'code'=>$x.uniqid(),'timezone'=>'Asia/Kolkata','is_active'=>true]);}private function actor($c,$role,$code):array{$u=User::factory()->create();ClubMembership::create(['club_id'=>$c->id,'user_id'=>$u->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'status'=>'active','joined_at'=>now()]);$m=Member::create(['club_id'=>$c->id,'user_id'=>$u->id,'member_code'=>$code.uniqid(),'full_name'=>'Duplicate Member','status'=>'active']);return[$u,$m];}}
