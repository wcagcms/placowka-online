<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Facility;
use App\Models\Incident;
use App\Support\DeviceTelemetryFreshness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MonitoringCenterController extends Controller
{
    public function index(Request $request): View
    {
        $data = $this->buildSnapshot($request);

        return view('monitoring-center.index', $data);
    }

    public function snapshot(Request $request): JsonResponse
    {
        $data = $this->buildSnapshot($request);

        return response()->json([
            'generated_at' => $data['generatedAt']->toIso8601String(),
            'generated_at_label' => $data['generatedAt']->timezone('Europe/Warsaw')->format('H:i:s'),
            'html' => view('monitoring-center.partials.snapshot', $data)->render(),
        ]);
    }

    /**
     * @return array{
     *   facilities: Collection<int, Facility>,
     *   openIncidents: Collection<int, Incident>,
     *   recentEvents: Collection<int, Incident>,
     *   stats: array<string, int|null>,
     *   generatedAt: \Illuminate\Support\Carbon
     * }
     */
    private function buildSnapshot(Request $request): array
    {
        $user = $request->user();
        abort_unless($user, 403);

        $facilities = Facility::query()
            ->visibleTo($user)
            ->with([
                'devices' => function ($query): void {
                    $query
                        ->whereNull('archived_at')
                        ->orderBy('name');
                },
                'incidents' => function ($query): void {
                    $query
                        ->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)
                        ->with('device')
                        ->latest('started_at');
                },
            ])
            ->orderBy('code')
            ->get();

        $facilityIds = $facilities->pluck('id');

        $facilities->each(function (Facility $facility): void {
            $facility->devices->each(function (Device $device): void {
                $freshness = DeviceTelemetryFreshness::describe($device);
                $effectiveStatus = match (true) {
                    $device->archived_at !== null || ! $device->is_active => 'inactive',
                    ! $freshness['is_fresh'] => 'offline',
                    default => (string) $device->status,
                };

                $device->setAttribute('telemetry_freshness', $freshness);
                $device->setAttribute('monitoring_status', $effectiveStatus);
            });

            $activeDevices = $facility->devices
                ->filter(fn (Device $device): bool => $device->is_active && $device->archived_at === null)
                ->values();
            $freshDevices = $activeDevices
                ->filter(fn (Device $device): bool => (bool) data_get($device->getAttribute('telemetry_freshness'), 'is_fresh', false));

            $facility->setAttribute('monitoring_status', $this->facilityStatus($facility));
            $facility->setAttribute('monitoring_summary', $this->facilitySummary($facility));
            $facility->setAttribute('latest_seen_at', $facility->devices->max('last_seen_at'));
            $facility->setAttribute('active_devices_count', $activeDevices->count());
            $facility->setAttribute('online_devices_count', $facility->devices->where('monitoring_status', 'online')->count());
            $facility->setAttribute('problem_devices_count', $facility->devices->where('monitoring_status', 'problem')->count());
            $facility->setAttribute('offline_devices_count', $facility->devices->where('monitoring_status', 'offline')->count());
            $facility->setAttribute('monitoring_score', $this->operationalScore($activeDevices));
            $facility->setAttribute(
                'monitoring_confidence',
                $activeDevices->isNotEmpty()
                    ? (int) round(($freshDevices->count() / $activeDevices->count()) * 100)
                    : 0
            );
            $facility->setAttribute(
                'monitoring_pulse',
                $activeDevices
                    ->take(16)
                    ->map(fn (Device $device): array => [
                        'name' => $device->name,
                        'state' => $this->deviceState((string) $device->getAttribute('monitoring_status')),
                        'label' => $this->deviceStateLabel((string) $device->getAttribute('monitoring_status')),
                    ])
                    ->values()
                    ->all()
            );
        });

        $openIncidentQuery = Incident::query()
            ->whereIn('facility_id', $facilityIds)
            ->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES);
        $openIncidentCount = (clone $openIncidentQuery)->count();
        $openIncidents = $openIncidentQuery
            ->with(['facility', 'device', 'assignedUser'])
            ->latest('started_at')
            ->limit(12)
            ->get();

        $recentEvents = Incident::query()
            ->with(['facility', 'device', 'assignedUser'])
            ->whereIn('facility_id', $facilityIds)
            ->where(function ($query): void {
                $query
                    ->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)
                    ->orWhere('ended_at', '>=', now()->subDay());
            })
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->sortByDesc(function (Incident $incident): int {
                $eventAt = in_array($incident->status, Incident::ACTIVE_STATUSES, true)
                    ? $incident->started_at
                    : ($incident->ended_at
                        ?? $incident->closed_at
                        ?? $incident->last_status_change_at
                        ?? $incident->updated_at);

                return $eventAt?->getTimestamp() ?? 0;
            })
            ->take(8)
            ->values();

        /** @var Collection<int, Device> $devices */
        $devices = $facilities
            ->flatMap(fn (Facility $facility): Collection => $facility->devices)
            ->values();
        $activeDevices = $devices
            ->filter(fn (Device $device): bool => $device->is_active && $device->archived_at === null)
            ->values();
        $freshDevices = $activeDevices
            ->filter(fn (Device $device): bool => (bool) data_get($device->getAttribute('telemetry_freshness'), 'is_fresh', false));

        $stats = [
            'facilities' => $facilities->count(),
            'online' => $facilities->where('monitoring_status', 'online')->count(),
            'warning' => $facilities->where('monitoring_status', 'warning')->count(),
            'offline' => $facilities->where('monitoring_status', 'offline')->count(),
            'inactive' => $facilities->where('monitoring_status', 'inactive')->count(),
            'unknown' => $facilities->where('monitoring_status', 'unknown')->count(),
            'devices' => $devices->count(),
            'active_devices' => $activeDevices->count(),
            'device_online' => $activeDevices->where('monitoring_status', 'online')->count(),
            'device_warning' => $activeDevices->where('monitoring_status', 'problem')->count(),
            'device_offline' => $activeDevices->where('monitoring_status', 'offline')->count(),
            'fresh_devices' => $freshDevices->count(),
            'open_incidents' => $openIncidentCount,
            'operational_score' => $this->operationalScore($activeDevices),
            'data_confidence' => $activeDevices->isNotEmpty()
                ? (int) round(($freshDevices->count() / $activeDevices->count()) * 100)
                : 0,
        ];

        return [
            'facilities' => $facilities,
            'openIncidents' => $openIncidents,
            'recentEvents' => $recentEvents,
            'stats' => $stats,
            'generatedAt' => now(),
        ];
    }

    private function facilityStatus(Facility $facility): string
    {
        if (! $facility->is_active) {
            return 'inactive';
        }

        $devices = $facility->devices->where('is_active', true);

        if ($devices->isEmpty()) {
            return 'unknown';
        }

        if ($devices->contains(fn (Device $device): bool => $device->getAttribute('monitoring_status') === 'offline')) {
            return 'offline';
        }

        if ($devices->contains(fn (Device $device): bool => $device->getAttribute('monitoring_status') === 'problem')) {
            return 'warning';
        }

        if ($devices->every(fn (Device $device): bool => $device->getAttribute('monitoring_status') === 'online')) {
            return 'online';
        }

        return 'unknown';
    }

    private function facilitySummary(Facility $facility): string
    {
        return match ($facility->getAttribute('monitoring_status')) {
            'online' => 'Wszystkie aktywne urządzenia działają prawidłowo.',
            'warning' => 'Co najmniej jedno urządzenie zgłasza problem wymagający uwagi.',
            'offline' => 'Co najmniej jedno urządzenie nie wysyła heartbeatów.',
            'inactive' => 'Placówka jest wyłączona z monitorowania.',
            default => 'Brak wystarczających danych do określenia stanu.',
        };
    }

    /** @param Collection<int, Device> $devices */
    private function operationalScore(Collection $devices): ?int
    {
        if ($devices->isEmpty()) {
            return null;
        }

        $points = $devices->sum(function (Device $device): int {
            return match ((string) $device->getAttribute('monitoring_status')) {
                'online' => 100,
                'problem' => 65,
                'offline' => 0,
                default => 25,
            };
        });

        return (int) round($points / $devices->count());
    }

    private function deviceState(string $status): string
    {
        return match ($status) {
            'online' => 'healthy',
            'problem' => 'warning',
            'offline' => 'critical',
            'inactive' => 'inactive',
            default => 'unknown',
        };
    }

    private function deviceStateLabel(string $status): string
    {
        return match ($status) {
            'online' => 'Online',
            'problem' => 'Wymaga uwagi',
            'offline' => 'Brak komunikacji',
            'inactive' => 'Nieaktywne',
            default => 'Brak danych',
        };
    }
}
