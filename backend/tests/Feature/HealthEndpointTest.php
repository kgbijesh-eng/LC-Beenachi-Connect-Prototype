<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_api_health_endpoint_returns_base_json_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'LC Beenachi Connect API is healthy.',
                'data' => [
                    'service' => 'lc-beenachi-connect-backend',
                    'status' => 'ok',
                ],
            ]);
    }
}
