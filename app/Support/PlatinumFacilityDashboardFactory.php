<?php

namespace App\Support;

use App\Models\Device;
use App\Models\Facility;
use App\Models\Heartbeat;
use App\Models\Incident;
use App\Services\DeviceHealthScoreService;
use Illuminate\Support\Collection;

class PlatinumFacilityDashboardFactory
{
    /**
     * @param Collection<int, Heartbeat> $heartbeats
     * @param Collection<int, Incident> $incidents
     * @param array<string, int> $incidentStats
     * @param Collection<int, Heartbeat> $latestHeartbeats
     * @param Collection<int, Collection<int, Incident>> $recentIncidentsByDevice
     * @return array<string, mixed>
     */
    public static function make(
        Facility $facility,
        Collection $heartbeats,
        Collection $incidents,
        array $incidentStats,
        Collection $latestHeartbeats,
        Collection $recentIncidentsByDevice,
        DeviceHealthScoreService $healthScoreService
    ): array {
        $devices = $facility->devices->values();
        $healthScores = collect();
        $confidences = collect();
        $smartHealthy = 0;
        $smartWarnings = 0;
        $smartTotal = 0;
        $servicesHealthy = 0;
        $servicesTotal = 0;
        $windowsReporting = 0;
        $cpuValues = collect();
        $memoryValues = collect();
        $currentInternetChecks = collect();
        $currentDnsChecks = collect();
        $currentMonitoringChecks = collect();
        $currentLatencies = collect();
        $currentReporting = 0;
        $activeCurrentDevices = $devices
            ->filter(fn (Device $device): bool => $device->archived_at === null && $device->is_active)
            ->count();

        $deviceItems = $devices->map(function (Device $device) use (
            $latestHeartbeats,
            $recentIncidentsByDevice,
            $healthScoreService,
            &$healthScores,
            &$confidences,
            &$smartHealthy,
            &$smartWarnings,
            &$smartTotal,
            &$servicesHealthy,
            &$servicesTotal,
            &$windowsReporting,
            &$cpuValues,
            &$memoryValues,
            &$currentInternetChecks,
            &$currentDnsChecks,
            &$currentMonitoringChecks,
            &$currentLatencies,
            &$currentReporting
        ): array {
            $latestHeartbeat = $latestHeartbeats->get($device->id);
            $freshness = DeviceTelemetryFreshness::describe($device, $latestHeartbeat);
            $isFresh = (bool) $freshness['is_fresh'];
            $deviceIncidents = $recentIncidentsByDevice->get($device->id, collect());
            $health = $isFresh
                ? $healthScoreService->calculate($device, $latestHeartbeat, $deviceIncidents)
                : DeviceTelemetryFreshness::unavailableHealthScore($freshness);

            if ($isFresh) {
                $healthScores->push((int) data_get($health, 'score', 0));
                $confidences->push((int) data_get($health, 'confidence', 0));
                $currentReporting++;

                if ($latestHeartbeat?->internet_ok !== null) {
                    $currentInternetChecks->push($latestHeartbeat->internet_ok);
                }
                if ($latestHeartbeat?->dns_ok !== null) {
                    $currentDnsChecks->push($latestHeartbeat->dns_ok);
                }
                if ($latestHeartbeat?->monitoring_server_ok !== null) {
                    $currentMonitoringChecks->push($latestHeartbeat->monitoring_server_ok);
                }
                if (is_numeric($latestHeartbeat?->latency_ms)) {
                    $currentLatencies->push((float) $latestHeartbeat->latency_ms);
                }
            }

            $payload = $isFresh && is_array($latestHeartbeat?->payload)
                ? $latestHeartbeat->payload
                : [];

            $systemInfo = data_get($payload, 'system_info', []);
            $systemInfo = is_array($systemInfo) ? $systemInfo : [];

            $cpu = data_get($systemInfo, 'cpu.usage_percent');
            $memory = data_get($systemInfo, 'memory.usage_percent');

            if (is_numeric($cpu)) {
                $cpuValues->push((float) $cpu);
            }

            if (is_numeric($memory)) {
                $memoryValues->push((float) $memory);
            }

            if ($systemInfo !== []) {
                $windowsReporting++;
            }

            $smartDisks = collect(data_get($payload, 'smart_info.disks', []))
                ->filter(fn ($disk): bool => is_array($disk));

            foreach ($smartDisks as $disk) {
                $smartTotal++;

                if (self::smartDiskRequiresAttention($disk)) {
                    $smartWarnings++;
                } else {
                    $smartHealthy++;
                }
            }

            $services = collect(data_get($payload, 'windows_services', []))
                ->filter(fn ($service): bool => is_array($service));

            $servicesTotal += $services->count();
            $servicesHealthy += $services
                ->filter(fn (array $service): bool => self::serviceHealthy($service))
                ->count();

            $statusState = $isFresh
                ? self::deviceState($device)
                : (($device->archived_at !== null || ! $device->is_active) ? 'unknown' : 'critical');
            $healthState = self::healthState((string) data_get($health, 'status', 'unknown'));
            $state = self::worstState($statusState, $healthState);

            return [
                'id' => $device->id,
                'name' => $device->name,
                'uuid' => $device->uuid,
                'state' => $state,
                'status_label' => $isFresh
                    ? self::deviceStatusLabel($device)
                    : (($device->archived_at !== null || ! $device->is_active)
                        ? self::deviceStatusLabel($device)
                        : 'Brak komunikacji'),
                'diagnostic_label' => $isFresh
                    ? self::diagnosticLabel($device->diagnostic_status)
                    : (string) $freshness['label'],
                'health_score' => data_get($health, 'score'),
                'health_score_display' => is_numeric(data_get($health, 'score'))
                    ? ((int) data_get($health, 'score')).'/100'
                    : 'Brak bieżącej oceny',
                'health_label' => (string) data_get($health, 'label', 'Brak oceny'),
                'confidence' => (int) data_get($health, 'confidence', 0),
                'confidence_display' => $isFresh
                    ? ((int) data_get($health, 'confidence', 0)).'%'
                    : '0% — dane nieaktualne',
                'telemetry_fresh' => $isFresh,
                'telemetry_notice' => (string) $freshness['description'],
                'last_seen' => $device->last_seen_at
                    ? $device->last_seen_at->timezone('Europe/Warsaw')->format('Y-m-d H:i:s')
                    : 'Brak danych',
                'last_seen_relative' => $device->last_seen_at
                    ? $device->last_seen_at->diffForHumans()
                    : 'brak danych',
                'latency' => $isFresh && $device->last_latency_ms !== null
                    ? self::number($device->last_latency_ms, 0).' ms'
                    : 'Brak bieżących danych',
                'ip_address' => $device->last_ip ?: 'Brak danych',
                'agent_version' => $device->agent_version ?: 'Brak danych',
                'is_archived' => $device->archived_at !== null,
                'is_active' => (bool) $device->is_active,
                'archive_label' => $device->archived_at
                    ? 'Archiwalne od '.$device->archived_at->timezone('Europe/Warsaw')->format('Y-m-d H:i')
                    : null,
                'checks' => [
                    self::check('Brama', $isFresh ? $device->gateway_ok : null),
                    self::check('DNS', $isFresh ? $device->dns_ok : null),
                    self::check('Internet', $isFresh ? $device->internet_ok : null),
                    self::check('Monitoring', $isFresh ? $device->monitoring_server_ok : null),
                ],
                'details_url' => route('devices.heartbeats', ['device' => $device->id]),
                'manage_url' => route('devices.edit', ['device' => $device->id]),
                'sort' => self::deviceSort($device, $state),
            ];
        })
            ->sortBy('sort')
            ->values()
            ->map(function (array $item): array {
                unset($item['sort']);

                return $item;
            })
            ->all();

        $online = collect($deviceItems)->where('status_label', 'Online')->count();
        $problem = collect($deviceItems)->where('status_label', 'Wymaga uwagi')->count();
        $offline = collect($deviceItems)->where('status_label', 'Brak komunikacji')->count();
        $unknown = collect($deviceItems)
            ->filter(fn (array $item): bool => in_array($item['status_label'], ['Brak danych', 'Nieaktywne', 'Archiwalne'], true))
            ->count();
        $active = $devices
            ->filter(fn (Device $device): bool => $device->archived_at === null && $device->is_active)
            ->count();
        $archived = $devices->whereNotNull('archived_at')->count();

        $globalHealth = $healthScores->isNotEmpty()
            ? (int) round($healthScores->average())
            : null;
        $globalConfidence = $confidences->isNotEmpty()
            ? (int) round($confidences->average())
            : 0;

        $facilityState = match (true) {
            (int) data_get($incidentStats, 'open', 0) > 0 || $offline > 0 => 'critical',
            $problem > 0 || $unknown > 0 || ($globalHealth !== null && $globalHealth < 80) => 'warning',
            $devices->isEmpty() => 'unknown',
            default => 'healthy',
        };

        $facilityStatusLabel = match ($facilityState) {
            'healthy' => 'Stan prawidłowy',
            'warning' => 'Wymaga uwagi',
            'critical' => 'Wymaga interwencji',
            default => 'Brak danych',
        };

        $facilitySummary = match ($facilityState) {
            'healthy' => 'Wszystkie aktywne urządzenia przesyłają bieżące dane. Nie wykryto aktywnych awarii.',
            'warning' => 'Część urządzeń wymaga sprawdzenia lub nie przekazała pełnego zestawu danych.',
            'critical' => 'W placówce występuje aktywna awaria albo co najmniej jedno urządzenie nie przesyła bieżących danych.',
            default => 'Dodaj urządzenie lub poczekaj na pierwszy pomiar agenta.',
        };

        $latencyValues = $currentLatencies->values();
        $internetAvailability = self::booleanPercent($currentInternetChecks);
        $dnsAvailability = self::booleanPercent($currentDnsChecks);
        $monitoringAvailability = self::booleanPercent($currentMonitoringChecks);
        $hasCurrentData = $currentReporting > 0;
        $hasPartialCoverage = $hasCurrentData && $currentReporting < $activeCurrentDevices;
        $coverageDescription = $hasCurrentData
            ? 'Bieżące dane z '.$currentReporting.' z '.$activeCurrentDevices.' aktywnych urządzeń.'
            : 'Brak bieżących danych — żaden aktywny agent nie przesyła heartbeatów.';

        $latestMeasurement = $heartbeats
            ->map(fn (Heartbeat $heartbeat) => $heartbeat->checked_at ?: $heartbeat->created_at)
            ->filter()
            ->sortDesc()
            ->first();

        $heartbeatItems = $heartbeats
            ->take(15)
            ->map(function (Heartbeat $heartbeat): array {
                $state = match ($heartbeat->status) {
                    'online' => 'healthy',
                    'problem' => 'warning',
                    'offline' => 'critical',
                    default => 'unknown',
                };

                return [
                    'state' => $state,
                    'device_name' => $heartbeat->device?->name ?: 'Nieznane urządzenie',
                    'status_label' => match ($heartbeat->status) {
                        'online' => 'Online',
                        'problem' => 'Problem',
                        'offline' => 'Offline',
                        default => 'Brak danych',
                    },
                    'diagnostic_label' => self::diagnosticLabel($heartbeat->diagnostic_status),
                    'checked_at' => ($heartbeat->checked_at ?: $heartbeat->created_at)
                        ->timezone('Europe/Warsaw')
                        ->format('Y-m-d H:i:s'),
                    'latency' => $heartbeat->latency_ms !== null
                        ? self::number($heartbeat->latency_ms, 0).' ms'
                        : 'Brak danych',
                    'agent_version' => $heartbeat->agent_version ?: 'Brak danych',
                    'checks' => [
                        self::check('Brama', $heartbeat->gateway_ok),
                        self::check('DNS', $heartbeat->dns_ok),
                        self::check('Internet', $heartbeat->internet_ok),
                        self::check('Monitoring', $heartbeat->monitoring_server_ok),
                    ],
                ];
            })
            ->all();

        $incidentItems = $incidents
            ->map(function (Incident $incident): array {
                $isOpen = in_array($incident->status, \App\Models\Incident::ACTIVE_STATUSES, true);

                return [
                    'state' => $isOpen ? 'critical' : 'healthy',
                    'status_label' => $isOpen ? 'Aktywna' : 'Zakończona',
                    'device_name' => $incident->device?->name ?: 'Nieznane urządzenie',
                    'type_label' => self::incidentTypeLabel($incident->type),
                    'started_at' => $incident->started_at
                        ? $incident->started_at->timezone('Europe/Warsaw')->format('Y-m-d H:i:s')
                        : 'Brak danych',
                    'ended_at' => $incident->ended_at
                        ? $incident->ended_at->timezone('Europe/Warsaw')->format('Y-m-d H:i:s')
                        : 'Trwa',
                    'duration' => self::duration($incident->duration_seconds),
                    'notification_label' => self::notificationLabel($incident),
                    'notification_error' => $incident->notification_last_error,
                ];
            })
            ->all();

        return [
            'meta' => [
                'state' => $facilityState,
                'status_label' => $facilityStatusLabel,
                'summary' => $facilitySummary,
                'health_score' => $globalHealth,
                'confidence' => $globalConfidence,
                'has_current_data' => $hasCurrentData,
                'current_reporting' => $currentReporting,
                'active_devices' => $activeCurrentDevices,
                'freshness_message' => $coverageDescription,
                'code' => $facility->code,
                'name' => $facility->name,
                'address' => $facility->address ?: 'Brak adresu',
                'contact_email' => $facility->contact_email ?: 'Brak adresu e-mail',
                'is_active' => (bool) $facility->is_active,
                'latest_measurement' => $latestMeasurement
                    ? $latestMeasurement->timezone('Europe/Warsaw')->format('Y-m-d H:i:s')
                    : 'Brak danych',
                'latest_measurement_relative' => $latestMeasurement
                    ? $latestMeasurement->diffForHumans()
                    : 'brak danych',
            ],
            'counts' => [
                'total' => $devices->count(),
                'active' => $active,
                'archived' => $archived,
                'online' => $online,
                'problem' => $problem,
                'offline' => $offline,
                'unknown' => $unknown,
                'open_incidents' => (int) data_get($incidentStats, 'open', 0),
                'incidents_24h' => (int) data_get($incidentStats, 'last_24h', 0),
                'incidents_7d' => (int) data_get($incidentStats, 'last_7d', 0),
                'incidents_30d' => (int) data_get($incidentStats, 'last_30d', 0),
            ],
            'modules' => [
                'internet' => [
                    'state' => self::currentState(self::availabilityState($internetAvailability), $hasCurrentData, $hasPartialCoverage),
                    'value' => $internetAvailability !== null
                        ? self::number($internetAvailability, 1).'%'
                        : 'Brak danych',
                    'label' => 'Dostępność Internetu',
                    'description' => $coverageDescription,
                ],
                'latency' => [
                    'state' => self::currentState(self::latencyState($latencyValues->average()), $hasCurrentData, $hasPartialCoverage),
                    'value' => $latencyValues->isNotEmpty()
                        ? self::number($latencyValues->average(), 0).' ms'
                        : 'Brak danych',
                    'label' => 'Średni czas odpowiedzi',
                    'description' => $coverageDescription,
                    'sparkline' => self::sparkline($latencyValues),
                ],
                'smart' => [
                    'state' => self::currentState(match (true) {
                        $smartTotal === 0 => 'unknown',
                        $smartWarnings > 0 => 'warning',
                        default => 'healthy',
                    }, $hasCurrentData, $hasPartialCoverage),
                    'value' => $smartTotal > 0
                        ? $smartHealthy.' / '.$smartTotal
                        : 'Brak danych',
                    'label' => 'SMART dysków',
                    'description' => ! $hasCurrentData
                        ? $coverageDescription
                        : ($smartWarnings > 0
                            ? $smartWarnings.' dysków wymaga uwagi. '.$coverageDescription
                            : 'Dyski w bieżących raportach nie zgłaszają problemów. '.$coverageDescription),
                ],
                'services' => [
                    'state' => self::currentState(match (true) {
                        $servicesTotal === 0 => 'unknown',
                        $servicesHealthy < $servicesTotal => 'warning',
                        default => 'healthy',
                    }, $hasCurrentData, $hasPartialCoverage),
                    'value' => $servicesTotal > 0
                        ? $servicesHealthy.' / '.$servicesTotal
                        : 'Brak danych',
                    'label' => 'Usługi Windows',
                    'description' => $hasCurrentData ? 'Usługi względem bieżących raportów. '.$coverageDescription : $coverageDescription,
                ],
                'windows' => [
                    'state' => self::currentState(match (true) {
                        ! $hasCurrentData => 'unknown',
                        $windowsReporting === $currentReporting => 'healthy',
                        default => 'warning',
                    }, $hasCurrentData, $hasPartialCoverage),
                    'value' => $hasCurrentData
                        ? $windowsReporting.' / '.$currentReporting
                        : 'Brak bieżących danych',
                    'label' => 'Dane systemowe Windows',
                    'description' => $coverageDescription,
                ],
                'resources' => [
                    'state' => self::currentState(self::resourceState($cpuValues->average(), $memoryValues->average()), $hasCurrentData, $hasPartialCoverage),
                    'value' => ($cpuValues->isNotEmpty() || $memoryValues->isNotEmpty())
                        ? 'CPU '.self::optionalPercent($cpuValues->average())
                            .' · RAM '.self::optionalPercent($memoryValues->average())
                        : 'Brak danych',
                    'label' => 'Średnie wykorzystanie',
                    'description' => $coverageDescription,
                ],
                'dns' => [
                    'state' => self::currentState(self::availabilityState($dnsAvailability), $hasCurrentData, $hasPartialCoverage),
                    'value' => $dnsAvailability !== null
                        ? self::number($dnsAvailability, 1).'%'
                        : 'Brak danych',
                    'label' => 'Dostępność DNS',
                    'description' => $coverageDescription,
                ],
                'monitoring' => [
                    'state' => self::currentState(self::availabilityState($monitoringAvailability), $hasCurrentData, $hasPartialCoverage),
                    'value' => $monitoringAvailability !== null
                        ? self::number($monitoringAvailability, 1).'%'
                        : 'Brak danych',
                    'label' => 'Serwer monitoringu',
                    'description' => $coverageDescription,
                ],
            ],
            'devices' => $deviceItems,
            'heartbeats' => $heartbeatItems,
            'incidents' => $incidentItems,
        ];
    }

