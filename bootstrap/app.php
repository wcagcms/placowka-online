<?php

use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureAgentEnrollmentRequest;
use App\Http\Middleware\EnsureFacilityAccess;
use App\Http\Middleware\EnsurePanelAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
		$middleware->trustHosts(
		at: fn (): array => app()->environment('production')
        ? [
            '^monitoring\.wcag-cms\.pl$',
        ]
        : [
            '^monitoring\.wcag-cms\.pl$',
            '^localhost$',
            '^127\.0\.0\.1$',
        ],
		subdomains: false,
	);
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'panel.auth' => EnsurePanelAuthenticated::class,
            'panel.admin' => EnsureAdministrator::class,
            'facility.access' => EnsureFacilityAccess::class,
            'agent.enrollment' => EnsureAgentEnrollmentRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Central exception handling may be configured here.
    })->create();
