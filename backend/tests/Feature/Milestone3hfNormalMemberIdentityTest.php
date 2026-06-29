<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalLinkedMemberIdentitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class Milestone3hfNormalMemberIdentityTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->club = Club::where('code', 'LCB')->firstOrFail();

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
        $this->withToken($ranjithToken)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/me")
            ->assertOk()
            ->assertJsonPath('member.member_code', '26766860');

        Auth::forgetGuards();
        $this->withToken($ranjithToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        Auth::forgetGuards();
        $this->withToken($ranjithToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        Auth::forgetGuards();
        $this->withToken($sajithToken)
            ->getJson("/api/v1/clubs/{$this->club->id}/members/me")
            ->assertOk()
            ->assertJsonPath('member.member_code', '4794369');
    }

    public function test_linking_is_idempotent_and_uses_codes_instead_of_numeric_database_ids(): void
    {
        $originalLinks = Member::whereIn('member_code', ['26766860', '4794369'])
            ->pluck('user_id', 'member_code')
            ->all();

        $this->seed(LocalLinkedMemberIdentitiesSeeder::class);

        $this->assertSame($originalLinks, Member::whereIn('member_code', ['26766860', '4794369'])
            ->pluck('user_id', 'member_code')
            ->all());
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

        return $this->withToken($token)->getJson('/api/v1/auth/me');
    }

    private function loginToken(string $email): string
    {
        $this->withoutToken();
        Auth::forgetGuards();

        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk()->json('token');
    }
}
