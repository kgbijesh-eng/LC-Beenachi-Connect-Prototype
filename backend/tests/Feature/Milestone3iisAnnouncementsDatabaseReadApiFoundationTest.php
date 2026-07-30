<?php

namespace Tests\Feature;

use App\Exceptions\Announcements\InvalidClubAnnouncementReadStateException;
use App\Http\Controllers\Api\V1\Announcements\ClubAnnouncementController;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;
use Tests\TestCase;

class Milestone3iisAnnouncementsDatabaseReadApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_exact_schema_indexes_foreign_keys_constants_and_factory_states(): void
    {
        $this->assertTrue(Schema::hasTable('club_announcements'));
        $columns = [
            'id', 'club_id', 'title', 'body', 'priority', 'audience', 'status',
            'scheduled_publish_at', 'published_at', 'expires_at', 'withdrawn_at',
            'withdrawal_reason', 'archived_at', 'replaces_announcement_id',
            'published_by_user_id', 'published_by_role', 'created_by_user_id',
            'updated_by_user_id', 'withdrawn_by_user_id', 'archived_by_user_id',
            'lock_version', 'created_at', 'updated_at',
        ];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('club_announcements', $column), "Missing {$column}");
        }
        foreach (['deleted_at', 'external_link_url', 'attachment_id', 'delivered_at', 'read_at', 'expired_at'] as $excluded) {
            $this->assertFalse(Schema::hasColumn('club_announcements', $excluded));
        }

        $indexColumns = collect(Schema::getIndexes('club_announcements'))->pluck('columns')->map(fn ($value) => array_values($value))->all();
        foreach ([
            ['club_id', 'status', 'published_at'], ['club_id', 'audience', 'status', 'published_at'],
            ['club_id', 'priority', 'published_at'], ['club_id', 'expires_at'],
            ['club_id', 'scheduled_publish_at'], ['club_id', 'updated_at'],
            ['club_id', 'replaces_announcement_id'],
        ] as $expected) {
            $this->assertContains($expected, $indexColumns);
        }

        $foreignColumns = collect(Schema::getForeignKeys('club_announcements'))->pluck('columns')->flatten()->all();
        foreach (['club_id', 'replaces_announcement_id', 'published_by_user_id', 'created_by_user_id', 'updated_by_user_id', 'withdrawn_by_user_id', 'archived_by_user_id'] as $column) {
            $this->assertContains($column, $foreignColumns);
        }

        $this->assertSame(['draft', 'published', 'withdrawn', 'archived'], ClubAnnouncement::statuses());
        $this->assertSame(['normal', 'high', 'urgent'], ClubAnnouncement::priorities());
        $this->assertSame(['club-members', 'officers'], ClubAnnouncement::audiences());
        $this->assertSame(['president', 'secretary'], ClubAnnouncement::publisherRoles());
        $this->assertNotContains('expired', ClubAnnouncement::statuses());

        $draft = ClubAnnouncement::factory()->draft()->create();
        $published = ClubAnnouncement::factory()->published()->create();
        $expired = ClubAnnouncement::factory()->expired()->create();
        $withdrawn = ClubAnnouncement::factory()->withdrawn()->create();
        $archived = ClubAnnouncement::factory()->archived()->create();
        $this->assertNotNull($draft->created_by_user_id);
        $this->assertSame('published', $published->status);
        $this->assertTrue($expired->isEffectivelyExpired());
        $this->assertSame('withdrawn', $withdrawn->status);
        $this->assertSame('archived', $archived->status);

        $club = $this->club('NOTNULL');
        $this->expectException(QueryException::class);
        DB::table('club_announcements')->insert($this->rawRow($club, ['created_by_user_id' => null]));
    }

    public function test_permissions_are_exactly_assigned_for_read_and_management_read(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach (['president-admin', 'secretary'] as $slug) {
            $permissions = Role::where('slug', $slug)->firstOrFail()->permissions->pluck('slug');
            $this->assertTrue($permissions->contains('announcements.view'));
            $this->assertTrue($permissions->contains('announcements.management.view'));
        }
        foreach (['treasurer', 'member'] as $slug) {
            $permissions = Role::where('slug', $slug)->firstOrFail()->permissions->pluck('slug');
            $this->assertTrue($permissions->contains('announcements.view'));
            $this->assertFalse($permissions->contains('announcements.management.view'));
        }
    }

    public function test_management_treasurer_and_member_visibility_boundaries(): void
    {
        Carbon::setTestNow('2026-07-20 12:00:00 UTC');
        foreach (['president-admin', 'secretary'] as $slug) {
            $club = $this->club('M-'.$slug);
            $manager = $this->actor($club, $slug);
            $draft = $this->announcement($club, ['status' => 'draft', 'audience' => 'officers']);
            Sanctum::actingAs($manager);
            $payload = $this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=current")
                ->assertOk()->assertJsonPath('data.0.id', $draft->id)->json('data.0');
            $this->assertSame('draft', $payload['stored_status']);
            $this->assertSame('officers', $payload['audience']);
            $this->assertArrayHasKey('created_by_user_id', $payload);
        }

        $treasurerClub = $this->club('O-treasurer');
        $treasurer = $this->actor($treasurerClub, 'treasurer');
        $treasurerMemberNotice = $this->announcement($treasurerClub, $this->published(['title' => 'Visible member notice']));
        $treasurerOfficerNotice = $this->announcement($treasurerClub, $this->published(['title' => 'Officer notice', 'audience' => 'officers']));
        $treasurerDraft = $this->announcement($treasurerClub, ['title' => 'Hidden draft', 'audience' => 'officers']);
        Sanctum::actingAs($treasurer);
        $treasurerData = $this->getJson("/api/v1/clubs/{$treasurerClub->id}/announcements")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $treasurerMemberNotice->id])
            ->assertJsonFragment(['id' => $treasurerOfficerNotice->id])
            ->assertJsonMissing(['id' => $treasurerDraft->id])
            ->json('data');
        foreach ($treasurerData as $payload) {
            foreach (['club_id', 'audience', 'stored_status', 'created_by_user_id', 'lock_version', 'scheduled_publish_at', 'replaces_announcement_id'] as $hidden) {
                $this->assertArrayNotHasKey($hidden, $payload);
            }
            $this->assertSame('President', $payload['published_by_role']);
        }
        $treasurerDetail = $this->getJson("/api/v1/clubs/{$treasurerClub->id}/announcements/{$treasurerOfficerNotice->id}")
            ->assertOk()
            ->json('data');
        foreach (['club_id', 'audience', 'stored_status', 'created_by_user_id', 'lock_version', 'scheduled_publish_at', 'replaces_announcement_id'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $treasurerDetail);
        }
        $this->getJson("/api/v1/clubs/{$treasurerClub->id}/announcements/{$treasurerDraft->id}")->assertNotFound();
        $this->getJson("/api/v1/clubs/{$treasurerClub->id}/announcements?audience=officers")->assertForbidden();

        $memberClub = $this->club('O-member');
        $member = $this->actor($memberClub, 'member');
        $memberNotice = $this->announcement($memberClub, $this->published(['title' => 'Visible member notice']));
        $memberOfficerNotice = $this->announcement($memberClub, $this->published(['title' => 'Officer notice', 'audience' => 'officers']));
        $memberDraft = $this->announcement($memberClub, ['title' => 'Hidden draft']);
        Sanctum::actingAs($member);
        $memberPayload = $this->getJson("/api/v1/clubs/{$memberClub->id}/announcements")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $memberNotice->id)
            ->assertJsonMissing(['id' => $memberOfficerNotice->id])
            ->assertJsonMissing(['id' => $memberDraft->id])
            ->json('data.0');
        foreach (['club_id', 'audience', 'stored_status', 'created_by_user_id', 'lock_version', 'scheduled_publish_at', 'replaces_announcement_id'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $memberPayload);
        }
        $this->getJson("/api/v1/clubs/{$memberClub->id}/announcements/{$memberOfficerNotice->id}")->assertNotFound();
        $this->getJson("/api/v1/clubs/{$memberClub->id}/announcements/{$memberDraft->id}")->assertNotFound();
    }

    public function test_cross_audience_predecessor_context_is_concealed_from_non_management_reader(): void
    {
        $club = $this->club('REPLACE-AUDIENCE');
        $treasurer = $this->actor($club, 'treasurer');
        $memberPredecessor = $this->announcement($club, $this->published(['audience' => 'club-members']));
        $officerReplacement = $this->announcement($club, $this->published([
            'audience' => 'officers',
            'replaces_announcement_id' => $memberPredecessor->id,
        ]));

        Sanctum::actingAs($treasurer);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements/{$officerReplacement->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.replaces_announcement_id');
    }

    public function test_authentication_permissions_inactive_membership_and_cross_club_access_fail_closed(): void
    {
        $club = $this->club('AUTH-A');
        $other = $this->club('AUTH-B');
        $record = $this->announcement($club, $this->published());
        $this->getJson("/api/v1/clubs/{$club->id}/announcements")->assertUnauthorized();

        Sanctum::actingAs($this->actor($club, 'member', 'inactive'));
        $this->getJson("/api/v1/clubs/{$club->id}/announcements")->assertForbidden();

        Sanctum::actingAs($this->actor($other, 'member'));
        $this->getJson("/api/v1/clubs/{$club->id}/announcements")->assertForbidden();

        Sanctum::actingAs($this->actor($club, 'member'));
        $this->getJson("/api/v1/clubs/{$other->id}/announcements/{$record->id}")->assertForbidden();
        $this->getJson("/api/v1/clubs/{$club->id}/announcements/999999")->assertNotFound();

        $otherRecord = $this->announcement($other, $this->published());
        $this->getJson("/api/v1/clubs/{$club->id}/announcements/{$otherRecord->id}")->assertNotFound();
    }

    public function test_expiry_boundaries_scopes_archived_rules_and_scheduled_metadata(): void
    {
        Carbon::setTestNow('2026-07-20 12:00:00 UTC');
        $club = $this->club('TIME');
        $manager = $this->actor($club, 'president-admin');
        $member = $this->actor($club, 'member');
        $before = $this->announcement($club, $this->published(['title' => 'Before', 'expires_at' => '2026-07-20 12:00:01']));
        $at = $this->announcement($club, $this->published(['title' => 'At', 'expires_at' => '2026-07-20 12:00:00']));
        $after = $this->announcement($club, $this->published(['title' => 'After', 'expires_at' => '2026-07-20 11:59:59']));
        $scheduledDraft = $this->announcement($club, ['title' => 'Scheduled draft', 'scheduled_publish_at' => '2026-07-19 12:00:00']);
        $withdrawn = $this->announcement($club, $this->withdrawn(['title' => 'Withdrawn']));
        $archived = $this->announcement($club, $this->archived(['title' => 'Archived']));

        Sanctum::actingAs($member);
        $current = $this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=current")->assertOk()->json('data');
        $history = $this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=history")->assertOk()->json('data');
        $this->assertSame([$before->id], array_column($current, 'id'));
        $this->assertEqualsCanonicalizing([$at->id, $after->id], array_column($history, 'id'));

        Sanctum::actingAs($manager);
        $managementCurrent = array_column($this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=current&per_page=50")->assertOk()->json('data'), 'id');
        $managementHistory = array_column($this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=history&per_page=50")->assertOk()->json('data'), 'id');
        $managementAll = array_column($this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=all&per_page=50")->assertOk()->json('data'), 'id');
        $this->assertContains($before->id, $managementCurrent);
        $this->assertContains($scheduledDraft->id, $managementCurrent);
        $this->assertNotContains($scheduledDraft->id, $managementHistory);
        $this->assertContains($at->id, $managementHistory);
        $this->assertContains($after->id, $managementHistory);
        $this->assertContains($withdrawn->id, $managementHistory);
        $this->assertEmpty(array_intersect($managementCurrent, $managementHistory));
        $this->assertNotContains($archived->id, $managementCurrent);
        $this->assertNotContains($archived->id, $managementHistory);
        $this->assertContains($archived->id, $managementAll);
    }

    public function test_permission_precedes_validation_for_management_only_filters(): void
    {
        $club = $this->club('FILTER-AUTH');
        $member = $this->actor($club, 'member');
        Sanctum::actingAs($member);
        foreach (['status=invalid', 'audience=invalid', 'effective_state=invalid', 'scheduled=invalid', 'scope=all'] as $query) {
            $this->getJson("/api/v1/clubs/{$club->id}/announcements?{$query}")->assertForbidden();
        }

        Sanctum::actingAs($this->actor($club, 'president-admin'));
        foreach (['status=invalid', 'audience=invalid', 'effective_state=invalid', 'scheduled=invalid'] as $query) {
            $this->getJson("/api/v1/clubs/{$club->id}/announcements?{$query}")->assertUnprocessable();
        }
    }

    public function test_filter_compatibility_unknown_filters_and_literal_title_search(): void
    {
        $club = $this->club('SEARCH');
        $manager = $this->actor($club, 'president-admin');
        $member = $this->actor($club, 'member');
        $percent = $this->announcement($club, $this->published(['title' => 'Budget 100% Ready']));
        $underscore = $this->announcement($club, $this->published(['title' => 'Literal_Agenda']));
        $quote = $this->announcement($club, $this->published(['title' => "President's Note"]));
        $this->announcement($club, $this->published(['title' => 'Body does not count', 'body' => 'NeedleBodyOnly']));
        $this->announcement($club, $this->published(['title' => 'Other club percent']), $this->club('SEARCH-OTHER'));
        $this->announcement($club, $this->published(['title' => 'Officer 100%', 'audience' => 'officers']));
        $this->announcement($club, ['title' => 'Draft 100%']);

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements?q=%25")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $percent->id);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements?q=Literal_Agenda")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $underscore->id);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements?q=President%27s")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $quote->id);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements?q=NeedleBodyOnly")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/clubs/{$club->id}/announcements?unknown=x")->assertUnprocessable();

        Sanctum::actingAs($manager);
        foreach (['scope=current&status=withdrawn', 'scope=current&status=archived', 'scope=history&status=draft', 'scope=history&status=archived', 'status=draft&effective_state=expired', 'scope=current&effective_state=expired', 'scope=history&effective_state=current'] as $query) {
            $this->getJson("/api/v1/clubs/{$club->id}/announcements?{$query}")->assertUnprocessable();
        }
    }

    public function test_club_local_date_boundaries_normalized_filters_and_deterministic_pagination(): void
    {
        foreach ([['Asia/Kolkata', '2026-07-20 18:30:00', '2026-07-21'], ['America/New_York', '2026-03-08 05:00:00', '2026-03-08']] as [$timezone, $utc, $localDate]) {
            $club = $this->club('DATE-'.str_replace('/', '-', $timezone), $timezone);
            $manager = $this->actor($club, 'president-admin');
            $record = $this->announcement($club, $this->published(['published_at' => $utc]));
            Sanctum::actingAs($manager);
            $this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=all&published_from={$localDate}&published_to={$localDate}&per_page=1")
                ->assertOk()->assertJsonPath('data.0.id', $record->id)
                ->assertJsonPath('meta.filters.published_from', $localDate)
                ->assertJsonPath('meta.filters.published_to', $localDate)
                ->assertJsonPath('meta.filters.per_page', 1);
        }

        $club = $this->club('PAGE');
        $member = $this->actor($club, 'member');
        $one = $this->announcement($club, $this->published(['published_at' => '2026-07-20 10:00:00']));
        $two = $this->announcement($club, $this->published(['published_at' => '2026-07-20 10:00:00']));
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements?per_page=1&direction=asc")
            ->assertOk()->assertJsonPath('data.0.id', min($one->id, $two->id))->assertJsonPath('meta.total', 2);
    }

    public function test_management_all_integrity_failures_are_generic_and_never_partial(): void
    {
        $club = $this->club('INTEGRITY');
        $manager = $this->actor($club, 'president-admin');
        Sanctum::actingAs($manager);
        $this->announcement($club, $this->published(['title' => 'Valid row']));

        foreach ([
            ['status' => 'unknown'],
            ['priority' => 'unknown'],
            ['audience' => 'unknown'],
            ['status' => 'published', 'published_at' => null],
            ['status' => 'draft', 'published_at' => '2026-07-20 10:00:00'],
        ] as $invalid) {
            $record = $this->announcement($club, array_merge($this->published(['title' => 'Invalid secret']), $invalid));
            $response = $this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=all&per_page=50")
                ->assertStatus(500)->assertExactJson(['message' => 'Announcement records are unavailable because stored data failed integrity validation.']);
            $this->assertStringNotContainsString((string) $record->id, $response->getContent());
            $this->assertStringNotContainsString('Invalid secret', $response->getContent());
            $record->delete();
        }

        $unresolvedActor = $this->announcement($club, $this->published());
        $unresolvedActor->setRelation('publisher', null);
        $this->expectException(InvalidClubAnnouncementReadStateException::class);
        $unresolvedActor->assertReadableIntegrity($club);
    }

    public function test_cross_club_replacement_never_leaks_and_invalid_detail_is_concealed(): void
    {
        $club = $this->club('REPLACE-A');
        $other = $this->club('REPLACE-B');
        $manager = $this->actor($club, 'president-admin');
        $foreign = $this->announcement($other, $this->published());
        $record = $this->announcement($club, $this->published(['replaces_announcement_id' => $foreign->id]));
        Sanctum::actingAs($manager);
        $response = $this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=all")->assertStatus(500);
        $this->assertStringNotContainsString((string) $foreign->id, $response->getContent());
        $this->getJson("/api/v1/clubs/{$club->id}/announcements/{$record->id}")->assertNotFound();

        $self = $this->announcement($club, $this->published());
        $self->update(['replaces_announcement_id' => $self->id]);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements/{$self->id}")->assertNotFound();
    }

    public function test_read_routes_remain_non_mutating_after_narrow_write_adapters_are_added(): void
    {
        $routes = collect(Route::getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'clubs/{club}/announcements'));
        $this->assertCount(11, $routes);
        $this->assertCount(2, $routes->filter(fn ($route) => in_array('GET', $route->methods(), true)));
        $this->assertCount(8, $routes->filter(fn ($route) => in_array('POST', $route->methods(), true)));
        $this->assertCount(1, $routes->filter(fn ($route) => in_array('PATCH', $route->methods(), true)));
        $this->assertCount(0, $routes->filter(fn ($route) => in_array('DELETE', $route->methods(), true)));

        $publicEndpoints = collect((new ReflectionClass(ClubAnnouncementController::class))->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->filter(fn ($method) => $method->getDeclaringClass()->getName() === ClubAnnouncementController::class)
            ->pluck('name')->sort()->values()->all();
        $this->assertSame(['approve', 'archive', 'index', 'publish', 'rejectApproval', 'revokeApproval', 'show', 'store', 'storeReplacement', 'update', 'withdraw'], $publicEndpoints);

        $club = $this->club('NO-WRITE');
        $member = $this->actor($club, 'member');
        Sanctum::actingAs($member);
        $before = ClubAnnouncement::count();
        $this->getJson("/api/v1/clubs/{$club->id}/announcements")->assertOk();
        $this->assertSame($before, ClubAnnouncement::count());
        $this->postJson("/api/v1/clubs/{$club->id}/announcements", [])->assertForbidden();
        $this->putJson("/api/v1/clubs/{$club->id}/announcements/1", [])->assertMethodNotAllowed();
        $this->patchJson("/api/v1/clubs/{$club->id}/announcements/1", [])->assertNotFound();
        $this->deleteJson("/api/v1/clubs/{$club->id}/announcements/1")->assertMethodNotAllowed();
    }

    public function test_frontend_fabricated_announcement_suppression_remains_in_force(): void
    {
        $frontend = file_get_contents(base_path('../app/page.tsx'));
        $workspace = file_get_contents(base_path('../app/AnnouncementsBackend.tsx'));
        $this->assertStringContainsString('AnnouncementsBackend', $frontend);
        $this->assertStringContainsString('authenticatedFetch', $workspace);
        $this->assertStringNotContainsString('District Governor Visit Planning', $frontend);
    }

    private function club(string $code, string $timezone = 'UTC'): Club
    {
        $this->seed(DatabaseSeeder::class);

        return Club::create(['name' => 'Synthetic '.$code, 'code' => $code.'-'.uniqid(), 'timezone' => $timezone, 'is_active' => true]);
    }

    private function actor(Club $club, string $roleSlug, string $status = 'active'): User
    {
        $user = User::factory()->create();
        ClubMembership::create([
            'club_id' => $club->id, 'user_id' => $user->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'status' => $status, 'joined_at' => now(),
        ]);

        return $user;
    }

    private function announcement(Club $club, array $overrides = [], ?Club $actualClub = null): ClubAnnouncement
    {
        $actualClub ??= $club;
        $creator = User::factory()->create();
        $data = array_merge([
            'club_id' => $actualClub->id, 'title' => 'Synthetic notice', 'body' => 'Synthetic plain body.',
            'priority' => 'normal', 'audience' => 'club-members', 'status' => 'draft',
            'scheduled_publish_at' => null, 'published_at' => null, 'expires_at' => null,
            'withdrawn_at' => null, 'withdrawal_reason' => null, 'archived_at' => null,
            'replaces_announcement_id' => null, 'published_by_user_id' => null,
            'published_by_role' => null, 'created_by_user_id' => $creator->id,
            'updated_by_user_id' => null, 'withdrawn_by_user_id' => null,
            'archived_by_user_id' => null, 'lock_version' => 1,
        ], $overrides);

        return ClubAnnouncement::create($data);
    }

    private function published(array $overrides = []): array
    {
        $publisher = User::factory()->create();

        return array_merge([
            'status' => 'published', 'published_at' => '2026-07-20 10:00:00',
            'published_by_user_id' => $publisher->id, 'published_by_role' => 'president',
        ], $overrides);
    }

    private function withdrawn(array $overrides = []): array
    {
        $withdrawer = User::factory()->create();

        return array_merge($this->published(), [
            'status' => 'withdrawn', 'withdrawn_at' => '2026-07-20 11:00:00',
            'withdrawal_reason' => 'Synthetic reason', 'withdrawn_by_user_id' => $withdrawer->id,
        ], $overrides);
    }

    private function archived(array $overrides = []): array
    {
        $archiver = User::factory()->create();

        return array_merge([
            'status' => 'archived', 'archived_at' => '2026-07-20 11:00:00',
            'archived_by_user_id' => $archiver->id,
        ], $overrides);
    }

    private function rawRow(Club $club, array $overrides = []): array
    {
        return array_merge([
            'club_id' => $club->id, 'title' => 'Raw row', 'body' => 'Raw body',
            'priority' => 'normal', 'audience' => 'club-members', 'status' => 'draft',
            'scheduled_publish_at' => null, 'published_at' => null, 'expires_at' => null,
            'withdrawn_at' => null, 'withdrawal_reason' => null, 'archived_at' => null,
            'replaces_announcement_id' => null, 'published_by_user_id' => null,
            'published_by_role' => null, 'created_by_user_id' => User::factory()->create()->id,
            'updated_by_user_id' => null, 'withdrawn_by_user_id' => null,
            'archived_by_user_id' => null, 'lock_version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides);
    }
}
