<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_login_response_contains_strict_browser_headers(): void
    {
        $response = $this->get('/panel/login');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private');

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_api_error_response_is_not_cacheable(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.Str::random(64))
            ->postJson('/api/v1/heartbeat/'.Str::uuid(), [
                'internet_ok' => true,
                'dns_ok' => true,
            ]);

        $response->assertUnauthorized()
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
