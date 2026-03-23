<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class XssProtectionTest extends TestCase
{
    /**
     * Test that HTML tags are stripped from input.
     */
    public function test_input_is_sanitized_of_html_tags(): void
    {
        $response = $this->postJson('/api/test-sanitization', [
            'name' => '<b>John</b>',
            'script' => '<script>alert("xss")</script>Safe',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'name' => 'John',
            'script' => 'alert("xss")Safe',
        ]);
        
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    /**
     * Test that all security headers are present.
     */
    public function test_all_security_headers_are_present(): void
    {
        $response = $this->get('/api/health-check');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'no-referrer-when-downgrade');
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none';");
    }

    /**
     * Test that nested input is also sanitized.
     */
    public function test_nested_input_is_sanitized(): void
    {
        $response = $this->postJson('/api/test-sanitization', [
            'user' => [
                'bio' => '<i>Developer</i>',
                'tags' => ['<b>tag1</b>', 'tag2']
            ]
        ]);

        $response->assertJson([
            'user' => [
                'bio' => 'Developer',
                'tags' => ['tag1', 'tag2']
            ]
        ]);
    }
}
