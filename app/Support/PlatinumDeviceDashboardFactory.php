<?php

namespace App\Support;

use App\Models\Device;
use App\Models\Heartbeat;
use App\Models\Incident;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlatinumDeviceDashboardFactory
{
    /**
     * @param Collection<int, Heartbeat> $heartbeats
     * @param Collection<int, Incident> $incidents
     * @param array<string, mixed> $healthScore
     * @param array<string, mixed> $freshness
     * @return array<string, mixed>
     */
    public static function make(
        Device $device,
        Collection $heartbeats,
        Collection $incidents,
        array $healthScore,
        array $freshness
    ): array {
        $latestHeartbeat = $heartbeats->first();
        $isFresh = (bool) data_get($freshness, 'is_fresh', false);
        $payload = $isFresh && is_array($latestHeartbeat?->payload)
            ? $latestHeartbeat->payload
            : [];

        $systemInfo = data_get($payload, 'system_info', []);
        $systemInfo = is_array($systemInfo) ? $systemInfo : [];

        $cpu = data_get($systemInfo, 'cpu', []);
        $cpu = is_array($cpu) ? $cpu : [];

        $memory = data_get($systemInfo, 'memory', []);
        $memory = is_array($memory) ? $memory : [];

        $network = is_array($device->network_info) ? $device->network_info : [];
        $wifi = data_get($network, 'wifi', []);
        $wifi = is_array($wifi) ? $wifi : [];

        $systemDisks = collect(data_get($systemInfo, 'disks', []))
            ->filter(fn ($disk): bool => is_array($disk))
            ->values();

        $smartDisks = collect(data_get($payload, 'smart_info.disks', []))
            ->filter(fn ($disk): bool => is_array($disk))
            ->values();

        $services = collect(data_get($payload, 'windows_services', []))
            ->filter(fn ($service): bool => is_array($service))
            ->values();

        $cpuSeries = $isFresh
            ? self::payloadSeries($heartbeats, 'system_info.cpu.usage_percent')
            : collect();
        $memorySeries = $isFresh
            ? self::payloadSeries($heartbeats, 'system_info.memory.usage_percent')
            : collect();
        $latencySeries = $isFresh
            ? $heartbeats
                ->pluck('latency_ms')
                ->filter(fn ($value): bool => is_numeric($value))
                ->map(fn ($value): float => (float) $value)
                ->values()
            : collect();

        $smartFactor = collect(data_get($healthScore, 'factors', []))
            ->firstWhere('key', 'smart');

        $smartPercent = is_array($smartFactor)
            && (bool) data_get($smartFactor, 'available', false)
            && (float) data_get($smartFactor, 'max', 0) > 0
                ? (int) round(
                    ((float) data_get($smartFactor, 'score', 0)
                        / (float) data_get($smartFactor, 'max')) * 100
                )
                : null;

        $internetAvailability = $isFresh
            ? self::booleanPercent($heartbeats->pluck('internet_ok'))
            : null;

        $healthValue = is_numeric(data_get($healthScore, 'score'))
            ? (int) data_get($healthScore, 'score')
            : null;
        $healthStatus = (string) data_get($healthScore, 'status', 'unknown');

        $metrics = [
            'cpu' => self::metric(
                'Procesor',
                data_get($cpu, 'usage_percent'),
                '%',
                75,
                90,
                $cpuSeries,
                true,
                'Bieżące użycie procesora'
            ),
            'memory' => self::metric(
                'Pamięć RAM',
                data_get($memory, 'usage_percent'),
                '%',
                80,
                92,
                $memorySeries,
                true,
                'Bieżące wykorzystanie pamięci'
            ),
            'smart' => [
                'label' => 'SMART',
                'value' => $smartPercent,
                'display' => $smartPercent !== null ? $smartPercent.'%' : 'Brak danych',
                'state' => self::factorStateToMetricState(
                    is_array($smartFactor)
                        ? (string) data_get($smartFactor, 'state', 'unknown')
                        : 'unknown'
                ),
                'trend' => [
                    'direction' => 'flat',
                    'label' => 'ostatni pomiar',
                    'tone' => 'neutral',
                ],
                'sparkline' => self::sparkline([]),
                'note' => is_array($smartFactor)
                    ? (string) data_get($smartFactor, 'value', 'Brak danych SMART')
                    : 'Brak danych SMART',
            ],
            'internet' => [
                'label' => 'Dostępność Internetu',
                'value' => $internetAvailability,
                'display' => $internetAvailability !== null
                    ? self::number($internetAvailability, 1).'%'
                    : 'Brak danych',
                'state' => match (true) {
                    $internetAvailability === null => 'unknown',
                    $internetAvailability < 90 => 'critical',
                    $internetAvailability < 99 => 'warning',
                    default => 'healthy',
                },
                'trend' => $isFresh ? self::booleanTrend($heartbeats->pluck('internet_ok')) : [
                    'direction' => 'flat',
                    'label' => 'brak bieżących danych',
                    'tone' => 'neutral',
                ],
                'sparkline' => $isFresh
                    ? self::sparkline(
                        $heartbeats
                            ->pluck('internet_ok')
                            ->filter(fn ($value): bool => $value !== null)
                            ->map(fn ($value): int => $value ? 100 : 0)
                            ->values()
                    )
                    : self::sparkline([]),
                'note' => $isFresh && $device->last_latency_ms !== null
                    ? 'Opóźnienie: '.self::number($device->last_latency_ms, 0).' ms'
                    : 'Brak bieżącego pomiaru opóźnienia',
            ],
        ];

        $connectionChecks = collect([
            self::connectionCheck(
                'Router / brama',
                $isFresh ? $device->gateway_ok : null,
                null,
                'Dostępność bramy domyślnej'
            ),
            self::connectionCheck(
                'DNS',
                $isFresh ? $device->dns_ok : null,
                $isFresh ? $device->last_dns_latency_ms : null,
                'Rozwiązywanie nazw domenowych'
            ),
            self::connectionCheck(
                'Internet',
                $isFresh ? $device->internet_ok : null,
                $isFresh ? $device->last_latency_ms : null,
                'Dostęp do sieci Internet'
            ),
            self::connectionCheck(
                'Serwer monitoringu',
                $isFresh ? $device->monitoring_server_ok : null,
                null,
                'Połączenie agenta z panelem'
            ),
        ])->all();

        $smartItems = $smartDisks->map(function (array $disk): array {
            [$state, $label] = self::smartDiskState($disk);
            $wearUsed = data_get($disk, 'wear_percent_used');
            $remainingLife = is_numeric($wearUsed)
                ? max(0, min(100, 100 - (float) $wearUsed))
                : null;

            return [
                'name' => (string) (
                    data_get($disk, 'friendly_name')
                    ?: data_get($disk, 'model')
                    ?: 'Dysk fizyczny'
                ),
                'model' => (string) data_get($disk, 'model', ''),
                'state' => $state,
                'state_label' => $label,
                'temperature' => is_numeric(data_get($disk, 'temperature_c'))
                    ? self::number(data_get($disk, 'temperature_c'), 1).'°C'
                    : 'Brak danych',
                'remaining_life' => $remainingLife,
                'remaining_life_display' => $remainingLife !== null
                    ? self::number($remainingLife, 1).'%'
                    : 'Brak danych',
                'power_on_hours' => is_numeric(data_get($disk, 'power_on_hours'))
                    ? self::number(data_get($disk, 'power_on_hours'), 0).' godz.'
                    : 'Brak danych',
                'health_status' => (string) (
                    data_get($disk, 'health_status') ?: 'Brak danych'
                ),
                'operational_status' => collect(
                    data_get($disk, 'operational_status', [])
                )->filter()->implode(', ') ?: 'Brak danych',
                'media_type' => (string) (
                    data_get($disk, 'media_type') ?: 'Brak danych'
                ),
                'bus_type' => (string) (
                    data_get($disk, 'bus_type') ?: 'Brak danych'
                ),
                'capacity' => self::formatBytes(data_get($disk, 'size_bytes')),
                'read_errors' => is_numeric(data_get($disk, 'read_errors_total'))
                    ? self::number(data_get($disk, 'read_errors_total'), 0)
                    : 'Brak danych',
                'write_errors' => is_numeric(data_get($disk, 'write_errors_total'))
                    ? self::number(data_get($disk, 'write_errors_total'), 0)
                    : 'Brak danych',
                'smart_supported' => (bool) data_get($disk, 'smart_supported', false),
                'message' => (string) data_get(
                    $disk,
                    'status_message',
                    'Brak dodatkowych informacji.'
                ),
            ];
        })->all();

        $systemDiskItems = $systemDisks->map(function (array $disk): array {
            $usage = data_get($disk, 'usage_percent');

            return [
                'name' => (string) data_get($disk, 'name', 'Dysk'),
                'label' => (string) data_get($disk, 'label', ''),
                'filesystem' => (string) (
                    data_get($disk, 'filesystem') ?: 'Brak danych'
                ),
                'usage' => is_numeric($usage) ? (float) $usage : null,
                'usage_display' => is_numeric($usage)
                    ? self::number($usage, 1).'%'
                    : 'Brak danych',
                'state' => self::usageState($usage, 80, 92),
                'used' => self::formatBytes(data_get($disk, 'used_bytes')),
                'free' => self::formatBytes(data_get($disk, 'free_bytes')),
                'total' => self::formatBytes(data_get($disk, 'total_bytes')),
            ];
        })->all();

        $serviceItems = $services->map(function (array $service): array {
            $healthy = (bool) data_get($service, 'healthy', false);
            $exists = (bool) data_get($service, 'exists', false);
            $alert = (bool) data_get($service, 'alert', false);
            $status = (string) data_get($service, 'status', 'Unknown');

            return [
                'label' => (string) data_get(
                    $service,
                    'label',
                    data_get($service, 'name', 'Usługa')
                ),
                'name' => (string) data_get($service, 'name', '-'),
                'display_name' => (string) (
                    data_get($service, 'display_name') ?: '-'
                ),
                'status' => $status,
                'status_label' => self::serviceStatusLabel($status),
                'state' => $healthy ? 'healthy' : ($alert ? 'critical' : 'warning'),
                'healthy' => $healthy,
                'exists' => $exists,
                'alert' => $alert,
                'start_type' => (string) (
                    data_get($service, 'start_type') ?: '-'
                ),
                'expected_status' => (string) data_get(
                    $service,
                    'expected_status',
                    'Running'
                ),
                'message' => ! $exists
                    ? 'Usługa nie została znaleziona na tym komputerze.'
                    : (! $healthy
                        ? 'Aktualny stan różni się od stanu oczekiwanego.'
                        : 'Usługa działa zgodnie z konfiguracją.'),
            ];
        })->all();

        $historyItems = $heartbeats->take(30)->map(function (Heartbeat $heartbeat): array {
            $diagnostic = $heartbeat->diagnostic_status
                ?: ($heartbeat->status === 'online' ? 'online' : 'internet_problem');

            $testDetails = collect(data_get($heartbeat->payload, 'test_details', []))
                ->filter(fn ($detail): bool => is_array($detail))
                ->values();

            $successfulProbes = $testDetails
                ->filter(fn (array $detail): bool => (bool) data_get($detail, 'ok', false))
                ->count();

            $failedProbes = $testDetails
                ->reject(fn (array $detail): bool => (bool) data_get($detail, 'ok', false))
                ->map(function (array $detail): string {
                    $url = (string) data_get($detail, 'url', '');
                    $host = parse_url($url, PHP_URL_HOST) ?: $url ?: 'nieznana sonda';
                    $error = trim((string) data_get($detail, 'error', ''));

                    return $error !== ''
                        ? $host.': '.Str::limit($error, 140)
                        : $host.': brak odpowiedzi';
                })
                ->values()
                ->all();

            return [
                'time' => self::dateTime($heartbeat->checked_at ?: $heartbeat->created_at),
                'diagnostic' => self::diagnosticLabel($diagnostic),
                'state' => self::diagnosticState($diagnostic),
                'gateway' => self::booleanStatus($heartbeat->gateway_ok),
                'dns' => self::booleanStatus($heartbeat->dns_ok),
                'internet' => self::booleanStatus($heartbeat->internet_ok),
                'monitoring' => self::booleanStatus($heartbeat->monitoring_server_ok),
                'latency' => $heartbeat->latency_ms !== null
                    ? self::number($heartbeat->latency_ms, 0).' ms'
                    : '—',
                'dns_latency' => $heartbeat->dns_latency_ms !== null
                    ? self::number($heartbeat->dns_latency_ms, 0).' ms'
                    : '—',
                'is_replayed' => (bool) $heartbeat->is_replayed,
                'delivery_label' => $heartbeat->is_replayed
                    ? 'Dostarczony z kolejki po '.self::duration($heartbeat->queue_delay_seconds)
                    : 'Bieżący',
                'probe_summary' => $testDetails->isNotEmpty()
                    ? 'Sondy: '.$successfulProbes.'/'.$testDetails->count().' OK'
                    : 'Brak szczegółów sond',
                'probe_failures' => $failedProbes,
            ];
        })->all();

        $incidentItems = $incidents->take(30)->map(function (Incident $incident): array {
            $open = in_array($incident->status, \App\Models\Incident::ACTIVE_STATUSES, true);

            return [
                'title' => self::incidentLabel($incident->type),
                'summary' => (string) (
                    $incident->summary ?: 'Brak dodatkowego opisu.'
                ),
                'status' => $open ? 'Aktywny' : 'Zakończony',
                'state' => $open ? 'critical' : 'healthy',
                'started_at' => self::dateTime($incident->started_at),
                'ended_at' => self::dateTime($incident->ended_at),
                'duration' => self::duration($incident->duration_seconds),
            ];
        })->all();

        return [
            'meta' => [
                'status' => $isFresh ? (string) $device->status : 'offline',
                'status_label' => $isFresh ? self::deviceStatusLabel($device) : 'Brak komunikacji',
                'status_state' => $isFresh ? self::deviceState($device) : 'critical',
                'diagnostic_label' => $isFresh
                    ? self::diagnosticLabel(
                        $device->diagnostic_status
                            ?: ($device->status === 'online' ? 'online' : '')
                    )
                    : (string) data_get($freshness, 'label', 'Brak bieżącej komunikacji'),
                'telemetry_fresh' => $isFresh,
                'freshness_state' => (string) data_get($freshness, 'state', 'stale'),
                'freshness_label' => (string) data_get($freshness, 'label', 'Brak bieżących danych'),
                'freshness_message' => (string) data_get($freshness, 'description', 'Brak bieżących danych telemetrycznych.'),
                'freshness_threshold_minutes' => (int) data_get($freshness, 'threshold_minutes', 3),
                'last_seen' => self::dateTime($device->last_seen_at),
                'last_seen_relative' => $device->last_seen_at
                    ? $device->last_seen_at->diffForHumans()
                    : 'brak danych',
                'measurement_at' => $latestHeartbeat
                    ? self::dateTime(
                        $latestHeartbeat->checked_at ?: $latestHeartbeat->created_at
                    )
                    : 'Brak danych',
                'agent_version' => $device->agent_version ?: 'Brak danych',
                'ip_address' => (string) (
                    data_get($network, 'ipv4_address')
                    ?: $device->last_ip
                    ?: 'Brak danych'
                ),
                'active_incidents' => $incidents->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)->count(),
                'incidents_30d' => $incidents->count(),
                'heartbeats_loaded' => $heartbeats->count(),
            ],
            'health' => [
                'value' => $healthValue,
                'state' => $isFresh ? self::healthStatusToMetricState($healthStatus) : 'unknown',
                'label' => (string) data_get($healthScore, 'label', 'Brak danych'),
                'summary' => (string) data_get($healthScore, 'summary', ''),
                'confidence' => (int) data_get($healthScore, 'confidence', 0),
                'factors' => collect(data_get($healthScore, 'factors', []))
                    ->filter(fn ($factor): bool => is_array($factor))
                    ->map(function (array $factor): array {
                        $max = (float) data_get($factor, 'max', 0);
                        $score = (float) data_get($factor, 'score', 0);

                        return [
                            'label' => (string) data_get($factor, 'label', 'Składnik'),
                            'value' => (string) data_get($factor, 'value', 'Brak danych'),
                            'description' => (string) data_get($factor, 'description', ''),
                            'state' => self::factorStateToMetricState(
                                (string) data_get($factor, 'state', 'unknown')
                            ),
                            'available' => (bool) data_get($factor, 'available', false),
                            'percent' => $max > 0
                                ? (int) round(($score / $max) * 100)
                                : 0,
                        ];
                    })->values()->all(),
                'recommendations' => array_values(
                    array_filter((array) data_get($healthScore, 'recommendations', []))
                ),
            ],
            'metrics' => $metrics,
            'connection' => [
                'current' => $isFresh,
                'checks' => $connectionChecks,
                'availability' => $internetAvailability,
                'availability_display' => $internetAvailability !== null
                    ? self::number($internetAvailability, 1).'%'
                    : 'Brak danych',
                'average_latency' => $latencySeries->isNotEmpty()
                    ? self::number($latencySeries->average(), 0).' ms'
                    : 'Brak danych',
                'minimum_latency' => $latencySeries->isNotEmpty()
                    ? self::number($latencySeries->min(), 0).' ms'
                    : 'Brak danych',
                'maximum_latency' => $latencySeries->isNotEmpty()
                    ? self::number($latencySeries->max(), 0).' ms'
                    : 'Brak danych',
                'latency_sparkline' => self::sparkline($latencySeries),
            ],
            'system' => [
                'current' => $isFresh,
                'available' => $isFresh && $systemInfo !== [],
                'computer_name' => (string) (
                    data_get($systemInfo, 'computer_name') ?: 'Brak danych'
                ),
                'os_caption' => (string) (
                    data_get($systemInfo, 'os_caption') ?: 'Brak danych'
                ),
                'os_version' => (string) (
                    data_get($systemInfo, 'os_version') ?: 'Brak danych'
                ),
                'uptime' => self::formatUptime(data_get($systemInfo, 'uptime_seconds')),
                'cpu' => [
                    'model' => (string) (
                        data_get($cpu, 'model') ?: 'Brak danych'
                    ),
                    'cores' => data_get($cpu, 'cores', 'Brak danych'),
                    'logical_processors' => data_get(
                        $cpu,
                        'logical_processors',
                        'Brak danych'
                    ),
                ],
                'memory' => [
                    'used' => self::formatBytes(data_get($memory, 'used_bytes')),
                    'free' => self::formatBytes(data_get($memory, 'free_bytes')),
                    'total' => self::formatBytes(data_get($memory, 'total_bytes')),
                ],
                'disks' => $systemDiskItems,
            ],
            'smart' => [
                'current' => $isFresh,
                'available' => $isFresh && $smartDisks->isNotEmpty(),
                'count' => $smartDisks->count(),
                'healthy' => collect($smartItems)->where('state', 'healthy')->count(),
                'attention' => collect($smartItems)
                    ->whereIn('state', ['warning', 'critical'])
                    ->count(),
                'items' => $smartItems,
            ],
            'services' => [
                'current' => $isFresh,
                'available' => $isFresh && $services->isNotEmpty(),
                'count' => $services->count(),
                'healthy' => $services->where('healthy', true)->count(),
                'attention' => $services->where('healthy', false)->count(),
                'items' => $serviceItems,
            ],
            'network' => [
                'current' => $isFresh,
                'data_label' => $isFresh ? 'Dane bieżące' : 'Ostatnia znana konfiguracja',
                'connection_type' => self::connectionTypeLabel(
                    data_get($network, 'connection_type')
                ),
                'ipv4_address' => (string) (
                    data_get($network, 'ipv4_address') ?: 'Brak danych'
                ),
                'ipv4_prefix' => is_numeric(data_get($network, 'ipv4_prefix_length'))
                    ? '/'.data_get($network, 'ipv4_prefix_length')
                    : 'Brak danych',
                'gateway' => (string) (
                    data_get($network, 'default_gateway') ?: 'Brak danych'
                ),
                'dns_servers' => collect(data_get($network, 'dns_servers', []))
                    ->filter()->implode(', ') ?: 'Brak danych',
                'dhcp' => self::yesNo(data_get($network, 'dhcp_enabled')),
                'mtu' => data_get($network, 'mtu', 'Brak danych'),
                'ipv6_addresses' => collect(
                    data_get($network, 'ipv6_addresses', [])
                )->filter()->implode(', ') ?: 'Brak danych',
                'interface_alias' => (string) (
                    data_get($network, 'interface_alias') ?: 'Brak danych'
                ),
                'adapter_status' => (string) (
                    data_get($network, 'adapter_status') ?: 'Brak danych'
                ),
                'description' => (string) (
                    data_get($network, 'interface_description') ?: 'Brak danych'
                ),
                'link_speed' => (string) (
                    data_get($network, 'link_speed') ?: 'Brak danych'
                ),
                'mac_address' => (string) (
                    data_get($network, 'mac_address') ?: 'Brak danych'
                ),
                'manufacturer' => (string) (
                    data_get($network, 'manufacturer') ?: 'Brak danych'
                ),
                'driver_version' => (string) (
                    data_get($network, 'driver_version') ?: 'Brak danych'
                ),
                'driver_date' => (string) (
                    data_get($network, 'driver_date') ?: 'Brak danych'
                ),
                'received' => self::formatBytes(data_get($network, 'received_bytes')),
                'sent' => self::formatBytes(data_get($network, 'sent_bytes')),
                'received_errors' => data_get($network, 'received_errors', 'Brak danych'),
                'sent_errors' => data_get($network, 'sent_errors', 'Brak danych'),
                'received_discards' => data_get($network, 'received_discards', 'Brak danych'),
                'sent_discards' => data_get($network, 'sent_discards', 'Brak danych'),
                'wifi' => [
                    'available' => $wifi !== []
                        || data_get($network, 'connection_type') === 'wifi',
                    'ssid' => (string) (data_get($wifi, 'ssid') ?: 'Brak danych'),
                    'signal' => is_numeric(data_get($wifi, 'signal_percent'))
                        ? self::number(data_get($wifi, 'signal_percent'), 0).'%'
                        : 'Brak danych',
                    'radio_type' => (string) (
                        data_get($wifi, 'radio_type') ?: 'Brak danych'
                    ),
                    'channel' => data_get($wifi, 'channel', 'Brak danych'),
                    'receive_rate' => is_numeric(data_get($wifi, 'receive_rate_mbps'))
                        ? self::number(data_get($wifi, 'receive_rate_mbps'), 0).' Mb/s'
                        : 'Brak danych',
                    'transmit_rate' => is_numeric(data_get($wifi, 'transmit_rate_mbps'))
                        ? self::number(data_get($wifi, 'transmit_rate_mbps'), 0).' Mb/s'
                        : 'Brak danych',
                    'bssid' => (string) (data_get($wifi, 'bssid') ?: 'Brak danych'),
                ],
            ],
            'history' => $historyItems,
            'incidents' => $incidentItems,
        ];
    }

    /**
     * @param Collection<int, Heartbeat> $heartbeats
     * @return Collection<int, float>
     */
    private static function payloadSeries(Collection $heartbeats, string $path): Collection
    {
        return $heartbeats
            ->map(fn (Heartbeat $heartbeat) => data_get($heartbeat->payload, $path))
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): float => (float) $value)
            ->values();
    }

    /**
     * @param Collection<int, float|int> $series
     * @return array<string, mixed>
     */
    private static function metric(
        string $label,
        mixed $value,
        string $suffix,
        float $warning,
        float $critical,
        Collection $series,
        bool $lowerIsBetter,
        string $note
    ): array {
        return [
            'label' => $label,
            'value' => is_numeric($value) ? (float) $value : null,
            'display' => is_numeric($value)
                ? self::number($value, 1).$suffix
                : 'Brak danych',
            'state' => self::usageState($value, $warning, $critical),
            'trend' => self::numericTrend($series, $lowerIsBetter),
            'sparkline' => self::sparkline($series),
            'note' => $note,
        ];
    }

    /**
     * @param Collection<int, float|int> $values Newest values first.
     * @return array{direction:string,label:string,tone:string}
     */
    private static function numericTrend(
        Collection $values,
        bool $lowerIsBetter
    ): array {
        $current = $values->take(5);
        $previous = $values->slice(5, 5);

        if ($current->isEmpty() || $previous->isEmpty()) {
            return [
                'direction' => 'flat',
                'label' => 'brak porównania',
                'tone' => 'neutral',
            ];
        }

        $difference = (float) $current->average() - (float) $previous->average();

        if (abs($difference) < 0.5) {
            return [
                'direction' => 'flat',
                'label' => 'bez zmian',
                'tone' => 'neutral',
            ];
        }

        $improved = $lowerIsBetter ? $difference < 0 : $difference > 0;

        return [
            'direction' => $difference > 0 ? 'up' : 'down',
            'label' => self::number(abs($difference), 1).' pp',
            'tone' => $improved ? 'good' : 'bad',
        ];
    }

    /**
     * @param Collection<int, mixed> $values Newest values first.
     * @return array{direction:string,label:string,tone:string}
     */
    private static function booleanTrend(Collection $values): array
    {
        $current = self::booleanPercent($values->take(20));
        $previous = self::booleanPercent($values->slice(20, 20));

        if ($current === null || $previous === null) {
            return [
                'direction' => 'flat',
                'label' => 'brak porównania',
                'tone' => 'neutral',
            ];
        }

        $difference = $current - $previous;

        if (abs($difference) < 0.5) {
            return [
                'direction' => 'flat',
                'label' => 'bez zmian',
                'tone' => 'neutral',
            ];
        }

        return [
            'direction' => $difference > 0 ? 'up' : 'down',
            'label' => self::number(abs($difference), 1).' pp',
            'tone' => $difference > 0 ? 'good' : 'bad',
        ];
    }

    /**
     * @param Collection<int, mixed> $values
     */
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

    /**
     * @param Collection<int, float|int>|array<int, float|int> $values
     * @return array{line:string,fill:string}
     */
    private static function sparkline(Collection|array $values): array
    {
        $values = collect($values)
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

    /** @return array<string, mixed> */
    private static function connectionCheck(
        string $label,
        ?bool $value,
        mixed $latency,
        string $description
    ): array {
        return [
            'label' => $label,
            'state' => $value === null ? 'unknown' : ($value ? 'healthy' : 'critical'),
            'status' => $value === null ? 'Brak danych' : ($value ? 'Prawidłowo' : 'Błąd'),
            'detail' => is_numeric($latency)
                ? self::number($latency, 0).' ms'
                : $description,
        ];
    }

    private static function usageState(
        mixed $value,
        float $warning,
        float $critical
    ): string {
        if (! is_numeric($value)) {
            return 'unknown';
        }

        return match (true) {
            (float) $value >= $critical => 'critical',
            (float) $value >= $warning => 'warning',
            default => 'healthy',
        };
    }

    /** @return array{0:string,1:string} */
    private static function smartDiskState(array $disk): array
    {
        $health = mb_strtolower(trim((string) data_get($disk, 'health_status', '')));
        $predictFailure = (bool) data_get($disk, 'predict_failure', false);
        $supported = (bool) data_get($disk, 'smart_supported', false);
        $temperature = data_get($disk, 'temperature_c');
        $wear = data_get($disk, 'wear_percent_used');

        if (
            $predictFailure
            || ! in_array($health, ['', 'healthy', 'ok', 'unknown'], true)
            || (is_numeric($temperature) && (float) $temperature >= 65)
            || (is_numeric($wear) && (float) $wear >= 95)
        ) {
            return ['critical', 'Krytyczny'];
        }

        if (
            (is_numeric($temperature) && (float) $temperature >= 55)
            || (is_numeric($wear) && (float) $wear >= 80)
        ) {
            return ['warning', 'Ostrzeżenie'];
        }

        if (! $supported) {
            return ['unknown', 'Dane ograniczone'];
        }

        return ['healthy', 'Dobry'];
    }

    private static function healthStatusToMetricState(string $status): string
    {
        return match ($status) {
            'excellent', 'good' => 'healthy',
            'warning' => 'warning',
            'critical' => 'critical',
            default => 'unknown',
        };
    }

    private static function factorStateToMetricState(string $state): string
    {
        return match ($state) {
            'healthy' => 'healthy',
            'warning' => 'warning',
            'critical' => 'critical',
            default => 'unknown',
        };
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

    private static function deviceStatusLabel(Device $device): string
    {
        return match ($device->status) {
            'online' => 'Online',
            'problem' => 'Wymaga uwagi',
            'offline' => 'Offline',
            default => 'Brak danych',
        };
    }

    private static function diagnosticState(string $diagnostic): string
    {
        return match ($diagnostic) {
            'online' => 'healthy',
            'gateway_problem', 'dns_problem', 'internet_problem',
            'monitoring_server_problem' => 'critical',
            default => 'unknown',
        };
    }

    private static function diagnosticLabel(string $diagnostic): string
    {
        return match ($diagnostic) {
            'online' => 'Połączenie prawidłowe',
            'gateway_problem' => 'Problem z routerem lub bramą',
            'dns_problem' => 'Problem z DNS',
            'internet_problem' => 'Problem z Internetem',
            'monitoring_server_problem' => 'Problem z serwerem monitoringu',
            default => 'Brak bieżącej diagnozy',
        };
    }

    /** @return array{label:string,state:string} */
    private static function booleanStatus(?bool $value): array
    {
        return [
            'label' => $value === null ? '—' : ($value ? 'OK' : 'Błąd'),
            'state' => $value === null ? 'unknown' : ($value ? 'healthy' : 'critical'),
        ];
    }

    private static function incidentLabel(string $type): string
    {
        return match ($type) {
            'no_communication' => 'Brak komunikacji z urządzeniem',
            'gateway_problem' => 'Problem z bramą lub routerem',
            'dns_problem' => 'Problem z usługą DNS',
            'internet_problem' => 'Brak dostępu do Internetu',
            'monitoring_server_problem' => 'Brak połączenia z monitoringiem',
            'windows_service_problem' => 'Problem z usługą Windows',
            'smart_disk_problem' => 'Problem z dyskiem SMART',
            default => 'Incydent monitoringu',
        };
    }

    private static function serviceStatusLabel(string $status): string
    {
        return match ($status) {
            'Running' => 'Działa',
            'Stopped' => 'Zatrzymana',
            'Paused' => 'Wstrzymana',
            'Start Pending' => 'Uruchamianie',
            'Stop Pending' => 'Zatrzymywanie',
            'Missing' => 'Brak usługi',
            default => 'Brak danych',
        };
    }

    private static function connectionTypeLabel(mixed $type): string
    {
        return match ($type) {
            'ethernet' => 'Ethernet',
            'wifi' => 'Wi-Fi',
            'vpn' => 'VPN',
            null, '' => 'Brak danych',
            default => (string) $type,
        };
    }

    private static function yesNo(mixed $value): string
    {
        return $value === null ? 'Brak danych' : ((bool) $value ? 'Tak' : 'Nie');
    }

    private static function formatBytes(mixed $bytes): string
    {
        if (! is_numeric($bytes)) {
            return 'Brak danych';
        }

        $value = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return self::number($value, $index >= 3 ? 1 : 0).' '.$units[$index];
    }

    private static function formatUptime(mixed $seconds): string
    {
        if (! is_numeric($seconds)) {
            return 'Brak danych';
        }

        $seconds = max(0, (int) $seconds);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];

        if ($days > 0) {
            $parts[] = $days.' dni';
        }

        if ($hours > 0 || $days > 0) {
            $parts[] = $hours.' godz.';
        }

        $parts[] = $minutes.' min';

        return implode(' ', $parts);
    }

    private static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return 'Trwa';
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        return $minutes > 0
            ? $minutes.' min '.$remainingSeconds.' sek.'
            : $remainingSeconds.' sek.';
    }

    private static function dateTime(mixed $date): string
    {
        return $date
            ? $date->timezone('Europe/Warsaw')->format('Y-m-d H:i:s')
            : '—';
    }

    private static function number(mixed $value, int $precision): string
    {
        return number_format((float) $value, $precision, ',', ' ');
    }
}
