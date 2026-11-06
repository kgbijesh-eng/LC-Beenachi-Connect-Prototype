<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3gjReportsCatalogSharedQueryFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT_KEYS = [
        'membership-summary',
        'events-register',
        'projects-register',
        'reminders-register',
        'collections-summary',
        'member-outstanding-balances',
        'charge-collection-status',
        'payment-register',
        'receipt-register',
    ];

    public function test_catalog_roles_and_denials(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::where('code','LCB')->firstOrFail();
        foreach (['president-admin','secretary','treasurer'] as $slug) {
            $user=$this->user($club,$slug); Sanctum::actingAs($user);
            config(['instance.organisation_code' => $club->code]);
            $response=$this->getJson("/api/v1/clubs/{$club->id}/reports")->assertOk()->assertJsonCount(9,'data');
            $keys=array_column($response->json('data'),'key');
            $this->assertSame(self::REPORT_KEYS,$keys);
        }
        Sanctum::actingAs($this->user($club,'member'));
        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/reports")->assertForbidden();
        Sanctum::actingAs($this->user($club,'president-admin'));
        $this->getJson("/api/v1/clubs/{$club->id}/reports/unknown")->assertNotFound();
    }

    public function test_unauthenticated_inactive_cross_club_and_no_mutations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club=Club::where('code','LCB')->firstOrFail();
        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/reports")->assertUnauthorized();
        Sanctum::actingAs($this->user($club,'president-admin','inactive'));
        $this->getJson("/api/v1/clubs/{$club->id}/reports")->assertForbidden();
        $other=Club::create(['name'=>'Other','code'=>'REP-'.uniqid(),'timezone'=>'Asia/Kolkata','is_active'=>true]);
        Sanctum::actingAs($this->user($other,'president-admin'));
        $this->getJson("/api/v1/clubs/{$club->id}/reports")->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/reports")->assertMethodNotAllowed();
    }

    private function user(Club $club,string $role,string $status='active'): User
    {
        $u=User::factory()->create();
        ClubMembership::create(['club_id'=>$club->id,'user_id'=>$u->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'status'=>$status,'joined_at'=>now()]);
        return $u;
    }
}
