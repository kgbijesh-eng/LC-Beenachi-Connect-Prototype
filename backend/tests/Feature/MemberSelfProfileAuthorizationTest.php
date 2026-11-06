<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberProfileDocument;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalLinkedMemberIdentitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSelfProfileAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private Member $ranjith;
    private Member $sajith;

    protected function setUp(): void
    {
        parent::setUp();
        config(['instance.organisation_code' => 'LCB']);
        $this->seed(DatabaseSeeder::class);

        $this->club = Club::where('code', 'LCB')->firstOrFail();
        Member::create([
            'club_id' => $this->club->id,
            'member_code' => '4794370',
            'full_name' => 'Bijesh K G',
            'status' => 'active',
        ]);
        Member::create([
            'club_id' => $this->club->id,
            'member_code' => '26766796',
            'full_name' => 'Rajeesh PR',
            'status' => 'active',
        ]);
        $this->ranjith = Member::create([
            'club_id' => $this->club->id,
            'member_code' => '26766860',
            'full_name' => 'Ranjith MR',
            'status' => 'active',
        ]);
        $this->sajith = Member::create([
            'club_id' => $this->club->id,
            'member_code' => '4794369',
            'full_name' => 'Sajith K',
            'status' => 'active',
        ]);

        $this->seed(LocalLinkedMemberIdentitiesSeeder::class);
        $this->ranjith->refresh();
        $this->sajith->refresh();
    }

    public function test_linked_treasurer_and_member_resolve_their_own_profiles(): void
    {
        $this->assertOwnProfile('ranjith.treasurer@lcbeenachi.test', $this->ranjith);
        $this->assertOwnProfile('sajith.member@lcbeenachi.test', $this->sajith);
    }

    public function test_self_profile_access_is_independent_of_members_view_permission(): void
    {
        $sajithUser = User::where('email', 'sajith.member@lcbeenachi.test')->firstOrFail();
        $this->removeMembersViewPermission($sajithUser);

        $this->actingAs($sajithUser)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/me")
            ->assertOk()
            ->assertJsonPath('member.member_code', '4794369');

        $this->actingAs($sajithUser)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/{$this->ranjith->id}")
            ->assertForbidden();
    }

    public function test_both_linked_identities_can_read_only_their_own_document_lists(): void
    {
        $ranjithDocument = $this->documentFor($this->ranjith, 'id_proof');
        $sajithDocument = $this->documentFor($this->sajith, 'membership_form');

        $ranjithUser = User::where('email', 'ranjith.treasurer@lcbeenachi.test')->firstOrFail();
        $this->actingAs($ranjithUser)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/{$this->ranjith->id}/profile-documents")
            ->assertOk()
            ->assertJsonPath('profile_documents.0.id', $ranjithDocument->id);

        $sajithUser = User::where('email', 'sajith.member@lcbeenachi.test')->firstOrFail();
        $this->actingAs($sajithUser)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/{$this->sajith->id}/profile-documents")
            ->assertOk()
            ->assertJsonPath('profile_documents.0.id', $sajithDocument->id);

        $this->actingAs($sajithUser)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/{$this->ranjith->id}/profile-documents")
            ->assertForbidden();
    }

    public function test_other_member_reads_still_require_members_view(): void
    {
        $sajithUser = User::where('email', 'sajith.member@lcbeenachi.test')->firstOrFail();
        $this->removeMembersViewPermission($sajithUser);
        $this->actingAs($sajithUser)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/{$this->ranjith->id}")
            ->assertForbidden();

        $ranjithUser = User::where('email', 'ranjith.treasurer@lcbeenachi.test')->firstOrFail();
        $this->actingAs($ranjithUser)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/{$this->sajith->id}")
            ->assertOk()
            ->assertJsonPath('member.member_code', '4794369');
    }

    public function test_generic_accounts_remain_truthfully_unlinked(): void
    {
        foreach (['treasurer@lcbeenachi.test', 'local.member@lcbeenachi.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertSame(0, Member::where('user_id', $user->id)->count());

            $this->actingAs($user)
                ->getJson("/api/v1/clubs/{$this->club->id}/members/me")
                ->assertForbidden()
                ->assertJsonPath('message', 'Member record not linked.');
        }
    }

    public function test_unauthenticated_self_profile_request_returns_401(): void
    {
        $this->getJson("/api/v1/clubs/{$this->club->id}/members/me")
            ->assertUnauthorized();
    }

    private function assertOwnProfile(string $email, Member $member): void
    {
        $user = User::where('email', $email)->firstOrFail();

        $this->actingAs($user)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/me")
            ->assertOk()
            ->assertJsonPath('member.id', $member->id)
            ->assertJsonPath('member.member_code', $member->member_code)
            ->assertJsonPath('member.full_name', $member->full_name);
    }

    private function documentFor(Member $member, string $type): MemberProfileDocument
    {
        return MemberProfileDocument::create([
            'club_id' => $this->club->id,
            'member_id' => $member->id,
            'document_type' => $type,
            'status' => 'verified',
        ]);
    }

    private function removeMembersViewPermission(User $user): void
    {
        $role = Role::create([
            'name' => 'Self Profile Only',
            'slug' => 'self-profile-only-'.uniqid(),
            'description' => 'No other-member read permission.',
            'is_system' => false,
        ]);

        ClubMembership::where('club_id', $this->club->id)
            ->where('user_id', $user->id)
            ->update(['role_id' => $role->id]);
    }
}
