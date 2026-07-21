<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! Auth::check() || ! $user || ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('panel.login');
        }

        $sessionAuthVersion = (int) $request->session()->get('panel_auth_version', 0);

        if ($sessionAuthVersion !== (int) $user->auth_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('panel.login')
                ->withErrors(['email' => 'Sesja została unieważniona. Zaloguj się ponownie.']);
        }

        if (
            $user->must_change_password
            && ! $request->routeIs('account.*', 'panel.logout')
        ) {
            return redirect()
                ->route('account.edit')
                ->with('warning', 'Przed dalszą pracą ustaw własne, bezpieczne hasło.');
        }

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
