<?php

namespace App\Services;

use App\Models\Device;
use App\Support\DeviceTelemetryFreshness;

class AgentDiagnosticsService
{
    public function __construct(
        private readonly AgentVersionService $versions
    ) {
    }

    /** @param array<string, mixed>|null $freshness */
    public function describe(Device $device, ?array $freshness = null): array
    {
        $freshness ??= DeviceTelemetryFreshness::describe($device);
        $isFresh = (bool) data_get($freshness, 'is_fresh', false);
        $health = is_array($device->agent_health) ? $device->agent_health : [];
        $version = $this->versions->describe($device->agent_version);
        $completeness = $this->boundedInt(data_get($health, 'telemetry_completeness_percent'));
        $missingModules = collect(data_get($health, 'missing_modules', []))
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => $this->moduleLabel($value))
            ->unique()
            ->values()
            ->all();

        $selfStatus = (string) data_get($health, 'status', 'unknown');
        if (! in_array($selfStatus, ['healthy', 'warning', 'critical', 'unknown'], true)) {
            $selfStatus = 'unknown';
        }

        $status = match (true) {
            ! $device->is_active || $device->archived_at !== null => 'inactive',
            ! $isFresh => 'critical',
            $health === [] => 'warning',
            $selfStatus === 'critical' => 'critical',
            $selfStatus === 'warning' => 'warning',
            $version['status'] === 'outdated' => 'warning',
            $completeness !== null && $completeness < 70 => 'warning',
            default => 'healthy',
        };

        $clockSkew = data_get($health, 'clock_skew_seconds');
        $clockSkew = is_numeric($clockSkew) ? (int) $clockSkew : null;

