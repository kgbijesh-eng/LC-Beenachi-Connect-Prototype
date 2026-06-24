<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthMeRoleAwareResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_endpoint_returns_user_club_role_and_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'president@lcbeenachi.test',
            'password' => 'password',
        ]);

        $token = $loginResponse->json('token');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', 'president@lcbeenachi.test')
            ->assertJsonPath('user.name', 'LC Beenachi President')
            ->assertJsonPath('club_memberships.0.status', 'active')
            ->assertJsonPath('club_memberships.0.club.name', 'Lions Club Beenachi')
            ->assertJsonPath('club_memberships.0.club.code', 'LCB')
            ->assertJsonPath('club_memberships.0.club.district', '318E')
            ->assertJsonPath('club_memberships.0.role.name', 'President/Admin')
            ->assertJsonPath('club_memberships.0.role.slug', 'president-admin')
            ->assertJsonFragment([
                'slug' => 'club.view',
            ])
            ->assertJsonFragment([
                'slug' => 'club.manage',
            ])
            ->assertJsonFragment([
                'slug' => 'users.manage',
            ])
            ->assertJsonFragment([
                'slug' => 'members.view',
            ])
            ->assertJsonFragment([
                'slug' => 'members.manage',
            ])
            ->assertJsonFragment([
                'slug' => 'audit_logs.view',
            ])
            ->assertJsonFragment([
                'slug' => 'payments.record',
            ]);

        $actualPermissions = collect($response->json('club_memberships.0.permissions'))
            ->pluck('slug')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'audit_logs.view',
            'club.manage',
            'club.view',
            'events.manage',
            'events.view',
            'members.manage',
            'members.view',
            'memberships.manage',
            'memberships.view',
            'payments.record',
            'permissions.view',
            'projects.manage',
            'projects.view',
            'reminders.manage',
            'reminders.view',
            'roles.manage',
            'roles.view',
            'users.manage',
            'users.view',
        ], $actualPermissions);
    }
}
