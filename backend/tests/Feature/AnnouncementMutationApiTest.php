<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubAnnouncementPublicationDecision;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementMutationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_authentication_is_required_for_every_announcement_write_route(): void
    {
        foreach ([
            ['post', '/api/v1/clubs/1/announcements'],
            ['patch', '/api/v1/clubs/1/announcements/1'],
            ['post', '/api/v1/clubs/1/announcements/1/replacements'],
            ['post', '/api/v1/clubs/1/announcements/1/approvals'],
            ['post', '/api/v1/clubs/1/announcements/1/approval-rejections'],
            ['post', '/api/v1/clubs/1/announcements/1/approvals/1/revoke'],
            ['post', '/api/v1/clubs/1/announcements/1/publish'],
            ['post', '/api/v1/clubs/1/announcements/1/withdraw'],
            ['post', '/api/v1/clubs/1/announcements/1/archive'],
        ] as [$method, $uri]) {
            $this->json(strtoupper($method), $uri)->assertUnauthorized();
        }
    }

    public function test_create_and_edit_use_safe_resources_validation_permissions_and_optimistic_locking(): void
    {
        $club = $this->club('HTTP');
        $president = $this->actor($club, 'president-admin');
        $treasurer = $this->actor($club, 'treasurer');

        Sanctum::actingAs($president);
        $this->postJson("/api/v1/clubs/{$club->id}/announcements", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'body', 'priority', 'audience']);
        $created = $this->postJson("/api/v1/clubs/{$club->id}/announcements", $this->draft())
            ->assertCreated()->assertJsonPath('data.stored_status', 'draft')
            ->assertJsonMissingPath('data.content_fingerprint')
            ->json('data');
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcements.created', 'auditable_id' => $created['id']]);

        $this->patchJson("/api/v1/clubs/{$club->id}/announcements/{$created['id']}", ['title' => 'Missing version'])
            ->assertUnprocessable()->assertJsonValidationErrors('lock_version');
        $this->patchJson("/api/v1/clubs/{$club->id}/announcements/{$created['id']}", [
            'lock_version' => 1, 'title' => 'Updated title',
        ])->assertOk()->assertJsonPath('data.lock_version', 2);
        $auditCount = AuditLog::count();
        $this->patchJson("/api/v1/clubs/{$club->id}/announcements/{$created['id']}", [
            'lock_version' => 1, 'title' => 'Stale overwrite',
        ])->assertConflict();
        $this->assertSame($auditCount, AuditLog::count());

        Sanctum::actingAs($treasurer);
        $this->postJson("/api/v1/clubs/{$club->id}/announcements", $this->draft())->assertForbidden();
        $this->patchJson("/api/v1/clubs/{$club->id}/announcements/{$created['id']}", [
            'lock_version' => 2, 'title' => 'Unauthorized',
        ])->assertForbidden();
    }

    public function test_treasurer_member_and_inactive_membership_are_denied_for_every_write_permission(): void
    {
        $club = $this->club('DENY');
        $owner = $this->actor($club, 'president-admin');
        $draft = ClubAnnouncement::query()->create([
            'club_id' => $club->id,
            'title' => 'Permission target',
            'body' => 'Permission target body',
            'priority' => 'urgent',
            'audience' => 'club-members',
            'status' => 'draft',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
            'lock_version' => 1,
        ]);
        $approval = ClubAnnouncementPublicationDecision::query()->create([
            'club_id' => $club->id,
            'club_announcement_id' => $draft->id,
            'announcement_lock_version' => 1,
            'content_fingerprint' => str_repeat('a', 64),
            'maker_user_id' => $owner->id,
            'maker_role' => 'president',
            'decision' => 'approved',
            'decided_by_user_id' => $owner->id,
            'decided_by_role' => 'president',
            'reason' => null,
            'decided_at' => now(),
        ]);
        $targets = [
            ['post', "/api/v1/clubs/{$club->id}/announcements"],
            ['patch', "/api/v1/clubs/{$club->id}/announcements/{$draft->id}"],
            ['post', "/api/v1/clubs/{$club->id}/announcements/{$draft->id}/replacements"],
            ['post', "/api/v1/clubs/{$club->id}/announcements/{$draft->id}/approvals"],
            ['post', "/api/v1/clubs/{$club->id}/announcements/{$draft->id}/approval-rejections"],
            ['post', "/api/v1/clubs/{$club->id}/announcements/{$draft->id}/approvals/{$approval->id}/revoke"],
            ['post', "/api/v1/clubs/{$club->id}/announcements/{$draft->id}/publish"],
            ['post', "/api/v1/clubs/{$club->id}/announcements/{$draft->id}/withdraw"],
            ['post', "/api/v1/clubs/{$club->id}/announcements/{$draft->id}/archive"],
        ];
        foreach ([
            $this->actor($club, 'treasurer'),
            $this->actor($club, 'member'),
            $this->actor($club, 'president-admin', 'inactive'),
        ] as $actor) {
            Sanctum::actingAs($actor);
            foreach ($targets as [$method, $uri]) {
                $this->json(strtoupper($method), $uri)->assertForbidden();
            }
        }
    }

    public function test_http_adapter_preserves_checker_publication_withdrawal_archive_and_decision_contracts(): void
    {
        $club = $this->club('FLOW');
        $maker = $this->actor($club, 'president-admin');
        $checker = $this->actor($club, 'secretary');

        Sanctum::actingAs($maker);
        $draft = $this->postJson("/api/v1/clubs/{$club->id}/announcements", $this->draft(['priority' => 'urgent']))
            ->assertCreated()->json('data');
        $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$draft['id']}/publish", ['lock_version' => 1])
            ->assertConflict();

        Sanctum::actingAs($checker);
        $approval = $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$draft['id']}/approvals", ['lock_version' => 1])
            ->assertCreated()->assertJsonStructure(['data' => ['id', 'announcement_id', 'announcement_lock_version', 'decision', 'reason', 'decided_at']])
            ->assertJsonMissingPath('data.content_fingerprint')
            ->assertJsonMissingPath('data.decided_by_user_id')
            ->json('data');

        Sanctum::actingAs($maker);
        $published = $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$draft['id']}/publish", ['lock_version' => 1])
            ->assertOk()->assertJsonPath('data.stored_status', 'published')->json('data');

        Sanctum::actingAs($checker);
        $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$draft['id']}/withdraw", ['lock_version' => $published['lock_version']])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $withdrawn = $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$draft['id']}/withdraw", [
            'lock_version' => $published['lock_version'], 'reason' => 'Operational correction required',
        ])->assertOk()->assertJsonPath('data.stored_status', 'withdrawn')->json('data');

        Sanctum::actingAs($maker);
        $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$draft['id']}/archive", [
            'lock_version' => $withdrawn['lock_version'],
        ])->assertOk()->assertJsonPath('data.stored_status', 'archived');

        $this->assertDatabaseHas('audit_logs', ['action' => 'announcements.approval_granted', 'auditable_id' => $draft['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcements.published', 'auditable_id' => $draft['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcements.withdrawn', 'auditable_id' => $draft['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcements.archived', 'auditable_id' => $draft['id']]);
        $this->assertSame('approved', $approval['decision']);
    }

    public function test_rejection_revocation_replacement_and_cross_club_targets_follow_service_boundaries(): void
    {
        $club = $this->club('BOUNDARY');
        $other = $this->club('OTHER');
        $maker = $this->actor($club, 'president-admin');
        $checker = $this->actor($club, 'secretary');

        Sanctum::actingAs($maker);
        $rejectedDraft = $this->postJson("/api/v1/clubs/{$club->id}/announcements", $this->draft(['audience' => 'officers']))
            ->assertCreated()->json('data');
        Sanctum::actingAs($checker);
        $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$rejectedDraft['id']}/approval-rejections", [
            'lock_version' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$rejectedDraft['id']}/approval-rejections", [
            'lock_version' => 1, 'reason' => 'Needs revision',
        ])->assertCreated()->assertJsonPath('data.decision', 'rejected');

        Sanctum::actingAs($maker);
        $revocable = $this->postJson("/api/v1/clubs/{$club->id}/announcements", $this->draft(['priority' => 'urgent']))
            ->assertCreated()->json('data');
        Sanctum::actingAs($checker);
        $approval = $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$revocable['id']}/approvals", ['lock_version' => 1])
            ->assertCreated()->json('data');
        $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$revocable['id']}/approvals/{$approval['id']}/revoke", [
            'lock_version' => 1, 'reason' => 'Approval reconsidered',
        ])->assertCreated()->assertJsonPath('data.decision', 'revoked');

        Sanctum::actingAs($maker);
        $routine = $this->postJson("/api/v1/clubs/{$club->id}/announcements", $this->draft())->assertCreated()->json('data');
        $published = $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$routine['id']}/publish", ['lock_version' => 1])
            ->assertOk()->json('data');
        $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$published['id']}/replacements", [
            'title' => 'Replacement', 'body' => 'Replacement body', 'priority' => 'normal', 'expires_at' => null,
        ])->assertCreated()->assertJsonPath('data.replaces_announcement_id', $published['id']);
        $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$published['id']}/replacements", $this->draft())
            ->assertUnprocessable()->assertJsonValidationErrors('audience');
        ClubMembership::query()->create([
            'club_id' => $other->id,
            'user_id' => $maker->id,
            'role_id' => Role::query()->where('slug', 'president-admin')->firstOrFail()->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $this->postJson("/api/v1/clubs/{$other->id}/announcements/{$published['id']}/archive", ['lock_version' => 2])
            ->assertNotFound();
    }

    public function test_unsupported_generic_status_delete_and_restore_routes_remain_absent(): void
    {
        $club = $this->club('ABSENT');
        $president = $this->actor($club, 'president-admin');
        Sanctum::actingAs($president);
        $announcement = ClubAnnouncement::query()->create([
            'club_id' => $club->id, 'title' => 'Synthetic', 'body' => 'Synthetic body',
            'priority' => 'normal', 'audience' => 'club-members', 'status' => 'draft',
            'created_by_user_id' => $president->id, 'updated_by_user_id' => $president->id, 'lock_version' => 1,
        ]);

        $this->deleteJson("/api/v1/clubs/{$club->id}/announcements/{$announcement->id}")->assertMethodNotAllowed();
        $this->postJson("/api/v1/clubs/{$club->id}/announcements/{$announcement->id}/restore")->assertNotFound();
        $this->patchJson("/api/v1/clubs/{$club->id}/announcements/{$announcement->id}/status", ['status' => 'published'])->assertNotFound();
    }

    private function club(string $code): Club
    {
        return Club::query()->create(['name' => 'Synthetic '.$code, 'code' => $code.'-'.uniqid(), 'timezone' => 'UTC', 'is_active' => true]);
    }

    private function actor(Club $club, string $roleSlug, string $status = 'active'): User
    {
        $user = User::factory()->create();
        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
            'status' => $status,
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function draft(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Synthetic announcement',
            'body' => 'Synthetic body',
            'priority' => 'normal',
            'audience' => 'club-members',
            'expires_at' => null,
        ], $overrides);
    }
}
