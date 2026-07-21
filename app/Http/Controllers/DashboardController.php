<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Facility;
use App\Models\Heartbeat;
use App\Models\Incident;
use App\Services\AgentDiagnosticsService;
use App\Services\DeviceHealthScoreService;
use App\Support\DeviceTelemetryFreshness;
use App\Support\PlatinumDashboardFactory;
use App\Support\PlatinumDeviceDashboardFactory;
use App\Support\PlatinumFacilityDashboardFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DeviceHealthScoreService $healthScoreService
    ): View {
        $user = $request->user();
        abort_unless($user, 403);

        $facilities = Facility::query()
            ->visibleTo($user)
            ->with(['devices' => function ($query): void {
                $query->whereNull('archived_at')->orderBy('name');
            }])
            ->orderBy('code')
            ->get();

        $facilityIds = $facilities->pluck('id');

        $openIncidents = Incident::query()
            ->with(['facility', 'device'])
            ->whereIn('facility_id', $facilityIds)
            ->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)
            ->latest('started_at')
            ->limit(20)
            ->get();

        $devices = $facilities
            ->flatMap(fn (Facility $facility): Collection => $facility->devices)
            ->values();

        $deviceIds = $devices->pluck('id');

        $latestHeartbeats = collect();
        $recentIncidentsByDevice = collect();
        $recentHeartbeats = collect();

        if ($deviceIds->isNotEmpty()) {
            $latestHeartbeatIds = Heartbeat::query()
                ->selectRaw('MAX(id)')
                ->whereIn('device_id', $deviceIds)
                ->groupBy('device_id');

            $latestHeartbeats = Heartbeat::query()
                ->whereIn('id', $latestHeartbeatIds)
                ->get()
                ->keyBy('device_id');

            $recentIncidentsByDevice = Incident::query()
                ->whereIn('device_id', $deviceIds)
                ->where('started_at', '>=', now()->subDays(30))
                ->get()
                ->groupBy('device_id');

            $recentHeartbeats = Heartbeat::query()
                ->whereIn('device_id', $deviceIds)
                ->latest('checked_at')
                ->latest('id')
                ->limit(120)
                ->get();
        }

        $healthByDevice = collect();
        $confidenceByDevice = collect();
        $smartHealthy = 0;
        $smartWarnings = 0;
        $smartTotal = 0;
        $windowsReporting = 0;
        $servicesActive = 0;
        $servicesTotal = 0;
        $freshDeviceIds = collect();
        $effectiveStatusByDevice = collect();

        $dashboardDevices = $devices->map(function (Device $device) use (
            $healthScoreService,
            $latestHeartbeats,
            $recentIncidentsByDevice,
            $facilities,
            &$healthByDevice,
            &$confidenceByDevice,
            &$smartHealthy,
            &$smartWarnings,
            &$smartTotal,
            &$windowsReporting,
            &$servicesActive,
            &$servicesTotal,
            &$freshDeviceIds,
            &$effectiveStatusByDevice
        ): array {
            $latestHeartbeat = $latestHeartbeats->get($device->id);
            $freshness = DeviceTelemetryFreshness::describe($device, $latestHeartbeat);
            $isFresh = (bool) $freshness['is_fresh'];
            $deviceIncidents = $recentIncidentsByDevice->get($device->id, collect());
            $health = $isFresh
                ? $healthScoreService->calculate($device, $latestHeartbeat, $deviceIncidents)
                : DeviceTelemetryFreshness::unavailableHealthScore($freshness);

            $effectiveStatus = match (true) {
                $device->archived_at !== null || ! $device->is_active => 'unknown',
                ! $isFresh => 'offline',
                default => (string) $device->status,
            };
            $effectiveStatusByDevice->put($device->id, $effectiveStatus);

            if ($isFresh) {
                $freshDeviceIds->push($device->id);
                $healthByDevice->put($device->id, (int) $health['score']);
                $confidenceByDevice->put($device->id, (int) $health['confidence']);
            }

            $payload = $isFresh && is_array($latestHeartbeat?->payload)
                ? $latestHeartbeat->payload
                : [];

            $smartDisks = collect(data_get($payload, 'smart_info.disks', []))
                ->filter(fn ($disk): bool => is_array($disk));

            foreach ($smartDisks as $disk) {
                $smartTotal++;

                if ($this->smartDiskRequiresAttention($disk)) {
                    $smartWarnings++;
                } else {
                    $smartHealthy++;
                }
            }

            $systemInfo = data_get($payload, 'system_info', []);

            if (is_array($systemInfo) && $systemInfo !== []) {
                $windowsReporting++;
            }

            $windowsServices = collect(data_get($payload, 'windows_services', []))
                ->filter(fn ($service): bool => is_array($service));

            $servicesTotal += $windowsServices->count();
            $servicesActive += $windowsServices->where('healthy', true)->count();

            $facility = $facilities->firstWhere('id', $device->facility_id);
            $networkAddress = data_get($device->network_info, 'ipv4_address')
                ?: $device->last_ip
                ?: 'Brak adresu IP';
            $osCaption = data_get($systemInfo, 'os_caption') ?: 'Windows — brak danych';

            return [
                'name' => $device->name,
                'details' => trim(($facility?->code ?: 'Placówka').' · '.$networkAddress.' · '.$osCaption),
                'status' => $isFresh ? $this->deviceStatusLabel($device) : 'Brak komunikacji',
                'tone' => $isFresh ? $this->deviceTone($device) : 'danger',
                'message' => $isFresh ? $this->deviceStatusMessage($device) : (string) $freshness['description'],
                'health_score' => data_get($health, 'score'),
                'confidence' => (int) data_get($health, 'confidence', 0),
                'last_seen' => $device->last_seen_at
                    ? $device->last_seen_at->diffForHumans()
                    : 'brak danych',
                'url' => route('devices.heartbeats', ['device' => $device->id]),
                'sort' => $isFresh ? $this->deviceSortPriority($device) : 5,
            ];
        })
            ->sortBy('sort')
            ->values()
            ->take(12)
            ->map(function (array $device): array {
                unset($device['sort']);

                return $device;
            })
            ->all();

        $globalHealthScore = $healthByDevice->isNotEmpty()
            ? (int) round($healthByDevice->average())
            : 0;

        $globalConfidence = $confidenceByDevice->isNotEmpty()
            ? (int) round($confidenceByDevice->average())
            : 0;

        $freshDevices = $devices
            ->filter(fn (Device $device): bool => $freshDeviceIds->contains($device->id))
            ->values();

        $latencies = $freshDevices
            ->pluck('last_latency_ms')
            ->filter(fn ($value): bool => is_numeric($value));

        $averageResponse = $latencies->isNotEmpty()
            ? (int) round($latencies->average())
            : null;

        $internetReporting = $freshDevices
            ->filter(fn (Device $device): bool => $device->internet_ok !== null);

        $internetAvailability = $internetReporting->isNotEmpty()
            ? round(
                ($internetReporting->where('internet_ok', true)->count()
                    / $internetReporting->count()) * 100,
                1
            )
            : null;

        $stats = [
            'facilities' => $facilities->count(),
            'devices' => $devices->count(),
            'online' => $effectiveStatusByDevice->filter(fn (string $status): bool => $status === 'online')->count(),
            'problem' => $effectiveStatusByDevice->filter(fn (string $status): bool => $status === 'problem')->count(),
            'offline' => $effectiveStatusByDevice->filter(fn (string $status): bool => $status === 'offline')->count(),
            'unknown' => $effectiveStatusByDevice->filter(fn (string $status): bool => $status === 'unknown')->count(),
            'open_incidents' => Incident::query()
                ->whereIn('facility_id', $facilityIds)
                ->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)
                ->count(),
            'archived_devices' => Device::query()
                ->whereIn('facility_id', $facilityIds)
                ->whereNotNull('archived_at')
                ->count(),
        ];

        $incidentCurrent24h = Incident::query()
            ->whereIn('facility_id', $facilityIds)
            ->where('started_at', '>=', now()->subDay())
            ->count();

        $incidentPrevious24h = Incident::query()
            ->whereIn('facility_id', $facilityIds)
            ->whereBetween('started_at', [now()->subDays(2), now()->subDay()])
            ->count();

        [$responseTrend, $responseTrendTone] = $this->metricTrend(
            $recentHeartbeats->take(30)->pluck('latency_ms')->filter()->average(),
            $recentHeartbeats->slice(30, 30)->pluck('latency_ms')->filter()->average(),
            true,
            'ms'
        );

        [$internetTrend, $internetTrendTone] = $this->metricTrend(
            $this->booleanPercent($recentHeartbeats->take(30)->pluck('internet_ok')),
            $this->booleanPercent($recentHeartbeats->slice(30, 30)->pluck('internet_ok')),
            false,
            'pp'
        );

        [$incidentTrend, $incidentTrendTone] = $this->metricTrend(
            $incidentCurrent24h,
            $incidentPrevious24h,
            true,
            ''
        );

        if ($freshDevices->isEmpty()) {
            $responseTrend = '▬ brak bieżących danych';
            $responseTrendTone = 'neutral';
            $internetTrend = '▬ brak bieżących danych';
            $internetTrendTone = 'neutral';
        }

        $facilityCards = $facilities->map(function (Facility $facility) use ($healthByDevice, $effectiveStatusByDevice, $user): array {
            $facilityDevices = $facility->devices;
            $scores = $facilityDevices
                ->pluck('id')
                ->map(fn ($id) => $healthByDevice->get($id))
                ->filter(fn ($score): bool => $score !== null);
            $facilityStatuses = $facilityDevices
                ->pluck('id')
                ->map(fn ($id) => $effectiveStatusByDevice->get($id, 'unknown'));

            return [
                'code' => $facility->code,
                'name' => $facility->name,
                'active' => (bool) $facility->is_active,
                'devices' => $facilityDevices->count(),
                'online' => $facilityStatuses->filter(fn (string $status): bool => $status === 'online')->count(),
                'problem' => $facilityStatuses->filter(fn (string $status): bool => $status === 'problem')->count(),
                'offline' => $facilityStatuses->filter(fn (string $status): bool => $status === 'offline')->count(),
                'health_score' => $scores->isNotEmpty()
                    ? (int) round($scores->average())
                    : null,
                'details_url' => route('facilities.show', ['facility' => $facility->id]),
                'manage_url' => $user->isAdmin()
                    ? route('facilities.manage', ['facility' => $facility->id])
                    : null,
                'report_url' => route('reports.facility', ['facility' => $facility->id]),
            ];
        })->all();

        $dashboardIncidents = $openIncidents->map(function (Incident $incident): array {
            $critical = in_array($incident->type, [
                'no_communication',
                'smart_disk_problem',
            ], true);

            return [
                'title' => $this->incidentTitle($incident->type),
                'description' => $incident->summary
                    ?: trim(($incident->facility?->code ?: 'Placówka')
                        .' · '
                        .($incident->device?->name ?: 'Urządzenie')),
                'priority' => $critical ? 'krytyczny' : 'wysoki',
                'duration' => $incident->started_at
                    ? 'od '.$incident->started_at->diffForHumans()
                    : '',
                'tone' => $critical ? 'danger' : 'warning',
                'symbol' => $critical ? '!' : '▲',
                'url' => $incident->device_id
                    ? route('devices.heartbeats', ['device' => $incident->device_id])
                    : route('monitoring-center.index'),
            ];
        })->all();

        $responseSparkline = $this->sparkline(
            $freshDevices->isEmpty()
                ? []
                : $recentHeartbeats
                    ->whereIn('device_id', $freshDeviceIds)
                    ->take(20)
                    ->reverse()
                    ->pluck('latency_ms')
                    ->filter(fn ($value): bool => is_numeric($value))
                    ->map(fn ($value): float => (float) $value)
                    ->values()
                    ->all()
        );

        $internetSparkline = $this->sparkline(
            $freshDevices->isEmpty()
                ? []
                : $recentHeartbeats
                    ->whereIn('device_id', $freshDeviceIds)
                    ->take(20)
                    ->reverse()
                    ->pluck('internet_ok')
                    ->filter(fn ($value): bool => $value !== null)
                    ->map(fn ($value): float => $value ? 100.0 : 0.0)
                    ->values()
                    ->all()
        );

        $latestAgentVersion = $devices
            ->pluck('agent_version')
            ->filter()
            ->sort()
            ->last();

        $dashboard = PlatinumDashboardFactory::make([
            'page_title' => 'Panel główny — Placówka Online',
            'institution_name' => 'Placówka Online',
            'institution_description' => 'Zbiorczy stan placówek, urządzeń, Internetu, komputerów, dysków SMART i usług Windows.',
            'health_score' => $healthByDevice->isNotEmpty() ? $globalHealthScore : null,
            'health_tone' => $healthByDevice->isEmpty() ? 'danger' : null,
            'health_label' => $devices->isEmpty()
                ? 'Brak urządzeń do oceny'
                : ($healthByDevice->isEmpty() ? 'Brak bieżących danych do oceny' : null),
            'reliability' => $healthByDevice->isEmpty() ? '0% — brak komunikacji' : $this->confidenceLabel($globalConfidence),
            'devices_online' => $stats['online'],
            'devices_total' => $stats['devices'],
            'warnings' => $stats['problem'] + $stats['unknown'],
            'failures' => $stats['offline'],
            'active_incidents' => $stats['open_incidents'],
            'critical_incidents' => $openIncidents
                ->whereIn('type', ['no_communication', 'smart_disk_problem'])
                ->count(),
            'response_ms' => $averageResponse ?? 0,
            'response_value' => $averageResponse ?? '—',
            'response_note' => $averageResponse === null
                ? 'Brak bieżących pomiarów opóźnienia'
                : 'Średnia z ostatnich raportów urządzeń',
            'internet_uptime' => $internetAvailability ?? 0,
            'internet_value' => $internetAvailability === null
                ? '—'
                : number_format($internetAvailability, $internetAvailability == floor($internetAvailability) ? 0 : 1, ',', ' '),
            'internet_note' => $internetAvailability === null
                ? 'Brak bieżących danych — agenci nie komunikują się'
                : $internetReporting->count().' z '.$devices->where('is_active', true)->count().' aktywnych urządzeń raportuje',
            'smart_healthy' => $smartHealthy,
            'smart_warnings' => $smartWarnings,
            'smart_note' => $smartTotal > 0
                ? $smartWarnings.' wymaga uwagi z '.$smartTotal
                : 'Brak bieżących danych SMART',
            'smart_tone' => $smartTotal === 0 ? 'neutral' : ($smartWarnings > 0 ? 'warning' : 'success'),
            'smart_value' => $smartTotal === 0 ? '—' : $smartHealthy,
            'smart_suffix' => $smartTotal === 0 ? '' : 'sprawne',
            'windows_hosts' => $freshDevices->count(),
            'windows_reporting' => $windowsReporting,
            'windows_note' => $freshDevices->isEmpty()
                ? 'Brak bieżących danych Windows'
                : $windowsReporting.' z '.$freshDevices->count().' bieżących raportów zawiera dane systemowe',
            'windows_tone' => $freshDevices->isEmpty() ? 'neutral' : ($windowsReporting === $freshDevices->count() ? 'success' : 'warning'),
            'windows_value' => $freshDevices->isEmpty() ? '—' : $windowsReporting,
            'windows_suffix' => $freshDevices->isEmpty() ? '' : 'raportuje',
            'services_active' => $servicesActive,
            'services_total' => $servicesTotal,
            'services_note' => $servicesTotal > 0
                ? $servicesActive.' z '.$servicesTotal.' działa prawidłowo'
                : 'Brak bieżących danych o usługach',
            'services_tone' => $servicesTotal === 0 ? 'neutral' : ($servicesActive === $servicesTotal ? 'success' : 'warning'),
            'services_value' => $servicesTotal === 0 ? '—' : $servicesActive,
            'services_suffix' => $servicesTotal === 0 ? '' : 'aktywne',
            'system_status' => $devices->isEmpty()
                ? 'Brak urządzeń do monitorowania'
                : ($freshDevices->isEmpty() ? 'Brak bieżącej komunikacji z agentami' : null),
            'system_tone' => $devices->isEmpty() ? 'warning' : ($freshDevices->isEmpty() ? 'danger' : null),
            'last_refresh' => 'o '.now()->timezone('Europe/Warsaw')->format('H:i:s'),
            'agent_version' => $latestAgentVersion ?: 'brak danych',
            'agent_message' => $latestAgentVersion
                ? 'Najnowsza wykryta wersja w aktywnych urządzeniach.'
                : 'Agenci nie przesłali jeszcze informacji o wersji.',
            'user_name' => $user->name,
            'user_initials' => $user->initials(),
            'health_trend' => '▬ wynik bieżący',
            'devices_trend' => '▬ stan bieżący',
            'incidents_trend' => $incidentTrend,
            'incidents_trend_tone' => $incidentTrendTone,
            'response_trend' => $responseTrend,
            'response_trend_tone' => $responseTrendTone,
            'internet_trend' => $internetTrend,
            'internet_trend_tone' => $internetTrendTone,
            'smart_trend' => $smartTotal > 0 ? '▬ bieżący pomiar' : '▬ brak bieżących danych',
            'windows_trend' => $freshDevices->isNotEmpty() ? '▬ bieżący pomiar' : '▬ brak bieżących danych',
            'services_trend' => $servicesTotal > 0 ? '▬ bieżący pomiar' : '▬ brak bieżących danych',
            'response_sparkline_fill' => $responseSparkline['fill'],
            'response_sparkline_line' => $responseSparkline['line'],
            'internet_sparkline_fill' => $internetSparkline['fill'],
            'internet_sparkline_line' => $internetSparkline['line'],
            'devices' => $dashboardDevices,
            'incidents' => $dashboardIncidents,
            'links' => [
                'dashboard' => route('dashboard'),
                'devices' => '#devices',
                'incidents' => route('monitoring-center.index').'#incidents',
                'history' => route('reports.index'),
                'settings' => $user->isAdmin() ? route('system-settings.edit') : route('account.edit'),
                'users' => $user->isAdmin() ? route('operators.index') : route('account.edit'),
                'health' => $user->isAdmin() ? route('system.status') : route('monitoring-center.index'),
                'smart' => '#devices',
                'windows' => '#devices',
                'services' => $user->isAdmin() ? route('agent-windows-services.index') : route('monitoring-center.index'),
                'agents' => $user->isAdmin() ? route('system.status') : route('dashboard'),
                'export' => route('reports.index'),
                'facilities' => '#facilities',
                'monitoring' => route('monitoring-center.index'),
                'reports' => route('reports.index'),
                'status' => $user->isAdmin() ? route('system.status') : route('monitoring-center.index'),
                'add_facility' => $user->isAdmin() ? route('facilities.create') : route('dashboard'),
            ],
        ]);

        $dashboard['facilities'] = $facilityCards;

        return view('dashboard', [
            'dashboard' => $dashboard,
            'facilities' => $facilities,
            'openIncidents' => $openIncidents,
            'stats' => $stats,
        ]);
    }

    public function show(
        Request $request,
        Facility $facility,
        DeviceHealthScoreService $healthScoreService
    ): View {
        $facility->load(['devices' => function ($query): void {
            $query->orderBy('name');
        }]);

        $deviceIds = $facility->devices->pluck('id');

        $heartbeats = Heartbeat::query()
            ->with('device')
            ->whereIn('device_id', $deviceIds)
            ->latest('checked_at')
            ->latest('id')
            ->limit(50)
            ->get();

        $incidents = Incident::query()
            ->with('device')
            ->where('facility_id', $facility->id)
            ->latest('started_at')
            ->limit(50)
            ->get();

        $latestHeartbeats = collect();
        $recentIncidentsByDevice = collect();

        if ($deviceIds->isNotEmpty()) {
            $latestHeartbeatIds = Heartbeat::query()
                ->selectRaw('MAX(id)')
                ->whereIn('device_id', $deviceIds)
                ->groupBy('device_id');

            $latestHeartbeats = Heartbeat::query()
                ->whereIn('id', $latestHeartbeatIds)
                ->get()
                ->keyBy('device_id');

            $recentIncidentsByDevice = Incident::query()
                ->whereIn('device_id', $deviceIds)
                ->where('started_at', '>=', now()->subDays(30))
                ->get()
                ->groupBy('device_id');
        }

        $incidentStats = [
            'last_24h' => Incident::query()
                ->where('facility_id', $facility->id)
                ->where('started_at', '>=', now()->subDay())
                ->count(),

            'last_7d' => Incident::query()
                ->where('facility_id', $facility->id)
                ->where('started_at', '>=', now()->subDays(7))
                ->count(),

            'last_30d' => Incident::query()
                ->where('facility_id', $facility->id)
                ->where('started_at', '>=', now()->subDays(30))
                ->count(),

            'open' => Incident::query()
                ->where('facility_id', $facility->id)
                ->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)
                ->count(),
        ];

        $facilityDashboard = PlatinumFacilityDashboardFactory::make(
            $facility,
            $heartbeats,
            $incidents,
            $incidentStats,
            $latestHeartbeats,
            $recentIncidentsByDevice,
            $healthScoreService
        );

        return view('facility-show', [
            'facility' => $facility,
            'heartbeats' => $heartbeats,
            'incidents' => $incidents,
            'incidentStats' => $incidentStats,
            'facilityDashboard' => $facilityDashboard,
        ]);
    }

    public function deviceHeartbeats(
        Request $request,
        Device $device,
        DeviceHealthScoreService $healthScoreService,
        AgentDiagnosticsService $agentDiagnosticsService
    ): View {
        $device->load('facility');

        $heartbeats = Heartbeat::query()
            ->where('device_id', $device->id)
            ->latest('checked_at')
            ->latest('id')
            ->limit(100)
            ->get();

        $incidents = Incident::query()
            ->where('device_id', $device->id)
            ->where('started_at', '>=', now()->subDays(30))
            ->latest('started_at')
            ->limit(100)
            ->get();

        $freshness = DeviceTelemetryFreshness::describe($device, $heartbeats->first());
        $healthScore = $freshness['is_fresh']
            ? $healthScoreService->calculate($device, $heartbeats->first(), $incidents)
            : DeviceTelemetryFreshness::unavailableHealthScore($freshness);

        $deviceDashboard = PlatinumDeviceDashboardFactory::make(
            $device,
            $heartbeats,
            $incidents,
            $healthScore,
            $freshness
        );

        return view('device-heartbeats', [
            'device' => $device,
            'heartbeats' => $heartbeats,
            'incidents' => $incidents,
            'healthScore' => $healthScore,
            'deviceDashboard' => $deviceDashboard,
            'agentDiagnostics' => $agentDiagnosticsService->describe($device, $freshness),
        ]);
    }

    private function deviceStatusLabel(Device $device): string
    {
        return match ($device->status) {
            'online' => 'Online',
            'problem' => 'Ostrzeżenie',
            'offline' => 'Awaria',
            default => 'Brak danych',
        };
    }

    private function deviceTone(Device $device): string
    {
        return match ($device->status) {
            'online' => 'success',
            'problem', 'unknown' => 'warning',
            'offline' => 'danger',
            default => 'warning',
        };
    }

    private function deviceSortPriority(Device $device): int
    {
        return match ($device->status) {
            'offline' => 10,
            'problem' => 20,
            'unknown' => 30,
            default => 40,
        };
    }

    private function deviceStatusMessage(Device $device): string
    {
        if ($device->status === 'offline') {
            return 'Brak aktualnej komunikacji z agentem.';
        }

        return match ($device->diagnostic_status) {
            'gateway_problem' => 'Problem z bramą lub routerem.',
            'dns_problem' => 'Problem z rozwiązywaniem nazw DNS.',
            'internet_problem' => 'Brak dostępu do Internetu.',
            'monitoring_server_problem' => 'Problem z połączeniem do serwera monitoringu.',
            'online' => 'Internet, DNS i połączenie z serwerem działają.',
            default => $device->status === 'online'
                ? 'Urządzenie raportuje prawidłowo.'
                : 'Sprawdź szczegóły ostatniego pomiaru.',
        };
    }

    private function incidentTitle(string $type): string
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

    private function smartDiskRequiresAttention(array $disk): bool
    {
        $health = mb_strtolower(trim((string) data_get($disk, 'health_status', '')));
        $temperature = data_get($disk, 'temperature_c');
        $wear = data_get($disk, 'wear_percent_used');

        return (bool) data_get($disk, 'predict_failure', false)
            || ! in_array($health, ['', 'healthy', 'ok', 'unknown'], true)
            || (is_numeric($temperature) && (float) $temperature >= 55)
            || (is_numeric($wear) && (float) $wear >= 80);
    }

    private function confidenceLabel(int $confidence): string
    {
        return match (true) {
            $confidence >= 85 => 'wysoka — '.$confidence.'%',
            $confidence >= 60 => 'średnia — '.$confidence.'%',
            $confidence > 0 => 'niska — '.$confidence.'%',
            default => 'brak danych',
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function metricTrend(
        mixed $current,
        mixed $previous,
        bool $lowerIsBetter,
        string $unit
    ): array {
        if (! is_numeric($current) || ! is_numeric($previous)) {
            return ['▬ brak porównania', 'neutral'];
        }

        $difference = (float) $current - (float) $previous;

        if (abs($difference) < 0.5) {
            return ['▬ bez zmian', 'neutral'];
        }

        $improved = $lowerIsBetter ? $difference < 0 : $difference > 0;
        $arrow = $difference > 0 ? '▲' : '▼';
        $formatted = number_format(abs($difference), 1, ',', ' ');

        return [
            trim($arrow.' '.$formatted.' '.$unit),
            $improved ? 'good' : 'bad',
        ];
    }

    private function booleanPercent(Collection $values): ?float
    {
        $values = $values->filter(fn ($value): bool => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return round(($values->filter(fn ($value): bool => (bool) $value)->count()
            / $values->count()) * 100, 1);
    }

    /**
     * @param array<int, float|int> $values
     * @return array{line:string,fill:string}
     */
    private function sparkline(array $values): array
    {
        if (count($values) < 2) {
            return [
                'line' => 'M0 17 L180 17',
                'fill' => 'M0 17 L180 17 L180 34 L0 34Z',
            ];
        }

        $values = array_values(array_map('floatval', $values));
        $minimum = min($values);
        $maximum = max($values);
        $range = max(1.0, $maximum - $minimum);
        $lastIndex = count($values) - 1;
        $points = [];

        foreach ($values as $index => $value) {
            $x = ($index / $lastIndex) * 180;
            $y = 30 - ((($value - $minimum) / $range) * 24);
            $points[] = number_format($x, 1, '.', '')
                .' '
                .number_format($y, 1, '.', '');
        }

        $line = 'M'.implode(' L', $points);

        return [
            'line' => $line,
            'fill' => $line.' L180 34 L0 34Z',
        ];
    }
}
