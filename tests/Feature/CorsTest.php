<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CorsTest extends TestCase
{
    /**
     * Test that CORS preflight request returns correct headers.
     */
    public function test_cors_preflight_request(): void
    {
        $response = $this->call('OPTIONS', '/api/health-check', [], [], [], [
            'HTTP_ORIGIN' => 'https://www.igen.lk',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type, Authorization',
        ]);

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://www.igen.lk');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }

    /**
     * Test that CORS headers are present in a regular request.
     */
    public function test_cors_headers_in_regular_request(): void
    {
        $response = $this->get('/api/health-check', [
            'Origin' => 'https://www.igen.lk',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', 'https://www.igen.lk');
    }

    /**
     * Test that unauthorized origin is not allowed (it should not have the Access-Control-Allow-Origin header).
     */
    public function test_unauthorized_origin_cors_blocked(): void
    {
        $response = $this->get('/api/health-check', [
            'Origin' => 'https://unauthorized-domain.com',
        ]);

        $this->assertNotEquals('https://unauthorized-domain.com', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