        return [
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'status_message' => $this->statusMessage($status, $health, $version, $missingModules, $freshness),
            'telemetry_fresh' => $isFresh,
            'freshness_label' => (string) data_get($freshness, 'label', 'Brak danych'),
            'version' => $version,
            'self_check_available' => $health !== [],
            'self_check_status' => $selfStatus,
            'self_check_label' => $this->statusLabel($selfStatus),
            'profile' => (string) data_get($health, 'profile', 'Brak danych'),
            'run_mode' => $this->runModeLabel((string) data_get($health, 'run_mode', '')),
            'cycle_duration_ms' => $this->nullableInt(data_get($health, 'cycle_duration_ms')),
            'cycle_duration' => $this->durationLabel(data_get($health, 'cycle_duration_ms')),
            'telemetry_completeness_percent' => $completeness,
            'telemetry_completeness_label' => $completeness !== null ? $completeness.'%' : 'Brak danych',
            'missing_modules' => $missingModules,
            'config_valid' => $this->nullableBool(data_get($health, 'config_valid')),
            'state_file_writable' => $this->nullableBool(data_get($health, 'state_file_writable')),
            'log_directory_writable' => $this->nullableBool(data_get($health, 'log_directory_writable')),
            'task_present' => $this->nullableBool(data_get($health, 'task_present')),
            'consecutive_failures' => $this->nullableInt(data_get($health, 'consecutive_failures')) ?? 0,
            'last_failure_at' => $this->dateLabel(data_get($health, 'last_failure_at')),
            'last_failure_reason' => $this->safeText(data_get($health, 'last_failure_reason'), 300),
            'clock_skew_seconds' => $clockSkew,
            'clock_skew_label' => $clockSkew !== null ? $this->clockSkewLabel($clockSkew) : 'Brak danych',
            'last_system_at' => $this->dateLabel(data_get($health, 'last_system_at')),
            'last_network_at' => $this->dateLabel(data_get($health, 'last_network_at')),
            'last_services_at' => $this->dateLabel(data_get($health, 'last_services_at')),
            'last_smart_at' => $this->dateLabel(data_get($health, 'last_smart_at')),
            'updated_at' => $device->agent_health_updated_at?->timezone('Europe/Warsaw')->format('d.m.Y H:i:s')
                ?? 'Brak danych',
            'checks' => [
                $this->check('Konfiguracja', $this->nullableBool(data_get($health, 'config_valid'))),
                $this->check('Plik stanu', $this->nullableBool(data_get($health, 'state_file_writable'))),
                $this->check('Katalog logów', $this->nullableBool(data_get($health, 'log_directory_writable'))),
                $this->check('Zadanie automatyczne', $this->nullableBool(data_get($health, 'task_present'))),
            ],
        ];
    }

    private function statusMessage(
        string $status,
        array $health,
        array $version,
        array $missingModules,
        array $freshness
    ): string {
        if ($status === 'inactive') {
            return 'Urządzenie jest wyłączone z monitorowania.';
        }

        if ($status === 'critical' && ! (bool) data_get($freshness, 'is_fresh', false)) {
            return 'Agent nie przesyła bieżących heartbeatów.';
        }

        if ($health === []) {
            return 'Agent działa, ale nie obsługuje jeszcze rozszerzonej samokontroli. Zaktualizuj go do wersji 1.8.0.';
        }

        if ((string) data_get($health, 'status') === 'critical') {
            return 'Samokontrola agenta wykryła problem wymagający działania administratora.';
        }

        $failures = is_numeric(data_get($health, 'consecutive_failures'))
            ? (int) data_get($health, 'consecutive_failures')
            : 0;
        if ($failures > 0) {
            return 'Agent odzyskał komunikację, ale raportuje '.$failures.' kolejnych nieudanych prób wysłania heartbeat.';
        }

        $cycleDuration = is_numeric(data_get($health, 'cycle_duration_ms'))
            ? (int) data_get($health, 'cycle_duration_ms')
            : null;
        if ($cycleDuration !== null && $cycleDuration > 30000) {
            return 'Agent działa, ale ostatni cykl trwał dłużej niż 30 sekund. Sprawdź wydajność komputera i moduły ciężkie.';
        }

        $clockSkew = is_numeric(data_get($health, 'clock_skew_seconds'))
            ? abs((int) data_get($health, 'clock_skew_seconds'))
            : 0;
        if ($clockSkew > 300) {
            return 'Agent działa, ale zegar komputera różni się od czasu serwera o ponad 5 minut.';
        }

        if ($version['status'] === 'outdated') {
            return 'Agent działa, ale dostępna jest nowsza wersja produkcyjna.';
        }

        if ($missingModules !== []) {
            return 'Agent działa, lecz nie ma jeszcze kompletu pomiarów: '.implode(', ', $missingModules).'.';
        }

        return 'Agent działa prawidłowo, a pomiary są kompletne.';
    }

    private function check(string $label, ?bool $value): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'state' => $value === true ? 'healthy' : ($value === false ? 'critical' : 'unknown'),
            'text' => $value === true ? 'Prawidłowo' : ($value === false ? 'Problem' : 'Brak danych'),
        ];
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'system' => 'Windows i dyski',
            'network' => 'karta sieciowa',
            'services' => 'usługi Windows',
            'smart' => 'SMART dysków',
            default => str_replace('_', ' ', $module),
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'healthy' => 'Prawidłowy',
            'warning' => 'Wymaga uwagi',
            'critical' => 'Problem',
            'inactive' => 'Nieaktywny',
            default => 'Brak danych',
        };
    }

    private function runModeLabel(string $mode): string
    {
        return match ($mode) {
            'service' => 'Harmonogram low impact',
            'console' => 'Pełny pomiar ręczny',
            default => $mode !== '' ? $mode : 'Brak danych',
        };
    }

    private function durationLabel(mixed $milliseconds): string
    {
        if (! is_numeric($milliseconds)) {
            return 'Brak danych';
        }

        $milliseconds = max(0, (int) $milliseconds);

        return $milliseconds < 1000
            ? $milliseconds.' ms'
            : number_format($milliseconds / 1000, 2, ',', ' ').' s';
    }

    private function clockSkewLabel(int $seconds): string
    {
        $absolute = abs($seconds);
        if ($absolute < 5) {
            return 'Prawidłowy';
        }

        $direction = $seconds > 0 ? 'zegar komputera jest opóźniony' : 'zegar komputera wyprzedza serwer';

        return $direction.' o '.$absolute.' s';
    }

    private function dateLabel(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return 'Brak danych';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)
                ->timezone('Europe/Warsaw')
                ->format('d.m.Y H:i:s');
        } catch (\Throwable) {
            return 'Brak danych';
        }
    }

    private function nullableBool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function boundedInt(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, min(100, (int) $value)) : null;
    }

    private function safeText(mixed $value, int $max): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $max);
    }
}
