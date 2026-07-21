<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Incident;
use App\Services\IncidentLifecycleService;
use App\Services\IncidentNotificationService;
use Illuminate\Console\Command;

class CheckDeviceStatuses extends Command
{
    protected $signature = 'placowka:check-status';

    protected $description = 'Sprawdza, czy urządzenia wysyłają heartbeat i otwiera lub rozwiązuje incydenty.';

    public function handle(
        IncidentNotificationService $notifications,
        IncidentLifecycleService $incidents
    ): int {
        $checked = 0;
        $opened = 0;
        $resolved = 0;

        Device::query()
            ->where('is_active', true)
            ->with('facility')
            ->chunkById(100, function ($devices) use (&$checked, &$opened, &$resolved, $notifications, $incidents): void {
                foreach ($devices as $device) {
                    $checked++;

                    if (! $device->last_seen_at) {
                        continue;
                    }

                    $missingAfterMinutes = $device->missing_after_minutes
                        ?: config('placowka.default_missing_after_minutes', 3);
                    $threshold = now()->subMinutes($missingAfterMinutes);

                    if ($device->last_seen_at->lt($threshold)) {
                        $device->update(['status' => 'offline']);

                        $alreadyActive = Incident::query()
                            ->where('device_id', $device->id)
                            ->where('type', 'no_communication')
                            ->active()
                            ->exists();

                        $incident = $incidents->openOrTouch(
                            $device,
                            'no_communication',
                            'Brak raportu heartbeat od agenta.',
                            'critical',
                            $notifications,
                            [
                                'last_agent_seen_at' => $device->last_seen_at?->toIso8601String(),
                                'missing_after_minutes' => $missingAfterMinutes,
                            ]
                        );

                        if (! $alreadyActive) {
                            $incident->forceFill([
                                'started_at' => $device->last_seen_at->copy()->addSeconds($device->check_interval_seconds ?: 60),
                            ])->save();
                            $opened++;
                        }

                        continue;
                    }

                    if ($device->status === 'offline') {
                        $device->update([
                            'status' => ($device->internet_ok && $device->dns_ok) ? 'online' : 'problem',
                        ]);
                    }

                    if ($incidents->resolve(
                        $device,
                        'no_communication',
                        'Komunikacja z agentem została przywrócona.',
                        $notifications
                    )) {
                        $resolved++;
                    }
                }
            });

        $this->info("Sprawdzono urządzeń: {$checked}");
        $this->info("Nowe awarie: {$opened}");
        $this->info("Rozwiązane awarie: {$resolved}");

        return self::SUCCESS;
    }
}
