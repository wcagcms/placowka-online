<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class SecurityAuditLogger
{
    private const ALLOWED_CONTEXT_KEYS = [
        'email',
        'operator_id',
        'operator_email',
        'facility_ids',
        'active',
        'role',
        'reason',
        'route',
        'device_id',
        'facility_id',
        'enrollment_id',
        'machine_name',
        'setup_version',
        'expires_at',
        'incident_id',
        'backup_id',
        'status',
        'priority',
    ];

    public function write(
        string $event,
        ?User $user = null,
        mixed $subject = null,
        array $context = [],
        ?Request $request = null
    ): void {
        try {
            SecurityAuditLog::query()->create([
                'user_id' => $user?->getKey(),
                'event' => mb_substr($event, 0, 100),
                'subject_type' => is_object($subject) ? $subject::class : null,
                'subject_id' => is_object($subject) && method_exists($subject, 'getKey')
                    ? $subject->getKey()
                    : null,
                'ip_address' => mb_substr((string) ($request?->ip() ?? ''), 0, 45) ?: null,
                'user_agent' => mb_substr((string) ($request?->userAgent() ?? ''), 0, 500) ?: null,
                'context' => Arr::only($context, self::ALLOWED_CONTEXT_KEYS),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Nie udało się zapisać dziennika bezpieczeństwa.', [
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
