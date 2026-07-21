<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\AgentDiagnosticsService;
use App\Support\DeviceTelemetryFreshness;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentStatusController extends Controller
{
    public function __invoke(Request $request, AgentDiagnosticsService $diagnostics): View
    {
        $user = $request->user();
        abort_unless($user, 403);

        $items = Device::query()
            ->with('facility')
            ->whereNull('archived_at')
            ->whereHas('facility', fn ($query) => $query->visibleTo($user))
            ->orderBy('name')
            ->get()
            ->map(function (Device $device) use ($diagnostics): array {
                $freshness = DeviceTelemetryFreshness::describe($device);

                return [
                    'device' => $device,
                    'diagnostics' => $diagnostics->describe($device, $freshness),
                ];
            })
            ->sort(function (array $left, array $right): int {
                $priority = static fn (array $item): int => match (data_get($item, 'diagnostics.status')) {
                    'critical' => 10,
                    'warning' => 20,
                    'healthy' => 30,
                    'inactive' => 40,
                    default => 50,
                };

                $byPriority = $priority($left) <=> $priority($right);
                if ($byPriority !== 0) {
                    return $byPriority;
                }

                $leftLabel = mb_strtolower((string) data_get($left, 'device.facility.code').' '.(string) data_get($left, 'device.name'));
                $rightLabel = mb_strtolower((string) data_get($right, 'device.facility.code').' '.(string) data_get($right, 'device.name'));

                return $leftLabel <=> $rightLabel;
            })
            ->values();

        $stats = [
            'all' => $items->count(),
            'healthy' => $items->where('diagnostics.status', 'healthy')->count(),
            'warning' => $items->where('diagnostics.status', 'warning')->count(),
            'critical' => $items->where('diagnostics.status', 'critical')->count(),
            'outdated' => $items->where('diagnostics.version.status', 'outdated')->count(),
            'without_self_check' => $items->where('diagnostics.self_check_available', false)->count(),
        ];

        return view('agents.index', [
            'items' => $items,
            'stats' => $stats,
            'latestVersion' => (string) config('placowka.agent_latest_version', 'exe-1.8.0'),
        ]);
    }
}