    /** @return array<string, string> */
    private static function check(string $label, ?bool $value): array
    {
        return [
            'label' => $label,
            'state' => $value === null ? 'unknown' : ($value ? 'healthy' : 'critical'),
            'value' => $value === null ? 'Brak danych' : ($value ? 'OK' : 'Błąd'),
        ];
    }

    private static function deviceState(Device $device): string
    {
        return match ($device->status) {
            'online' => 'healthy',
            'problem' => 'warning',
            'offline' => 'critical',
            default => 'unknown',
        };
    }

    private static function healthState(string $status): string
    {
        return match ($status) {
            'excellent', 'good' => 'healthy',
            'warning' => 'warning',
            'critical' => 'critical',
            default => 'unknown',
        };
    }

    private static function worstState(string ...$states): string
    {
        $priority = [
            'unknown' => 1,
            'healthy' => 2,
            'warning' => 3,
            'critical' => 4,
        ];

        return collect($states)
            ->sortByDesc(fn (string $state): int => $priority[$state] ?? 0)
            ->first() ?: 'unknown';
    }

    private static function deviceStatusLabel(Device $device): string
    {
        if ($device->archived_at !== null) {
            return 'Archiwalne';
        }

        if (! $device->is_active) {
            return 'Nieaktywne';
        }

        return match ($device->status) {
            'online' => 'Online',
            'problem' => 'Wymaga uwagi',
            'offline' => 'Brak komunikacji',
            default => 'Brak danych',
        };
    }

