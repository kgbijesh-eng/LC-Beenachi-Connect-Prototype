<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_success_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'LC Beenachi Connect API is healthy.',
                'data' => [
                    'service' => 'lc-beenachi-connect-backend',
                    'status' => 'ok',
                ],
            ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'president@lcbeenachi.test',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'president@lcbeenachi.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid login credentials.',
            ]);
    }

    public function test_login_returns_token_and_user_details(): void
    {
        $user = User::factory()->create([
            'name' => 'President User',
            'email' => 'president@lcbeenachi.test',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'president@lcbeenachi.test',
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Login successful.',
                'user' => [
                    'id' => $user->id,
                    'name' => 'President User',
                    'email' => 'president@lcbeenachi.test',
                ],
            ])
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Secretary User',
            'email' => 'secretary@lcbeenachi.test',
            'password' => 'correct-password',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'secretary@lcbeenachi.test',
            'password' => 'correct-password',
        ]);

        $token = $loginResponse->json('token');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => 'Secretary User',
                    'email' => 'secretary@lcbeenachi.test',
                ],
            ]);
    }

    public function test_logout_revokes_current_token(): void
    {
        User::factory()->create([
            'email' => 'treasurer@lcbeenachi.test',
            'password' => 'correct-password',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'treasurer@lcbeenachi.test',
            'password' => 'correct-password',
        ]);

        $token = $loginResponse->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $logoutResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
