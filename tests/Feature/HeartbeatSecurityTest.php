<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Facility;
use App\Models\Heartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HeartbeatSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_device_token_is_rejected(): void
    {
        [$device] = $this->makeDevice();

        $this->withHeader('Authorization', 'Bearer '.Str::random(64))
            ->postJson('/api/v1/heartbeat/'.$device->uuid, $this->payload())
            ->assertUnauthorized();
    }

    public function test_replayed_heartbeat_uuid_is_not_stored_twice(): void
    {
        [$device, $token] = $this->makeDevice();
        $payload = $this->payload();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/heartbeat/'.$device->uuid, $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/heartbeat/'.$device->uuid, $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, Heartbeat::query()->count());
    }

    /** @return array{Device, string} */
    private function makeDevice(): array
    {
        $facility = Facility::query()->create([
            'code' => 'TEST',
            'name' => 'Placówka testowa',
            'is_active' => true,
        ]);
        $token = Str::random(64);
        $device = Device::query()->create([
            'facility_id' => $facility->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Komputer testowy',
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);

        return [$device, $token];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'heartbeat_uuid' => (string) Str::uuid(),
            'internet_ok' => true,
            'dns_ok' => true,
            'gateway_ok' => true,
            'monitoring_server_ok' => true,
            'latency_ms' => 25,
            'dns_latency_ms' => 5,
            'checked_at' => now()->toIso8601String(),
            'agent_version' => 'test-agent',
            'test_details' => [[
                'url' => 'https://www.google.com/generate_204',
                'ok' => true,
                'latency_ms' => 25,
                'error' => null,
            ]],
        ];
    }
}
