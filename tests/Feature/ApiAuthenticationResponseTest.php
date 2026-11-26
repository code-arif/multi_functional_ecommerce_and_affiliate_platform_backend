<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_without_accept_header_returns_401_json(): void
    {
        $response = $this->get('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated. Please log in.',
            ]);
    }

    public function test_unauthenticated_admin_api_request_returns_401_json(): void
    {
        $response = $this->getJson('/api/v1/admin/customers');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated. Please log in.',
            ]);
    }

    public function test_missing_api_route_returns_404_json(): void
    {
        $response = $this->get('/api/v1/non-existent-endpoint');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Route not found.',
            ]);
    }
}
