<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanelAuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.test',
            'password' => Hash::make('BezpieczneHaslo!123'),
            'role' => User::ROLE_OPERATOR,
            'is_active' => false,
            'must_change_password' => false,
            'auth_version' => 1,
        ]);

        $this->post('/panel/login', [
            'email' => $user->email,
            'password' => 'BezpieczneHaslo!123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_successful_login_records_auth_version_in_session(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@example.test',
            'password' => Hash::make('BezpieczneHaslo!123'),
            'role' => User::ROLE_OPERATOR,
            'is_active' => true,
            'must_change_password' => false,
            'auth_version' => 7,
        ]);

        $this->post('/panel/login', [
            'email' => $user->email,
            'password' => 'BezpieczneHaslo!123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(7, session('panel_auth_version'));
    }
}
