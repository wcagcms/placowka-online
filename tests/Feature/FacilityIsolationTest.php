<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FacilityIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_receives_404_for_device_from_unassigned_facility(): void
    {
        $operator = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'is_active' => true,
            'must_change_password' => false,
            'auth_version' => 1,
        ]);

        $allowed = Facility::query()->create([
            'code' => 'PP01',
            'name' => 'Placówka dozwolona',
            'is_active' => true,
        ]);
        $blocked = Facility::query()->create([
            'code' => 'PP02',
            'name' => 'Placówka niedozwolona',
            'is_active' => true,
        ]);
        $operator->facilities()->attach($allowed);

        $device = Device::query()->create([
            'facility_id' => $blocked->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Komputer sekretariat',
            'token_hash' => hash('sha256', Str::random(64)),
            'is_active' => true,
        ]);

        $this->actingAs($operator)
            ->withSession(['panel_auth_version' => 1])
            ->get(route('devices.heartbeats', ['device' => $device->id]))
            ->assertNotFound();
    }
}
