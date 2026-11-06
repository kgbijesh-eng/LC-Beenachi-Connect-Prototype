<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubAnnouncementRead;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3iizbAnnouncementReadStateFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-07-20 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_exact_schema_unique_scope_foreign_keys_casts_and_relationships(): void
    {
        $this->assertSame([
            'id', 'club_id', 'user_id', 'club_announcement_id', 'read_at', 'created_at',
        ], Schema::getColumnListing('club_announcement_reads'));

        $indexes = collect(Schema::getIndexes('club_announcement_reads'));
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['unique']
            && $index['columns'] === ['club_id', 'user_id', 'club_announcement_id']));

        $foreignColumns = collect(Schema::getForeignKeys('club_announcement_reads'))->pluck('columns')->flatten()->all();
        $this->assertEqualsCanonicalizing(['club_id', 'user_id', 'club_announcement_id'], $foreignColumns);

        $read = new ClubAnnouncementRead;
        $this->assertNull(ClubAnnouncementRead::UPDATED_AT);
        $this->assertSame('datetime', $read->getCasts()['read_at']);
        $this->assertSame('datetime', $read->getCasts()['created_at']);
        $this->assertInstanceOf(BelongsTo::class, $read->club());
        $this->assertInstanceOf(BelongsTo::class, $read->user());
        $this->assertInstanceOf(BelongsTo::class, $read->announcement());
        $this->assertInstanceOf(HasMany::class, (new ClubAnnouncement)->reads());
    }

    public function test_get_list_and_detail_are_side_effect_free_and_attach_default_personal_state(): void
    {
        $club = $this->club('GET');
        $member = $this->actor($club, 'member');
        $announcement = $this->announcement($club, $this->published());
        Sanctum::actingAs($member);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements")
            ->assertOk()
            ->assertJsonPath('data.0.id', $announcement->id)
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('data.0.read_at', null);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('data.is_read', false)
            ->assertJsonPath('data.read_at', null);

        $this->assertDatabaseCount('club_announcement_reads', 0);
    }

    public function test_put_is_replay_idempotent_preserves_first_timestamp_and_updates_get_projections(): void
    {
        $club = $this->club('PUT');
        $member = $this->actor($club, 'member');
        $announcement = $this->announcement($club, $this->published());
        Sanctum::actingAs($member);

        config(['instance.organisation_code' => $club->code]);
        $first = $this->putJson("/api/v1/clubs/{$club->id}/announcements/{$announcement->id}/read")
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'announcement_id' => $announcement->id,
                    'is_read' => true,
                    'read_at' => '2026-07-20T12:00:00.000000Z',
                ],
            ])
            ->json('data.read_at');

        Carbon::setTestNow('2026-07-20 13:00:00 UTC');
        $this->putJson("/api/v1/clubs/{$club->id}/announcements/{$announcement->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', $first);

        $this->assertDatabaseCount('club_announcement_reads', 1);
        $persisted = ClubAnnouncementRead::query()->sole();
        $this->assertSame('2026-07-20T12:00:00.000000Z', $persisted->read_at->toISOString());
        $this->assertSame($persisted->read_at->toISOString(), $persisted->created_at->toISOString());

        $this->getJson("/api/v1/clubs/{$club->id}/announcements")
            ->assertOk()
            ->assertJsonPath('data.0.is_read', true)
            ->assertJsonPath('data.0.read_at', $first);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.read_at', $first);
    }

    public function test_read_state_is_isolated_by_user_club_and_announcement_and_other_user_state_never_returns(): void
    {
        $club = $this->club('ISOLATE');
        $otherClub = $this->club('ISOLATE-OTHER');
        $firstUser = $this->actor($club, 'member');
        $secondUser = $this->actor($club, 'member');
        $first = $this->announcement($club, $this->published(['title' => 'First']));
        $second = $this->announcement($club, $this->published(['title' => 'Second']));
        $other = $this->announcement($otherClub, $this->published(['title' => 'Other club']));

        Sanctum::actingAs($firstUser);
        config(['instance.organisation_code' => $club->code]);
        $this->putJson("/api/v1/clubs/{$club->id}/announcements/{$first->id}/read")->assertOk();

        Sanctum::actingAs($secondUser);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.is_read', false)
            ->assertJsonPath('data.read_at', null);

        Sanctum::actingAs($firstUser);
        $payload = $this->getJson("/api/v1/clubs/{$club->id}/announcements?per_page=50")->assertOk()->json('data');
        $states = collect($payload)->mapWithKeys(fn (array $item): array => [$item['id'] => $item['is_read']]);
        $this->assertTrue($states[$first->id]);
        $this->assertFalse($states[$second->id]);
        $this->assertFalse($states->has($other->id));
    }

    public function test_role_audiences_are_enforced_and_management_read_does_not_broaden_markability(): void
    {
        foreach (['president-admin', 'secretary', 'treasurer'] as $role) {
            $club = $this->club('ROLE-'.$role);
            $user = $this->actor($club, $role);
            $members = $this->announcement($club, $this->published(['audience' => 'club-members']));
            $officers = $this->announcement($club, $this->published(['audience' => 'officers']));
            Sanctum::actingAs($user);
            config(['instance.organisation_code' => $club->code]);
            $this->putJson("/api/v1/clubs/{$club->id}/announcements/{$members->id}/read")->assertOk();
            $this->putJson("/api/v1/clubs/{$club->id}/announcements/{$officers->id}/read")->assertOk();
        }

        $memberClub = $this->club('ROLE-member');
        $member = $this->actor($memberClub, 'member');
        $members = $this->announcement($memberClub, $this->published(['audience' => 'club-members']));
        $officers = $this->announcement($memberClub, $this->published(['audience' => 'officers']));
        Sanctum::actingAs($member);
        config(['instance.organisation_code' => $memberClub->code]);
        $this->putJson("/api/v1/clubs/{$memberClub->id}/announcements/{$members->id}/read")->assertOk();
        $this->putJson("/api/v1/clubs/{$memberClub->id}/announcements/{$officers->id}/read")->assertNotFound();

        $managementClub = $this->club('ROLE-management-personal');
        $president = $this->actor($managementClub, 'president-admin');
        $secretary = $this->actor($managementClub, 'secretary');
        $published = $this->announcement($managementClub, $this->published());
        Sanctum::actingAs($president);
        config(['instance.organisation_code' => $managementClub->code]);
        $this->putJson("/api/v1/clubs/{$managementClub->id}/announcements/{$published->id}/read")->assertOk();
        Sanctum::actingAs($secretary);
        $this->getJson("/api/v1/clubs/{$managementClub->id}/announcements/{$published->id}")
            ->assertOk()
            ->assertJsonPath('data.is_read', false)
            ->assertJsonPath('data.read_at', null);
        $this->putJson("/api/v1/clubs/{$managementClub->id}/announcements/{$published->id}/read")->assertOk();

        $this->assertDatabaseCount('club_announcement_reads', 9);
    }

    public function test_unpublished_expired_wrong_club_invalid_and_inactive_records_fail_closed(): void
    {
        $club = $this->club('DENY');
        $otherClub = $this->club('DENY-OTHER');
        $president = $this->actor($club, 'president-admin');
        $this->membership($president, $otherClub, 'president-admin');
        $draft = $this->announcement($club);
        $withdrawn = $this->announcement($club, $this->withdrawn());
        $archived = $this->announcement($club, $this->archived());
        $expired = $this->announcement($club, $this->published(['expires_at' => '2026-07-20 12:00:00']));
        $foreign = $this->announcement($club, $this->published());
        $invalid = $this->announcement($club, $this->published());
        DB::table('club_announcements')->where('id', $invalid->id)->update(['audience' => 'invalid']);

        config(['instance.organisation_code' => $club->code]);
        $this->putJson("/api/v1/clubs/{$club->id}/announcements/{$foreign->id}/read")->assertUnauthorized();
        Sanctum::actingAs($president);
        foreach ([$draft, $withdrawn, $archived, $expired, $invalid] as $concealed) {
            config(['instance.organisation_code' => $club->code]);
            $this->putJson("/api/v1/clubs/{$club->id}/announcements/{$concealed->id}/read")->assertNotFound();
        }
        config(['instance.organisation_code' => $otherClub->code]);
        $this->putJson("/api/v1/clubs/{$otherClub->id}/announcements/{$foreign->id}/read")->assertNotFound();

        $inactiveClub = $this->club('DENY-INACTIVE');
        $inactive = $this->actor($inactiveClub, 'member', 'inactive');
        $published = $this->announcement($inactiveClub, $this->published());
        Sanctum::actingAs($inactive);
        config(['instance.organisation_code' => $inactiveClub->code]);
        $this->putJson("/api/v1/clubs/{$inactiveClub->id}/announcements/{$published->id}/read")->assertForbidden();

        $this->assertDatabaseCount('club_announcement_reads', 0);
    }

    public function test_replacement_and_direct_detail_visibility_remain_consistent(): void
    {
        $club = $this->club('REPLACE');
        $treasurer = $this->actor($club, 'treasurer');
        $predecessor = $this->announcement($club, $this->published(['audience' => 'officers']));
        $replacement = $this->announcement($club, $this->published([
            'audience' => 'officers',
            'replaces_announcement_id' => $predecessor->id,
        ]));
        Sanctum::actingAs($treasurer);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/announcements/{$predecessor->id}")->assertOk();
        $this->putJson("/api/v1/clubs/{$club->id}/announcements/{$predecessor->id}/read")->assertOk();
        $this->putJson("/api/v1/clubs/{$club->id}/announcements/{$replacement->id}/read")->assertOk();
        $this->assertDatabaseCount('club_announcement_reads', 2);
    }

    private function club(string $code): Club
    {
        return Club::query()->create([
            'name' => 'Synthetic '.$code,
            'code' => $code.'-'.uniqid(),
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    }

    private function actor(Club $club, string $roleSlug, string $status = 'active'): User
    {
        $user = User::factory()->create();
        $this->membership($user, $club, $roleSlug, $status);

        return $user;
    }

    private function membership(User $user, Club $club, string $roleSlug, string $status = 'active'): void
    {
        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
            'status' => $status,
            'joined_at' => now(),
        ]);
    }

    private function announcement(Club $club, array $overrides = []): ClubAnnouncement
    {
        $creator = User::factory()->create();

        return ClubAnnouncement::query()->create(array_merge([
            'club_id' => $club->id,
            'title' => 'Synthetic notice',
            'body' => 'Synthetic plain body.',
            'priority' => 'normal',
            'audience' => 'club-members',
            'status' => 'draft',
            'scheduled_publish_at' => null,
            'published_at' => null,
            'expires_at' => null,
            'withdrawn_at' => null,
            'withdrawal_reason' => null,
            'archived_at' => null,
            'replaces_announcement_id' => null,
            'published_by_user_id' => null,
            'published_by_role' => null,
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => null,
            'withdrawn_by_user_id' => null,
            'archived_by_user_id' => null,
            'lock_version' => 1,
        ], $overrides));
    }

    private function published(array $overrides = []): array
    {
        return array_merge([
            'status' => 'published',
            'published_at' => '2026-07-20 10:00:00',
            'published_by_user_id' => User::factory()->create()->id,
            'published_by_role' => 'president',
        ], $overrides);
    }

    private function withdrawn(array $overrides = []): array
    {
        return array_merge($this->published(), [
            'status' => 'withdrawn',
            'withdrawn_at' => '2026-07-20 11:00:00',
            'withdrawal_reason' => 'Synthetic reason',
            'withdrawn_by_user_id' => User::factory()->create()->id,
        ], $overrides);
    }

    private function archived(array $overrides = []): array
    {
        return array_merge([
            'status' => 'archived',
            'archived_at' => '2026-07-20 11:00:00',
            'archived_by_user_id' => User::factory()->create()->id,
        ], $overrides);
    }
}
