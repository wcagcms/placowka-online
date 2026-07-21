<?php

namespace App\Http\Controllers;

use App\Services\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PanelAuthController extends Controller
{
    private const ACCOUNT_MAX_ATTEMPTS = 5;
    private const IP_MAX_ATTEMPTS = 25;
    private const DECAY_SECONDS = 900;

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check() && $request->user()?->is_active) {
            return redirect()->route('dashboard');
        }

        return view('panel-login');
    }

    public function login(Request $request, SecurityAuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $accountKey = 'panel-login-account:'.hash('sha256', $email);
        $ipKey = 'panel-login-ip:'.hash('sha256', (string) $request->ip());

        if (
            RateLimiter::tooManyAttempts($accountKey, self::ACCOUNT_MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS)
        ) {
            $seconds = max(
                RateLimiter::availableIn($accountKey),
                RateLimiter::availableIn($ipKey)
            );

            $audit->write('login_blocked', null, null, ['email' => $email], $request);

            return back()
                ->withErrors(['email' => 'Logowanie zostało czasowo zablokowane. Spróbuj ponownie za '.$seconds.' sekund.'])
                ->onlyInput('email');
        }

        $authenticated = Auth::attempt([
            'email' => $email,
            'password' => $validated['password'],
            'is_active' => true,
        ], false);

        if (! $authenticated) {
            RateLimiter::hit($accountKey, self::DECAY_SECONDS);
            RateLimiter::hit($ipKey, self::DECAY_SECONDS);
            $audit->write('login_failed', null, null, ['email' => $email], $request);

            return back()
                ->withErrors(['email' => 'Nieprawidłowy adres e-mail lub hasło.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($accountKey);
        $request->session()->regenerate();

        $user = $request->user();
        $request->session()->put('panel_auth_version', (int) $user->auth_version);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $audit->write('login_success', $user, $user, ['role' => $user->role], $request);

        return redirect()->intended(
            $user->must_change_password
                ? route('account.edit')
                : route('dashboard')
        );
    }

    public function logout(Request $request, SecurityAuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $audit->write('logout', $user, $user, [], $request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('panel.login');
    }
}
