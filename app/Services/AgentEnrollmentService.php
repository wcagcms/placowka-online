<?php

namespace App\Services;

use App\Exceptions\AgentEnrollmentException;
use App\Models\AgentEnrollmentCode;
use App\Models\AgentEnrollmentSession;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AgentEnrollmentService
{
    private const CODE_ALPHABET = '2346789ABCDEFGHJKMNPQRTUVWXYZ';

    public function __construct(
        private readonly AgentWindowsServiceConfigService $windowsServices
    ) {
    }

    /**
     * @return array{code: AgentEnrollmentCode, plain_code: string}
     */
    public function createCode(Device $device, User $creator, ?string $ipAddress): array
    {
        if (! $device->is_active || $device->archived_at !== null) {
            throw new AgentEnrollmentException('Nie można utworzyć kodu dla nieaktywnego lub zarchiwizowanego urządzenia.');
        }

        $ttlMinutes = max(5, min(60, (int) config('placowka.agent_enrollment_code_ttl_minutes', 15)));
        $plainCode = $this->generatePlainCode($device);
        $normalized = $this->normalizeCode($plainCode);

        $code = DB::transaction(function () use ($device, $creator, $ipAddress, $ttlMinutes, $normalized, $plainCode): AgentEnrollmentCode {
            AgentEnrollmentCode::query()
                ->where('device_id', $device->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            return AgentEnrollmentCode::query()->create([
                'device_id' => $device->getKey(),
                'created_by' => $creator->getKey(),
                'code_hash' => $this->hashCode($normalized),
                'code_label' => $this->maskCode($plainCode),
                'attempts' => 0,
                'max_attempts' => max(3, min(10, (int) config('placowka.agent_enrollment_max_attempts', 5))),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'created_ip' => $ipAddress,
            ]);
        });

        return ['code' => $code, 'plain_code' => $plainCode];
    }

    public function revokeCode(AgentEnrollmentCode $code): void
    {
        if ($code->used_at !== null) {
            throw new AgentEnrollmentException('Wykorzystanego kodu nie można unieważnić.', 409);
        }

        $code->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * @param array{code:string,machine_name:string,client_nonce:string,architecture?:string|null,windows_version?:string|null,setup_version?:string|null} $data
     * @return array<string, mixed>
     */
    public function start(array $data, ?string $ipAddress): array
    {
        if (! config('placowka.agent_enrollment_enabled', true)) {
            throw new AgentEnrollmentException('Rejestracja agentów jest obecnie wyłączona.', 503);
        }

        $normalizedCode = $this->normalizeCode($data['code']);
        $codeHash = $this->hashCode($normalizedCode);
        $sessionToken = Str::random(80);
        $publicId = (string) Str::uuid();
        $sessionTtl = max(5, min(30, (int) config('placowka.agent_enrollment_session_ttl_minutes', 10)));

        return DB::transaction(function () use ($data, $ipAddress, $codeHash, $sessionToken, $publicId, $sessionTtl): array {
            $code = AgentEnrollmentCode::query()
                ->with('device.facility')
                ->where('code_hash', $codeHash)
                ->lockForUpdate()
                ->first();

            if (! $code) {
                throw new AgentEnrollmentException();
            }

            if (! $code->isAvailable()) {
                $this->recordFailedAttempt($code);
                throw new AgentEnrollmentException();
            }

            $device = $code->device;

            if (! $device || ! $device->is_active || $device->archived_at !== null) {
                $code->forceFill(['revoked_at' => now()])->save();
                throw new AgentEnrollmentException();
            }

            $expiresAt = now()->addMinutes($sessionTtl);
            if ($expiresAt->greaterThan($code->expires_at)) {
                $expiresAt = $code->expires_at->copy();
            }

            $code->forceFill([
                'attempts' => $code->attempts + 1,
                'claimed_at' => now(),
                'claimed_ip' => $ipAddress,
            ])->save();

            AgentEnrollmentSession::query()->create([
                'public_id' => $publicId,
                'agent_enrollment_code_id' => $code->getKey(),
                'device_id' => $device->getKey(),
                'session_token_hash' => hash('sha256', $sessionToken),
                'client_nonce_hash' => hash('sha256', $data['client_nonce']),
                'machine_name' => trim($data['machine_name']),
                'architecture' => $data['architecture'] ?? null,
                'windows_version' => $data['windows_version'] ?? null,
                'setup_version' => $data['setup_version'] ?? null,
                'start_ip' => $ipAddress,
                'expires_at' => $expiresAt,
            ]);

            return [
                'ok' => true,
                'status' => 'started',
                'enrollment_id' => $publicId,
                'session_token' => $sessionToken,
                'expires_at' => $expiresAt->toIso8601String(),
                'device' => [
                    'uuid' => $device->uuid,
                    'name' => $device->name,
                    'facility_code' => $device->facility?->code,
                    'facility_name' => $device->facility?->name,
                ],
            ];
        });
    }

    /**
     * @param array{enrollment_id:string,session_token:string,client_nonce:string,machine_name:string,architecture?:string|null,windows_version?:string|null,setup_version?:string|null,agent_version?:string|null} $data
     * @return array<string, mixed>
     */
    public function complete(array $data, ?string $ipAddress): array
    {
        return DB::transaction(function () use ($data, $ipAddress): array {
            $session = AgentEnrollmentSession::query()
                ->with(['device.facility', 'code'])
                ->where('public_id', $data['enrollment_id'])
                ->lockForUpdate()
                ->first();

            $this->assertSessionCredentials($session, $data);

            if ($session->completed_at !== null) {
                return $this->replayCompletedSession($session);
            }

            $plainToken = Str::random(64);
            $device = $session->device;

            if (! $device || ! $device->is_active || $device->archived_at !== null) {
                throw new AgentEnrollmentException('Urządzenie nie jest aktywne.', 409);
            }

            $device->forceFill([
                'token_hash' => hash('sha256', $plainToken),
                'status' => 'unknown',
                'agent_version' => $data['agent_version'] ?? $device->agent_version,
                'last_seen_at' => null,
            ])->save();

            $replayMinutes = max(2, min(15, (int) config('placowka.agent_enrollment_token_replay_minutes', 5)));
            $session->forceFill([
                'complete_ip' => $ipAddress,
                'completed_at' => now(),
                'issued_token_ciphertext' => Crypt::encryptString($plainToken),
                'token_replay_until' => now()->addMinutes($replayMinutes),
            ])->save();

            $session->code->forceFill([
                'used_at' => now(),
                'used_ip' => $ipAddress,
            ])->save();

            return $this->completeResponse($device->fresh('facility'), $plainToken, $session);
        }, 3);
    }

    public function confirmByHeartbeat(Device $device): void
    {
        AgentEnrollmentSession::query()
            ->where('device_id', $device->getKey())
            ->whereNotNull('completed_at')
            ->whereNull('confirmed_at')
            ->update([
                'confirmed_at' => now(),
                'issued_token_ciphertext' => null,
                'token_replay_until' => null,
                'updated_at' => now(),
            ]);
    }

    private function recordFailedAttempt(AgentEnrollmentCode $code): void
    {
        $attempts = min(255, $code->attempts + 1);
        $updates = ['attempts' => $attempts];

        if ($attempts >= $code->max_attempts) {
            $updates['revoked_at'] = now();
        }

        $code->forceFill($updates)->save();
    }

    /** @param AgentEnrollmentSession|null $session */
    private function assertSessionCredentials(?AgentEnrollmentSession $session, array $data): void
    {
        if (! $session || $session->expires_at->isPast()) {
            throw new AgentEnrollmentException('Sesja instalacyjna wygasła. Wygeneruj nowy kod.', 410);
        }

        if (! hash_equals($session->session_token_hash, hash('sha256', $data['session_token']))) {
            throw new AgentEnrollmentException();
        }

        if (! hash_equals($session->client_nonce_hash, hash('sha256', $data['client_nonce']))) {
            throw new AgentEnrollmentException();
        }

        if (mb_strtolower(trim($session->machine_name)) !== mb_strtolower(trim($data['machine_name']))) {
            throw new AgentEnrollmentException('Kod rozpoczęto na innym komputerze.', 409);
        }
    }

    /** @return array<string, mixed> */
    private function replayCompletedSession(AgentEnrollmentSession $session): array
    {
        if (
            $session->issued_token_ciphertext === null
            || $session->token_replay_until === null
            || $session->token_replay_until->isPast()
        ) {
            throw new AgentEnrollmentException('Rejestracja została już zakończona. W razie problemu wygeneruj nowy kod.', 409);
        }

        try {
            $plainToken = Crypt::decryptString($session->issued_token_ciphertext);
        } catch (Throwable) {
            throw new AgentEnrollmentException('Nie można odtworzyć zakończonej sesji instalacyjnej.', 409);
        }

        return $this->completeResponse($session->device, $plainToken, $session);
    }

    /** @return array<string, mixed> */
    private function completeResponse(Device $device, string $plainToken, AgentEnrollmentSession $session): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return [
            'ok' => true,
            'status' => 'completed',
            'enrollment_id' => $session->public_id,
            'token_replay_until' => $session->token_replay_until?->toIso8601String(),
            'configuration' => [
                'api_url' => $baseUrl.'/api/v1/heartbeat/'.$device->uuid,
                'token' => $plainToken,
                'agent_version' => (string) config('placowka.agent_latest_version', 'exe-1.8.0'),
                'location_code' => $device->facility?->code,
                'device_name' => $device->name,
                'timeout_seconds' => 8,
                'performance_profile' => 'low_impact',
                'system_interval_minutes' => 30,
                'network_interval_minutes' => 15,
                'services_interval_minutes' => 5,
                'smart_interval_minutes' => 60,
                'self_check_interval_minutes' => max(10, min(240, (int) config('placowka.agent_self_check_interval_minutes', 30))),
                'startup_jitter_seconds' => 15,
                'monitoring_url' => $baseUrl.'/panel/login',
                'test_urls' => [
                    'https://www.google.com/generate_204',
                    'https://cloudflare.com/cdn-cgi/trace',
                    'https://www.msftconnecttest.com/connecttest.txt',
                ],
                'dns_test_host' => parse_url($baseUrl, PHP_URL_HOST) ?: 'monitoring.wcag-cms.pl',
                'windows_services' => $this->windowsServices->forAgent(),
            ],
        ];
    }

    private function generatePlainCode(Device $device): string
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $device->facility?->code));
        $prefix = mb_substr($prefix ?: 'PO', 0, 8);

        return $prefix.'-'.$this->randomBlock(4).'-'.$this->randomBlock(4);
    }

    private function randomBlock(int $length): string
    {
        $result = '';
        $max = strlen(self::CODE_ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $result .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $result;
    }

    private function normalizeCode(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($code))) ?: '';
    }

    private function hashCode(string $normalizedCode): string
    {
        return hash_hmac('sha256', 'agent-enrollment:'.$normalizedCode, (string) config('app.key'));
    }

    private function maskCode(string $code): string
    {
        $parts = explode('-', $code);

        return ($parts[0] ?? 'PO').'-••••-'.($parts[2] ?? '••••');
    }
}
