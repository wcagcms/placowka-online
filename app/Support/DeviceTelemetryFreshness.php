<?php

namespace App\Support;

use App\Models\Device;
use App\Models\Heartbeat;
use Illuminate\Support\Carbon;

final class DeviceTelemetryFreshness
{
    /**
     * @return array{
     *   is_fresh:bool,
     *   state:string,
     *   label:string,
     *   description:string,
     *   measured_at:?Carbon,
     *   measured_at_label:string,
     *   measured_at_relative:string,
     *   threshold_minutes:int
     * }
     */
    public static function describe(Device $device, ?Heartbeat $heartbeat = null): array
    {
        $thresholdMinutes = self::thresholdMinutes($device);
        $measuredAt = self::measuredAt($device, $heartbeat);

        if ($device->archived_at !== null) {
            return self::result(
                false,
                'archived',
                'Urządzenie archiwalne',
                'Urządzenie jest archiwalne i nie przekazuje bieżących danych.',
                $measuredAt,
                $thresholdMinutes
            );
        }

        if (! $device->is_active) {
            return self::result(
                false,
                'inactive',
                'Monitoring wyłączony',
                'Urządzenie jest nieaktywne i nie jest uwzględniane w bieżącym monitoringu.',
                $measuredAt,
                $thresholdMinutes
            );
        }

        if ($measuredAt === null) {
            return self::result(
                false,
                'never',
                'Brak pierwszego pomiaru',
                'Agent nie przesłał jeszcze żadnego prawidłowego heartbeat.',
                null,
                $thresholdMinutes
            );
        }

        $isWithinThreshold = $measuredAt->gte(now()->subMinutes($thresholdMinutes));
        $isFresh = $isWithinThreshold && $device->status !== 'offline';

        if ($isFresh) {
            return self::result(
                true,
                'fresh',
                'Dane bieżące',
                'Ostatni pomiar mieści się w dozwolonym czasie braku komunikacji.',
                $measuredAt,
                $thresholdMinutes
            );
        }

        return self::result(
            false,
            'stale',
            'Brak bieżącej komunikacji',
            'Wartości techniczne są nieaktualne. Ostatni znany pomiar pochodzi z '.$measuredAt
                ->timezone('Europe/Warsaw')
                ->format('Y-m-d H:i:s').'.',
            $measuredAt,
            $thresholdMinutes
        );
    }

    public static function isFresh(Device $device, ?Heartbeat $heartbeat = null): bool
    {
        return self::describe($device, $heartbeat)['is_fresh'];
    }

    /** @return array<string, mixed> */
    public static function unavailableHealthScore(array $freshness): array
    {
        $message = (string) ($freshness['description'] ?? 'Brak bieżących danych telemetrycznych.');

        $factors = collect([
            ['key' => 'connectivity', 'label' => 'Łączność', 'max' => 30],
            ['key' => 'smart', 'label' => 'SMART dysków', 'max' => 25],
            ['key' => 'cpu', 'label' => 'Procesor', 'max' => 10],
            ['key' => 'memory', 'label' => 'Pamięć RAM', 'max' => 10],
            ['key' => 'disk_space', 'label' => 'Miejsce na dyskach', 'max' => 10],
            ['key' => 'windows_services', 'label' => 'Usługi Windows', 'max' => 10],
            ['key' => 'stability', 'label' => 'Stabilność', 'max' => 5],
        ])->map(fn (array $factor): array => [
            'key' => $factor['key'],
            'label' => $factor['label'],
            'score' => 0,
            'max' => $factor['max'],
            'state' => 'unknown',
            'value' => 'Brak bieżących danych',
            'description' => $message,
            'available' => false,
        ])->all();

        return [
            'score' => null,
            'confidence' => 0,
            'status' => 'unknown',
            'label' => 'Ocena wstrzymana — brak komunikacji',
            'summary' => $message,
            'factors' => $factors,
            'recommendations' => [
                'Sprawdź działanie agenta, Harmonogram zadań Windows oraz dostęp urządzenia do Internetu.',
            ],
        ];
    }

    public static function thresholdMinutes(Device $device): int
    {
        return max(
            1,
            (int) ($device->missing_after_minutes
                ?: config('placowka.default_missing_after_minutes', 3))
        );
    }

    private static function measuredAt(Device $device, ?Heartbeat $heartbeat): ?Carbon
    {
        $heartbeatAt = $heartbeat?->checked_at ?: $heartbeat?->created_at;
        $deviceAt = $device->last_seen_at;

        if ($heartbeatAt && $deviceAt) {
            return $heartbeatAt->gte($deviceAt) ? $heartbeatAt->copy() : $deviceAt->copy();
        }

        return $heartbeatAt?->copy() ?: $deviceAt?->copy();
    }

    /**
     * @return array{
     *   is_fresh:bool,
     *   state:string,
     *   label:string,
     *   description:string,
     *   measured_at:?Carbon,
     *   measured_at_label:string,
     *   measured_at_relative:string,
     *   threshold_minutes:int
     * }
     */
    private static function result(
        bool $isFresh,
        string $state,
        string $label,
        string $description,
        ?Carbon $measuredAt,
        int $thresholdMinutes
    ): array {
        return [
            'is_fresh' => $isFresh,
            'state' => $state,
            'label' => $label,
            'description' => $description,
            'measured_at' => $measuredAt,
            'measured_at_label' => $measuredAt
                ? $measuredAt->timezone('Europe/Warsaw')->format('Y-m-d H:i:s')
                : 'Brak danych',
            'measured_at_relative' => $measuredAt
                ? $measuredAt->diffForHumans()
                : 'brak danych',
            'threshold_minutes' => $thresholdMinutes,
        ];
    }
}
