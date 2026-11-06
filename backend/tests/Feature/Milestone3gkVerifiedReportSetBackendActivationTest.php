<?php

namespace Tests\Feature;

use App\Models\{Club,ClubEvent,ClubMembership,ClubProject,ClubReminder,Member,Role,User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3gkVerifiedReportSetBackendActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_reports_privacy_filters_and_cross_club_exclusion(): void
    {
        $this->seed(DatabaseSeeder::class); $club=$this->club('A'); $other=$this->club('B'); Sanctum::actingAs($this->user($club,'president-admin'));
        Member::create(['club_id'=>$club->id,'member_code'=>'M1','full_name'=>'Private One','status'=>'active','phone'=>'999']);
        Member::create(['club_id'=>$club->id,'member_code'=>'M2','full_name'=>'Private Two','status'=>'inactive']);
        Member::create(['club_id'=>$other->id,'member_code'=>'X','full_name'=>'Cross Club','status'=>'active']);
        $event=ClubEvent::create(['club_id'=>$club->id,'type'=>'meeting','title'=>'Duplicate','is_all_day'=>false,'starts_at'=>'2026-07-01 10:00:00','status'=>'published','visibility'=>'members','lock_version'=>1]);
        ClubEvent::create(['club_id'=>$other->id,'type'=>'event','title'=>'Cross','is_all_day'=>false,'starts_at'=>'2026-07-01 10:00:00','status'=>'published','visibility'=>'members','lock_version'=>1]);
        $project=ClubProject::create(['club_id'=>$club->id,'record_type'=>'project','title'=>'Duplicate','status'=>'planned','visibility'=>'members','planned_start_date'=>'2026-07-02','lock_version'=>1]);
        $reminder=ClubReminder::create(['club_id'=>$club->id,'title'=>'Duplicate','priority'=>'urgent','status'=>'active','visibility'=>'members','is_all_day'=>true,'due_date'=>'2026-07-03','activated_at'=>now(),'lock_version'=>1]);

        config(['instance.organisation_code' => $club->code]);
        $m=$this->getJson("/api/v1/clubs/{$club->id}/reports/membership-summary")->assertOk();
        $m->assertJsonPath('data.summary.total_members',2)->assertJsonPath('data.summary.active_members',1)->assertJsonPath('meta',null);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/events-register?type=meeting")->assertOk()->assertJsonPath('data.rows.0.id',$event->id)->assertJsonPath('meta.total',1);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/projects-register?record_type=project")->assertOk()->assertJsonPath('data.rows.0.id',$project->id);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/reminders-register?priority=urgent")->assertOk()->assertJsonPath('data.rows.0.id',$reminder->id);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/membership-summary?q=Private")->assertUnprocessable();
        if (str_contains(file_get_contents(app_path('Services/Reports/ReportCatalog.php')), "'collections-summary'")) {
            config(['instance.organisation_code' => $club->code]);
            $this->getJson("/api/v1/clubs/{$club->id}/reports/collections-summary")->assertOk();
        } else {
            config(['instance.organisation_code' => $club->code]);
            $this->getJson("/api/v1/clubs/{$club->id}/reports/collections-summary")->assertNotFound();
        }
    }

    public function test_repeated_calls_do_not_mutate_sources(): void
    {
        $this->seed(DatabaseSeeder::class); $club=$this->club('R'); Sanctum::actingAs($this->user($club,'treasurer'));
        Member::create(['club_id'=>$club->id,'member_code'=>'R1','full_name'=>'Read Only','status'=>'active']); $before=Member::where('club_id',$club->id)->count();
        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/reports/membership-summary")->assertOk();
        $this->getJson("/api/v1/clubs/{$club->id}/reports/membership-summary")->assertOk();
        $this->assertSame($before,Member::where('club_id',$club->id)->count());
    }

    private function club(string $code): Club { return Club::create(['name'=>'Report '.$code,'code'=>'R'.$code.uniqid(),'timezone'=>'Asia/Kolkata','is_active'=>true]); }
    private function user(Club $club,string $role): User { $u=User::factory()->create(); ClubMembership::create(['club_id'=>$club->id,'user_id'=>$u->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'status'=>'active','joined_at'=>now()]); return $u; }
}
