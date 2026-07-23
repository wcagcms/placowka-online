<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()'
        );
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set(
            'Content-Security-Policy',
            (string) config('security.content_security_policy')
        );

        if (
            $request->routeIs('panel.login', 'panel.login.store')
            || $request->is('api/*')
        ) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        $hstsMaxAge = max(0, (int) config('security.hsts_max_age', 31536000));

        if ($request->isSecure() && $hstsMaxAge > 0) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.$hstsMaxAge
            );
        }

        return $response;
    }
}
