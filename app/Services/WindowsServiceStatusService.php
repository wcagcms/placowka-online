<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Collection;

class WindowsServiceStatusService
{
    public function __construct(
        private readonly AgentWindowsServiceConfigService $config,
        private readonly IncidentLifecycleService $incidents
    ) {
    }

    public function process(
        Device $device,
        mixed $rawStatuses,
        IncidentNotificationService $notifications
    ): void {
        $statuses = $this->sanitize($rawStatuses);
        $configuration = collect($this->config->forAgent())
            ->keyBy(fn (array $item): string => mb_strtolower((string) $item['name']));

        if ($statuses->isEmpty() || $configuration->isEmpty()) {
            return;
        }

        $evaluated = $statuses
            ->map(function (array $status) use ($configuration): ?array {
                $configured = $configuration->get(mb_strtolower($status['name']));

                if (! is_array($configured)) {
                    return null;
                }

                $expectedStatus = (string) ($configured['expected_status'] ?? 'Running');
                $healthy = $status['exists']
                    && mb_strtolower($status['status']) === mb_strtolower($expectedStatus);

                return [
                    'name' => $status['name'],
                    'label' => mb_substr((string) ($configured['label'] ?? $status['name']), 0, 190),
                    'status' => $status['exists'] ? $status['status'] : 'Missing',
                    'expected_status' => $expectedStatus,
                    'alert' => (bool) ($configured['alert'] ?? false),
                    'healthy' => $healthy,
                ];
            })
            ->filter()
            ->values();

        $failedRequired = $evaluated
            ->filter(fn (array $service): bool => $service['alert'] && ! $service['healthy'])
            ->values();

        if ($failedRequired->isEmpty()) {
            $this->resolveIncident($device, $notifications);

            return;
        }

        $labels = $failedRequired
            ->map(fn (array $service): string =>
                $service['label'].' ('.$this->statusLabel($service['status']).')'
            )
            ->implode(', ');

        $summary = 'Wymagane usługi Windows nie działają prawidłowo: '.$labels.'.';

        $this->incidents->openOrTouch(
            $device,
            'windows_service_problem',
            $summary,
            'high',
            $notifications
        );
    }

    private function sanitize(mixed $rawStatuses): Collection
    {
        if (! is_array($rawStatuses)) {
            return collect();
        }

        return collect($rawStatuses)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                $name = mb_substr(trim((string) ($item['name'] ?? '')), 0, 150);
                $status = mb_substr(trim((string) ($item['status'] ?? 'Unknown')), 0, 50);

                return [
                    'name' => $name,
                    'status' => $status !== '' ? $status : 'Unknown',
                    'exists' => filter_var($item['exists'] ?? false, FILTER_VALIDATE_BOOL),
                ];
            })
            ->filter(fn (array $item): bool => $item['name'] !== '')
            ->take(50)
            ->values();
    }

    private function resolveIncident(
        Device $device,
        IncidentNotificationService $notifications
    ): void {
        $this->incidents->resolve(
            $device,
            'windows_service_problem',
            'Wymagane usługi Windows ponownie działają prawidłowo.',
            $notifications
        );
    }

    private function statusLabel(string $status): string
    {
        return match (mb_strtolower($status)) {
            'running' => 'działa',
            'stopped' => 'zatrzymana',
            'paused' => 'wstrzymana',
            'start pending' => 'uruchamianie',
            'stop pending' => 'zatrzymywanie',
            'missing' => 'brak usługi',
            default => $status,
        };
    }
}