    private static function diagnosticLabel(?string $status): string
    {
        return match ($status) {
            'online' => 'Połączenie prawidłowe',
            'gateway_problem' => 'Problem z routerem lub bramą',
            'dns_problem' => 'Problem z DNS',
            'internet_problem' => 'Problem z Internetem',
            'monitoring_server_problem' => 'Problem z serwerem monitoringu',
            default => 'Brak szczegółowej diagnozy',
        };
    }

    private static function deviceSort(Device $device, string $state): int
    {
        $base = match ($state) {
            'critical' => 10,
            'warning' => 20,
            'unknown' => 30,
            default => 40,
        };

        if ($device->archived_at !== null) {
            return 90;
        }

        if (! $device->is_active) {
            return 80;
        }

        return $base;
    }

    private static function booleanPercent(Collection $values): ?float
    {
        $values = $values->filter(fn ($value): bool => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return round(
            ($values->filter(fn ($value): bool => (bool) $value)->count()
                / $values->count()) * 100,
            1
        );
    }

    private static function currentState(
        string $state,
        bool $hasCurrentData,
        bool $hasPartialCoverage
    ): string {
        if (! $hasCurrentData) {
            return 'unknown';
        }

        if ($hasPartialCoverage && $state === 'healthy') {
            return 'warning';
        }

        return $state;
    }

    private static function availabilityState(?float $value): string
    {
        return match (true) {
            $value === null => 'unknown',
            $value < 90 => 'critical',
            $value < 99 => 'warning',
            default => 'healthy',
        };
    }

    private static function latencyState(mixed $value): string
    {
        if (! is_numeric($value)) {
            return 'unknown';
        }

        return match (true) {
            (float) $value >= 250 => 'critical',
            (float) $value >= 120 => 'warning',
            default => 'healthy',
        };
    }

    private static function resourceState(mixed $cpu, mixed $memory): string
    {
        if (! is_numeric($cpu) && ! is_numeric($memory)) {
            return 'unknown';
        }

        $max = max(
            is_numeric($cpu) ? (float) $cpu : 0,
            is_numeric($memory) ? (float) $memory : 0
        );

        return match (true) {
            $max >= 92 => 'critical',
            $max >= 80 => 'warning',
            default => 'healthy',
        };
    }

    private static function optionalPercent(mixed $value): string
    {
        return is_numeric($value)
            ? self::number($value, 1).'%'
            : '—';
    }

    /** @return array{line:string,fill:string} */
    private static function sparkline(Collection $values): array
    {
        $values = $values
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): float => (float) $value)
            ->reverse()
            ->values()
            ->take(30);

