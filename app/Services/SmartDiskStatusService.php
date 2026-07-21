<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Collection;

class SmartDiskStatusService
{
    public function __construct(
        private readonly IncidentLifecycleService $incidents
    ) {
    }
    public function process(
        Device $device,
        mixed $rawSmartInfo,
        IncidentNotificationService $notifications
    ): void {
        $disks = $this->sanitize($rawSmartInfo);

        if ($disks->isEmpty()) {
            return;
        }

        $critical = $disks
            ->filter(fn (array $disk): bool => $this->isCritical($disk))
            ->values();

        if ($critical->isEmpty()) {
            $this->resolveIncident($device, $notifications);

            return;
        }

        $summary = 'Wykryto krytyczny problem SMART: '
            . $critical
                ->map(fn (array $disk): string =>
                    $disk['name'] . ' — ' . $this->reason($disk)
                )
                ->implode('; ')
            . '.';

        $this->incidents->openOrTouch(
            $device,
            'smart_disk_problem',
            $summary,
            'critical',
            $notifications
        );
    }

    /**
     * @return Collection<int, array{
     *     name:string,
     *     health_status:string,
     *     predict_failure:bool,
     *     smart_supported:bool,
     *     temperature_c:float|null,
     *     wear_percent_used:float|null
     * }>
     */
    private function sanitize(mixed $rawSmartInfo): Collection
    {
        if (! is_array($rawSmartInfo)) {
            return collect();
        }

        $rawDisks = $rawSmartInfo['disks'] ?? [];

        if (! is_array($rawDisks)) {
            return collect();
        }

        return collect($rawDisks)
            ->filter(fn (mixed $disk): bool => is_array($disk))
            ->map(function (array $disk): array {
                $name = trim((string) (
                    $disk['friendly_name']
                    ?? $disk['model']
                    ?? $disk['device_id']
                    ?? 'Dysk'
                ));

                return [
                    'name' => mb_substr($name !== '' ? $name : 'Dysk', 0, 190),
                    'health_status' => mb_substr(
                        trim((string) ($disk['health_status'] ?? 'Unknown')),
                        0,
                        50
                    ),
                    'predict_failure' => filter_var(
                        $disk['predict_failure'] ?? false,
                        FILTER_VALIDATE_BOOL
                    ),
                    'smart_supported' => filter_var(
                        $disk['smart_supported'] ?? false,
                        FILTER_VALIDATE_BOOL
                    ),
                    'temperature_c' => is_numeric($disk['temperature_c'] ?? null)
                        ? (float) $disk['temperature_c']
                        : null,
                    'wear_percent_used' => is_numeric($disk['wear_percent_used'] ?? null)
                        ? (float) $disk['wear_percent_used']
                        : null,
                ];
            })
            ->take(20)
            ->values();
    }

    private function isCritical(array $disk): bool
    {
        if ($disk['predict_failure']) {
            return true;
        }

        $health = mb_strtolower($disk['health_status']);

        if (! in_array($health, ['', 'healthy', 'ok', 'unknown'], true)) {
            return true;
        }

        if ($disk['temperature_c'] !== null && $disk['temperature_c'] >= 65) {
            return true;
        }

        return $disk['wear_percent_used'] !== null
            && $disk['wear_percent_used'] >= 95;
    }

    private function reason(array $disk): string
    {
        if ($disk['predict_failure']) {
            return 'przewidywana awaria lub krytyczny stan dysku';
        }

        if ($disk['temperature_c'] !== null && $disk['temperature_c'] >= 65) {
            return 'temperatura '
                . number_format($disk['temperature_c'], 1, ',', ' ')
                . '°C';
        }

        if (
            $disk['wear_percent_used'] !== null
            && $disk['wear_percent_used'] >= 95
        ) {
            return 'zużycie SSD '
                . number_format($disk['wear_percent_used'], 1, ',', ' ')
                . '%';
        }

        return 'stan ' . $disk['health_status'];
    }

    private function resolveIncident(
        Device $device,
        IncidentNotificationService $notifications
    ): void {
        $this->incidents->resolve(
            $device,
            'smart_disk_problem',
            'Dyski nie zgłaszają już krytycznego problemu SMART.',
            $notifications
        );
    }
}
