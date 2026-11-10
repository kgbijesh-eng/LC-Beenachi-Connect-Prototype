<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalLinkedMemberIdentitiesSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Support\FinancialPostgresTestCase;

class Milestone3hfNormalMemberIdentityTest extends FinancialPostgresTestCase
{
    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
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
        Member::create([
            'club_id' => $this->club->id,
            'member_code' => '26766860',
            'full_name' => 'Ranjith MR',
            'status' => 'active',
        ]);
        Member::create([
            'club_id' => $this->club->id,
            'member_code' => '4794369',
            'full_name' => 'Sajith K',
            'status' => 'active',
        ]);

        $this->seed(LocalLinkedMemberIdentitiesSeeder::class);
    }

    public function test_auth_me_exposes_only_verified_linked_identity_fields(): void
    {
        $expectations = [
            'bijesh.president@lcbeenachi.test' => ['president-admin', '4794370', 'Bijesh K G'],
            'rajeesh.secretary@lcbeenachi.test' => ['secretary', '26766796', 'Rajeesh PR'],
            'ranjith.treasurer@lcbeenachi.test' => ['treasurer', '26766860', 'Ranjith MR'],
            'sajith.member@lcbeenachi.test' => ['member', '4794369', 'Sajith K'],
        ];

        foreach ($expectations as $email => [$role, $memberCode, $fullName]) {
            $response = $this->authenticatedMe($email);
            $response->assertOk()
                ->assertJsonPath('user.email', $email)
                ->assertJsonPath('club_memberships.0.role.slug', $role)
                ->assertJsonPath('club_memberships.0.member.member_code', $memberCode)
                ->assertJsonPath('club_memberships.0.member.full_name', $fullName)
                ->assertJsonMissingPath('club_memberships.0.member.phone')
                ->assertJsonMissingPath('club_memberships.0.member.email')
                ->assertJsonMissingPath('club_memberships.0.member.user_id')
                ->assertJsonMissingPath('club_memberships.0.member.club_id');
        }
    }

    public function test_generic_accounts_keep_null_member_relationships(): void
    {
        foreach (['president@lcbeenachi.test', 'secretary@lcbeenachi.test', 'treasurer@lcbeenachi.test', 'local.member@lcbeenachi.test'] as $email) {
            $this->authenticatedMe($email)
                ->assertOk()
                ->assertJsonPath('club_memberships.0.member', null);
        }
    }

    public function test_switching_tokens_cannot_retain_the_previous_member_identity(): void
    {
        $ranjithToken = $this->loginToken('ranjith.treasurer@lcbeenachi.test');
        $sajithToken = $this->loginToken('sajith.member@lcbeenachi.test');

        Auth::forgetGuards();
        config(['instance.organisation_code' => $this->club->code]);
        $this->withToken($ranjithToken)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/me")
            ->assertOk()
            ->assertJsonPath('member.member_code', '26766860');

        Auth::forgetGuards();
        config(['instance.organisation_code' => 'LCB']);
        $this->withToken($ranjithToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        Auth::forgetGuards();
        $this->withToken($ranjithToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        Auth::forgetGuards();
        config(['instance.organisation_code' => $this->club->code]);
        $this->withToken($sajithToken)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/me")
            ->assertOk()
            ->assertJsonPath('member.member_code', '4794369');
    }

    public function test_linking_is_idempotent_and_preserves_all_fixture_identifiers(): void
    {
        $memberCodes = ['4794370', '26766796', '26766860', '4794369'];
        $emails = [
            'bijesh.president@lcbeenachi.test',
            'rajeesh.secretary@lcbeenachi.test',
            'ranjith.treasurer@lcbeenachi.test',
            'sajith.member@lcbeenachi.test',
        ];
        $originalUserIds = User::whereIn('email', $emails)->pluck('id', 'email')->all();
        $originalMemberIds = Member::whereIn('member_code', $memberCodes)->pluck('id', 'member_code')->all();
        $originalLinks = Member::whereIn('member_code', $memberCodes)->pluck('user_id', 'member_code')->all();
        $originalMembershipIds = ClubMembership::where('club_id', $this->club->id)
            ->whereIn('user_id', array_values($originalUserIds))
            ->pluck('id', 'user_id')
            ->all();
        $originalRoleCount = Role::count();

        $this->seed(LocalLinkedMemberIdentitiesSeeder::class);
        $this->seed(LocalLinkedMemberIdentitiesSeeder::class);

        $this->assertSame($originalUserIds, User::whereIn('email', $emails)->pluck('id', 'email')->all());
        $this->assertSame($originalMemberIds, Member::whereIn('member_code', $memberCodes)->pluck('id', 'member_code')->all());
        $this->assertSame($originalLinks, Member::whereIn('member_code', $memberCodes)->pluck('user_id', 'member_code')->all());
        $this->assertSame($originalMembershipIds, ClubMembership::where('club_id', $this->club->id)
            ->whereIn('user_id', array_values($originalUserIds))
            ->pluck('id', 'user_id')
            ->all());
        $this->assertSame(4, User::whereIn('email', $emails)->count());
        $this->assertSame(4, ClubMembership::where('club_id', $this->club->id)
            ->whereIn('user_id', array_values($originalUserIds))
            ->count());
        $this->assertSame(4, Member::whereIn('member_code', $memberCodes)->whereNotNull('user_id')->count());
        $this->assertSame($originalRoleCount, Role::count());
    }

    public function test_linking_stops_when_a_stable_code_resolves_to_the_wrong_name(): void
    {
        Member::where('member_code', '4794369')->update(['full_name' => 'Unexpected Member']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Linked development identity code [4794369] resolved to [Unexpected Member], expected [Sajith K].');

        $this->seed(LocalLinkedMemberIdentitiesSeeder::class);
    }

    private function authenticatedMe(string $email)
    {
        $token = $this->loginToken($email);
        Auth::forgetGuards();

        config(['instance.organisation_code' => 'LCB']);
        return $this->withToken($token)->getJson('/api/v1/auth/me');
    }

    private function loginToken(string $email): string
    {
        $this->withoutToken();
        Auth::forgetGuards();

        config(['instance.organisation_code' => 'LCB']);
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk()->json('token');
    }
}