        if ($values->count() < 2) {
            return [
                'line' => 'M0 16 L180 16',
                'fill' => 'M0 16 L180 16 L180 32 L0 32Z',
            ];
        }

        $minimum = (float) $values->min();
        $maximum = (float) $values->max();
        $range = max(1.0, $maximum - $minimum);
        $lastIndex = max(1, $values->count() - 1);
        $points = [];

        foreach ($values as $index => $value) {
            $x = ($index / $lastIndex) * 180;
            $y = 28 - ((($value - $minimum) / $range) * 22);
            $points[] = number_format($x, 1, '.', '')
                .' '
                .number_format($y, 1, '.', '');
        }

        $line = 'M'.implode(' L', $points);

        return [
            'line' => $line,
            'fill' => $line.' L180 32 L0 32Z',
        ];
    }

    private static function smartDiskRequiresAttention(array $disk): bool
    {
        $health = mb_strtolower(trim((string) data_get($disk, 'health_status', '')));
        $temperature = data_get($disk, 'temperature_c');
        $wear = data_get($disk, 'wear_percent_used');

        return (bool) data_get($disk, 'predict_failure', false)
            || ! in_array($health, ['', 'healthy', 'ok', 'unknown'], true)
            || (is_numeric($temperature) && (float) $temperature >= 55)
            || (is_numeric($wear) && (float) $wear >= 80);
    }

    private static function serviceHealthy(array $service): bool
    {
        if (array_key_exists('healthy', $service)) {
            return (bool) data_get($service, 'healthy', false);
        }

        $status = mb_strtolower((string) data_get($service, 'status', ''));
        $expected = mb_strtolower((string) data_get($service, 'expected_status', 'running'));

        return $status !== '' && $status === $expected;
    }

    private static function incidentTypeLabel(?string $type): string
    {
        return match ($type) {
            'no_communication' => 'Brak komunikacji',
            'gateway_problem' => 'Problem z routerem lub bramą',
            'dns_problem' => 'Problem z DNS',
            'internet_problem' => 'Problem z Internetem',
            'monitoring_server_problem' => 'Problem z serwerem monitoringu',
            'windows_service_problem' => 'Problem z usługą Windows',
            'smart_problem' => 'Problem SMART dysku',
            default => $type ?: 'Nieokreślony problem',
        };
    }

    private static function duration(mixed $seconds): string
    {
        if (! is_numeric($seconds)) {
            return 'Trwa lub brak danych';
        }

        $seconds = max(0, (int) $seconds);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;
        $parts = [];

        if ($days > 0) {
            $parts[] = $days.' d';
        }

        if ($hours > 0) {
            $parts[] = $hours.' godz.';
        }

        if ($minutes > 0) {
            $parts[] = $minutes.' min';
        }

        if ($parts === []) {
            $parts[] = $remainingSeconds.' sek.';
        }

        return implode(' ', $parts);
    }

    private static function notificationLabel(Incident $incident): string
    {
        if ($incident->notification_last_error) {
            return 'Błąd powiadomienia';
        }

        if (in_array($incident->status, \App\Models\Incident::ACTIVE_STATUSES, true)) {
            return $incident->opened_notification_sent_at
                ? 'Powiadomienie o rozpoczęciu wysłane'
                : 'Powiadomienie jeszcze niewysłane';
        }

        return $incident->resolved_notification_sent_at
            ? 'Powiadomienia wysłane'
            : 'Brak potwierdzenia powiadomienia końcowego';
    }

    private static function number(mixed $value, int $decimals): string
    {
        return number_format((float) $value, $decimals, ',', ' ');
    }
}
