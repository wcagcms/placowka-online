<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Incident;

class IncidentLifecycleService
{
    public function openOrTouch(
        Device $device,
        string $type,
        string $summary,
        string $priority,
        IncidentNotificationService $notifications,
        array $meta = []
    ): Incident {
        $incident = Incident::query()
            ->where('device_id', $device->id)
            ->where('type', $type)
            ->active()
            ->first();

        if ($incident) {
            $incident->forceFill([
                'last_seen_at' => now(),
                'summary' => $summary,
                'priority' => $this->normalizePriority($priority),
                'meta' => array_merge((array) $incident->meta, $meta),
                'occurrence_count' => max(1, (int) $incident->occurrence_count) + 1,
            ])->save();

            $notifications->sendOpened($incident->fresh(['facility', 'device']));

            return $incident;
        }

        $incident = Incident::query()->create([
            'facility_id' => $device->facility_id,
            'device_id' => $device->id,
            'type' => $type,
            'status' => Incident::STATUS_OPEN,
            'priority' => $this->normalizePriority($priority),
            'started_at' => now(),
            'last_seen_at' => now(),
            'last_status_change_at' => now(),
            'summary' => $summary,
            'meta' => $meta,
        ]);

        $notifications->sendOpened($incident->fresh(['facility', 'device']));

        return $incident;
    }

    public function resolve(
        Device $device,
        string $type,
        string $summary,
        IncidentNotificationService $notifications
    ): ?Incident {
        $incident = Incident::query()
            ->where('device_id', $device->id)
            ->where('type', $type)
            ->active()
            ->first();

        if (! $incident) {
            return null;
        }

        $endedAt = now();

        $incident->forceFill([
            'status' => Incident::STATUS_RESOLVED,
            'ended_at' => $endedAt,
            'duration_seconds' => max(0, $incident->started_at->diffInSeconds($endedAt)),
            'summary' => $summary,
            'last_status_change_at' => $endedAt,
        ])->save();

        $notifications->sendResolved($incident->fresh(['facility', 'device']));

        return $incident;
    }

    public function priorityForType(string $type): string
    {
        return match ($type) {
            'no_communication',
            'gateway_problem',
            'monitoring_server_problem',
            'smart_failure',
            'defender_problem' => 'critical',
            'dns_problem',
            'internet_problem',
            'windows_service_problem',
            'windows_update_attention' => 'high',
            default => 'medium',
        };
    }

    private function normalizePriority(string $priority): string
    {
        return in_array($priority, Incident::PRIORITIES, true)
            ? $priority
            : 'medium';
    }
}
