<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Facility;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Collection;

class AvailabilityReportService
{
    public function globalReport(User $user): array
    {
        $facilities = Facility::query()
            ->visibleTo($user)
            ->with(['devices' => function ($query) {
                $query->whereNull('archived_at')->orderBy('name');
            }])
            ->orderBy('code')
            ->get();

        return [
            'generated_at' => now(),
            'periods' => $this->periods(),
            'facilities' => $facilities->map(function (Facility $facility) {
                return [
                    'facility' => $facility,
                    'summaries' => [
                        '24h' => $this->facilitySummary($facility, 1),
                        '7d' => $this->facilitySummary($facility, 7),
                        '30d' => $this->facilitySummary($facility, 30),
                    ],
                ];
            }),
        ];
    }

    public function facilityReport(Facility $facility): array
    {
        $facility->load(['devices' => function ($query) {
            $query->whereNull('archived_at')->orderBy('name');
        }]);

        $devices = $facility->devices;

        return [
            'generated_at' => now(),
            'facility' => $facility,
            'periods' => $this->periods(),
            'summaries' => [
                '24h' => $this->facilitySummary($facility, 1),
                '7d' => $this->facilitySummary($facility, 7),
                '30d' => $this->facilitySummary($facility, 30),
            ],
            'devices' => $devices->map(function (Device $device) {
                return [
                    'device' => $device,
                    'summaries' => [
                        '24h' => $this->deviceSummary($device, 1),
                        '7d' => $this->deviceSummary($device, 7),
                        '30d' => $this->deviceSummary($device, 30),
                    ],
                ];
            }),
            'last_incidents' => Incident::query()
                ->with('device')
                ->where('facility_id', $facility->id)
                ->latest('started_at')
                ->limit(30)
                ->get(),
        ];
    }

    public function periods(): array
    {
        return [
            '24h' => [
                'label' => '24h',
                'days' => 1,
            ],
            '7d' => [
                'label' => '7 dni',
                'days' => 7,
            ],
            '30d' => [
                'label' => '30 dni',
                'days' => 30,
            ],
        ];
    }

    private function facilitySummary(Facility $facility, int $days): array
    {
        $devices = Device::query()
            ->where('facility_id', $facility->id)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();

        $periodEnd = now();
        $periodStart = now()->subDays($days);
        $periodSeconds = max(1, $periodStart->diffInSeconds($periodEnd));

        $downtimeSeconds = 0;
        $incidentCount = 0;
        $openIncidentCount = 0;

        foreach ($devices as $device) {
            $deviceData = $this->deviceSummaryForPeriod($device, $periodStart, $periodEnd, $periodSeconds);

            $downtimeSeconds += $deviceData['downtime_seconds'];
            $incidentCount += $deviceData['incident_count'];
            $openIncidentCount += $deviceData['open_incidents'];
        }

        $deviceCount = $devices->count();
        $monitoredSeconds = $deviceCount > 0 ? $periodSeconds * $deviceCount : 0;

        $availability = $this->availabilityPercent($monitoredSeconds, $downtimeSeconds);

        return [
            'days' => $days,
            'device_count' => $deviceCount,
            'incident_count' => $incidentCount,
            'open_incidents' => $openIncidentCount,
            'downtime_seconds' => $downtimeSeconds,
            'downtime_text' => $this->formatDuration($downtimeSeconds),
            'monitored_seconds' => $monitoredSeconds,
            'availability_percent' => $availability,
            'availability_text' => $availability === null ? 'brak danych' : number_format($availability, 2, ',', ' ') . '%',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    private function deviceSummary(Device $device, int $days): array
    {
        $periodEnd = now();
        $periodStart = now()->subDays($days);
        $periodSeconds = max(1, $periodStart->diffInSeconds($periodEnd));

        return $this->deviceSummaryForPeriod($device, $periodStart, $periodEnd, $periodSeconds);
    }

    private function deviceSummaryForPeriod(Device $device, $periodStart, $periodEnd, int $periodSeconds): array
    {
        $incidents = Incident::query()
            ->where('device_id', $device->id)
            ->where('started_at', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart) {
                $query
                    ->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $periodStart);
            })
            ->orderBy('started_at')
            ->get();

        $downtimeSeconds = $this->mergedDowntimeSeconds($incidents, $periodStart, $periodEnd);
        $availability = $this->availabilityPercent($periodSeconds, $downtimeSeconds);

        return [
            'days' => null,
            'device_count' => 1,
            'incident_count' => $incidents->count(),
            'open_incidents' => $incidents->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)->count(),
            'downtime_seconds' => $downtimeSeconds,
            'downtime_text' => $this->formatDuration($downtimeSeconds),
            'monitored_seconds' => $periodSeconds,
            'availability_percent' => $availability,
            'availability_text' => $availability === null ? 'brak danych' : number_format($availability, 2, ',', ' ') . '%',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    private function mergedDowntimeSeconds(Collection $incidents, $periodStart, $periodEnd): int
    {
        $intervals = [];

        foreach ($incidents as $incident) {
            if (! $incident->started_at) {
                continue;
            }

            $startTs = max($incident->started_at->getTimestamp(), $periodStart->getTimestamp());

            $endedAt = $incident->ended_at ?: $periodEnd;
            $endTs = min($endedAt->getTimestamp(), $periodEnd->getTimestamp());

            if ($endTs > $startTs) {
                $intervals[] = [$startTs, $endTs];
            }
        }

        if (empty($intervals)) {
            return 0;
        }

        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($intervals as $interval) {
            if (empty($merged)) {
                $merged[] = $interval;
                continue;
            }

            $lastIndex = count($merged) - 1;

            if ($interval[0] <= $merged[$lastIndex][1]) {
                $merged[$lastIndex][1] = max($merged[$lastIndex][1], $interval[1]);
            } else {
                $merged[] = $interval;
            }
        }

        $seconds = 0;

        foreach ($merged as $interval) {
            $seconds += max(0, $interval[1] - $interval[0]);
        }

        return $seconds;
    }

    private function availabilityPercent(int $monitoredSeconds, int $downtimeSeconds): ?float
    {
        if ($monitoredSeconds <= 0) {
            return null;
        }

        $availableSeconds = max(0, $monitoredSeconds - $downtimeSeconds);

        return round(($availableSeconds / $monitoredSeconds) * 100, 2);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 sek.';
        }

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;

        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;

        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days . ' d';
        }

        if ($hours > 0) {
            $parts[] = $hours . ' godz.';
        }

        if ($minutes > 0) {
            $parts[] = $minutes . ' min';
        }

        if ($seconds > 0 && $days === 0) {
            $parts[] = $seconds . ' sek.';
        }

        return implode(' ', $parts);
    }
}
