<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityBaselineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_security-baseline-test', static function (Request $request) {
            return response($request->getHost());
        });
    }

    public function test_session_encryption_is_enabled_by_default(): void
    {
        $this->assertTrue(
            config('session.encrypt'),
            'Szyfrowanie sesji powinno być domyślnie włączone.'
        );
    }

    public function test_responses_contain_required_security_headers(): void
    {
        $response = $this->get('/_security-baseline-test');

        $response->assertOk();

        $response->assertHeaderContains(
            'Content-Security-Policy',
            "default-src 'self'"
        );

        $response->assertHeaderContains(
            'Content-Security-Policy',
            "script-src 'self'"
        );

        $response->assertHeaderContains(
            'Content-Security-Policy',
            "style-src 'self' 'unsafe-inline'"
        );

        $response->assertHeaderContains(
            'Content-Security-Policy',
            "img-src 'self' data:"
        );

        $response->assertHeaderContains(
            'Content-Security-Policy',
            "connect-src 'self'"
        );

        $response->assertHeaderContains(
            'Content-Security-Policy',
            "object-src 'none'"
        );

        $response->assertHeaderContains(
            'Content-Security-Policy',
            "frame-ancestors 'none'"
        );

        $response->assertHeaderContains(
            'Content-Security-Policy',
            "base-uri 'self'"
        );

        $response->assertHeaderContains(
            'Content-Security-Policy',
            "form-action 'self'"
        );
    }

    public function test_untrusted_host_is_rejected_in_production_environment(): void
    {
        $this->app->detectEnvironment(
            static fn (): string => 'production'
        );

        $response = $this->get(
            'https://attacker.example/_security-baseline-test'
        );

        $response->assertBadRequest();
    }

    public function test_production_host_is_accepted_in_production_environment(): void
    {
        $this->app->detectEnvironment(
            static fn (): string => 'production'
        );

        $response = $this->get(
            'https://monitoring.wcag-cms.pl/_security-baseline-test'
        );

        $response
            ->assertOk()
            ->assertSeeText('monitoring.wcag-cms.pl');
    }
}