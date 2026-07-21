<?php

namespace App\Http\Controllers;

use App\Services\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request, SecurityAuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
                'max:72',
            ],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'auth_version' => (int) $user->auth_version + 1,
        ])->save();

        $request->session()->regenerate();
        $request->session()->put('panel_auth_version', (int) $user->auth_version);
        $audit->write('password_changed', $user, $user, [], $request);

        return redirect()
            ->route('account.edit')
            ->with('success', 'Hasło zostało zmienione.');
    }
}
