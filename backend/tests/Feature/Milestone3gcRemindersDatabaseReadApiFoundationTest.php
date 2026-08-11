<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\ClubReminder;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3gcRemindersDatabaseReadApiFoundationTest extends TestCase
{
    private const BASE = '50ef1c9d451df77d808d28068297cebb9bf668a5';
    private const SUBJECT = 'Add reminders read API foundation';
    private string $repo = __DIR__.'/../../..';
    use RefreshDatabase;

    public function test_schema_model_and_permissions(): void
    {
        $this->assertTrue(Schema::hasTable('club_reminders'));
        foreach (['club_id','title','priority','visibility','status','is_all_day','due_date','due_at','activated_at','completed_at','cancelled_at','lock_version'] as $column) {
            $this->assertTrue(Schema::hasColumn('club_reminders', $column));
        }
        $this->assertSame(['draft','active','completed','cancelled'], ClubReminder::statuses());
        $this->assertSame(['normal','high','urgent'], ClubReminder::priorities());
        $this->seed(DatabaseSeeder::class);
        foreach (['president-admin','secretary'] as $slug) {
            $role = Role::where('slug', $slug)->firstOrFail();
            $this->assertTrue($role->permissions->contains('slug','reminders.view'));
            $this->assertTrue($role->permissions->contains('slug','reminders.manage'));
        }
        foreach (['treasurer','member'] as $slug) {
            $role = Role::where('slug', $slug)->firstOrFail();
            $this->assertTrue($role->permissions->contains('slug','reminders.view'));
            $this->assertFalse($role->permissions->contains('slug','reminders.manage'));
        }
    }

    public function test_management_and_read_only_visibility_matrix_and_privacy(): void
    {
        $club = $this->club();
        $president = $this->user($club, 'president-admin');
        $member = $this->user($club, 'member');
        $draft = $this->reminder($club, ['status'=>'draft','visibility'=>'members']);
        $active = $this->reminder($club, ['title'=>'Duplicate','status'=>'active','visibility'=>'members','activated_at'=>now()]);
        $management = $this->reminder($club, ['status'=>'active','visibility'=>'management','activated_at'=>now()]);
        $cancelled = $this->reminder($club, ['title'=>'Duplicate','status'=>'cancelled','visibility'=>'members','activated_at'=>now(),'cancelled_at'=>now(),'cancellation_reason'=>'Private']);
        $cancelledDraft = $this->reminder($club, ['status'=>'cancelled','visibility'=>'members','activated_at'=>null,'cancelled_at'=>now()]);

        Sanctum::actingAs($president);
        $this->getJson("/api/v1/clubs/{$club->id}/reminders?per_page=100")->assertOk()->assertJsonPath('meta.total', 5);
        $this->getJson("/api/v1/clubs/{$club->id}/reminders/{$draft->id}")->assertOk()->assertJsonPath('data.visibility','members');

        Sanctum::actingAs($member);
        $response = $this->getJson("/api/v1/clubs/{$club->id}/reminders?per_page=100")->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$active->id, $cancelled->id], $ids);
        $this->assertNotContains($management->id, $ids);
        $this->assertNotContains($cancelledDraft->id, $ids);
        $cancelledData = collect($response->json('data'))->firstWhere('id', $cancelled->id);
        $this->assertArrayNotHasKey('cancellation_reason', $cancelledData);
        $this->assertArrayNotHasKey('visibility', $cancelledData);
        $this->assertArrayNotHasKey('description', $cancelledData);
        $this->getJson("/api/v1/clubs/{$club->id}/reminders/{$management->id}")->assertNotFound();
    }

    public function test_all_day_timed_overdue_filters_order_pagination_and_stable_ids(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00 UTC');
        $club = $this->club();
        $officer = $this->user($club, 'president-admin');
        $overdue = $this->reminder($club, ['title'=>'Same','status'=>'active','activated_at'=>now(),'due_date'=>'2026-06-23']);
        $upcoming = $this->reminder($club, ['title'=>'Same','status'=>'active','is_all_day'=>false,'due_date'=>null,'due_at'=>'2026-06-25 12:00:00','activated_at'=>now()]);
        $this->reminder($club, ['title'=>'Other','status'=>'completed','completed_at'=>now()]);
        Sanctum::actingAs($officer);

        $this->getJson("/api/v1/clubs/{$club->id}/reminders?overdue=1&q=Same")->assertOk()
            ->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$overdue->id)->assertJsonPath('data.0.overdue',true);
        $list = $this->getJson("/api/v1/clubs/{$club->id}/reminders?q=Same&per_page=1")->assertOk();
        $list->assertJsonPath('data.0.id',$overdue->id)->assertJsonPath('meta.total',2)->assertJsonPath('meta.per_page',1);
        $this->getJson("/api/v1/clubs/{$club->id}/reminders/{$upcoming->id}")->assertOk()
            ->assertJsonPath('data.is_all_day',false)->assertJsonPath('data.overdue',false)
            ->assertJsonPath('data.club_timezone','Asia/Kolkata');
        Carbon::setTestNow();
    }

    public function test_authentication_inactive_cross_club_empty_and_read_only_routes(): void
    {
        $club = $this->club();
        $other = $this->club('OTHER');
        $record = $this->reminder($club);
        $this->getJson("/api/v1/clubs/{$club->id}/reminders")->assertUnauthorized();
        Sanctum::actingAs($this->user($club,'member','inactive'));
        $this->getJson("/api/v1/clubs/{$club->id}/reminders")->assertForbidden();
        Sanctum::actingAs($this->user($other,'president-admin'));
        $this->getJson("/api/v1/clubs/{$club->id}/reminders")->assertForbidden();
        $dual = $this->user($club,'president-admin');
        ClubMembership::create(['club_id'=>$other->id,'user_id'=>$dual->id,'role_id'=>Role::where('slug','president-admin')->firstOrFail()->id,'status'=>'active','joined_at'=>now()]);
        Sanctum::actingAs($dual);
        $this->getJson("/api/v1/clubs/{$other->id}/reminders/{$record->id}")->assertNotFound();
        $this->getJson("/api/v1/clubs/{$other->id}/reminders")->assertOk()->assertJsonPath('meta.total',0);

        $commit = $this->findCommit();
        if ($commit !== null) {
            $historicalRoutes = $this->git(['show', $commit.':backend/routes/api.php']);
            $this->assertStringContainsString("Route::get('/clubs/{club}/reminders'", $historicalRoutes);
            $this->assertStringNotContainsString("Route::post('/clubs/{club}/reminders'", $historicalRoutes);
            $this->assertStringNotContainsString("Route::patch('/clubs/{club}/reminders/{reminder}'", $historicalRoutes);
        } else {
            $routes = collect(Route::getRoutes())->filter(fn($r)=>str_contains($r->uri(),'clubs/{club}/reminders'));
            $this->assertCount(2,$routes);
            foreach ($routes as $route) $this->assertSame(['GET','HEAD'],$route->methods());
        }
    }

    public function test_3gc_changed_file_scope_and_read_only_history_are_lifecycle_safe(): void
    {
        $commit = $this->findCommit();
        if ($commit === null) {
            $this->assertSame(self::BASE, trim($this->git(['rev-parse', 'HEAD'])));
            foreach ($this->gitLines(['status', '--porcelain=v1', '-uall']) as $line) {
                $path = str_replace('\\', '/', trim(substr($line, 3)));
                $this->assertTrue(str_starts_with($path, 'backend/'));
                $this->assertNotSame('app/page.tsx', $path);
            }
            return;
        }
        $routes = $this->git(['show', $commit.':backend/routes/api.php']);
        $this->assertStringContainsString("Route::get('/clubs/{club}/reminders'", $routes);
        $this->assertStringNotContainsString("Route::post('/clubs/{club}/reminders'", $routes);
        $this->assertStringNotContainsString("Route::patch('/clubs/{club}/reminders/{reminder}'", $routes);
        $this->assertStringNotContainsString("Route::delete('/clubs/{club}/reminders/{reminder}'", $routes);
        foreach ($this->gitLines(['diff-tree', '--no-commit-id', '--name-only', '-r', $commit]) as $file) {
            $this->assertNotSame('app/page.tsx', $file);
            $this->assertFalse(str_contains($file, 'WriteService'));
        }
    }
    private function club(string $code='REM'): Club
    {
        $this->seed(DatabaseSeeder::class);
        return Club::create(['name'=>'Reminder Test Club','code'=>$code.uniqid(),'timezone'=>'Asia/Kolkata','is_active'=>true]);
    }

    private function user(Club $club,string $role,string $status='active'): User
    {
        $user=User::factory()->create();
        ClubMembership::create(['club_id'=>$club->id,'user_id'=>$user->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'status'=>$status,'joined_at'=>now()]);
        return $user;
    }

    private function reminder(Club $club,array $overrides=[]): ClubReminder
    {
        return ClubReminder::create(array_merge([
            'club_id'=>$club->id,'title'=>'Synthetic Reminder','description'=>'Synthetic only',
            'priority'=>'normal','visibility'=>'members','status'=>'active','is_all_day'=>true,
            'due_date'=>'2026-07-01','due_at'=>null,'activated_at'=>now(),'lock_version'=>1,
        ],$overrides));
    }

    private function findCommit(): ?string
    {
        $matches = [];
        foreach ($this->gitLines(['log', '--all', '--oneline']) as $line) {
            [$hash, $subject] = array_pad(explode(' ', $line, 2), 2, '');
            if ($subject === self::SUBJECT) $matches[] = trim($this->git(['rev-parse', $hash]));
        }
        $this->assertLessThanOrEqual(1, count($matches));
        return $matches[0] ?? null;
    }

    private function gitLines(array $args): array
    {
        return array_values(array_filter(explode("\n", $this->git($args))));
    }

    private function git(array $args): string
    {
        $command = array_merge(['git', '-C', realpath($this->repo) ?: $this->repo], $args);
        exec(implode(' ', array_map('escapeshellarg', $command)).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
        return implode("\n", $output);
    }}
