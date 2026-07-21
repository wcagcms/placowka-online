<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Heartbeat;
use Illuminate\Support\Collection;

class DeviceHealthScoreService
{
    /**
     * @param Collection<int, \App\Models\Incident> $incidents
     * @return array{
     *   score:int,
     *   confidence:int,
     *   status:string,
     *   label:string,
     *   summary:string,
     *   factors:array<int, array{
     *      key:string,
     *      label:string,
     *      score:int,
     *      max:int,
     *      state:string,
     *      value:string,
     *      description:string,
     *      available:bool
     *   }>,
     *   recommendations:array<int, string>
     * }
     */
    public function calculate(
        Device $device,
        ?Heartbeat $latestHeartbeat,
        Collection $incidents
    ): array {
        $payload = is_array($latestHeartbeat?->payload)
            ? $latestHeartbeat->payload
            : [];

        $factors = [
            $this->connectivityFactor($device),
            $this->smartFactor($payload),
            $this->cpuFactor($payload),
            $this->memoryFactor($payload),
            $this->diskSpaceFactor($payload),
            $this->windowsServicesFactor($payload),
            $this->stabilityFactor($incidents),
        ];

        $available = collect($factors)
            ->where('available', true)
            ->values();

        $availableMax = (int) $available->sum('max');
        $earned = (int) $available->sum('score');

        $score = $availableMax > 0
            ? (int) round(($earned / $availableMax) * 100)
            : 0;

        $confidence = (int) round(
            (collect($factors)->where('available', true)->sum('max') / 100) * 100
        );

        [$status, $label] = $this->scoreStatus($score, $confidence);

        $recommendations = collect($factors)
            ->filter(fn (array $factor): bool =>
                $factor['available'] && in_array(
                    $factor['state'],
                    ['warning', 'critical'],
                    true
                )
            )
            ->sortBy(fn (array $factor): int =>
                $factor['state'] === 'critical' ? 0 : 1
            )
            ->map(fn (array $factor): string => $factor['description'])
            ->unique()
            ->values()
            ->take(5)
            ->all();

        if ($confidence < 70) {
            $missingLabels = collect($factors)
                ->where('available', false)
                ->pluck('label')
                ->filter()
                ->values()
                ->all();

            $recommendations[] = $missingLabels !== []
                ? 'Ocena jest jeszcze niepełna. Brakuje bieżących danych: '.implode(', ', $missingLabels).'. Poczekaj na pełny pomiar agenta.'
                : 'Ocena jest jeszcze niepełna. Poczekaj na kolejny pełny pomiar agenta.';
        }

        return [
            'score' => max(0, min(100, $score)),
            'confidence' => max(0, min(100, $confidence)),
            'status' => $status,
            'label' => $label,
            'summary' => $this->summary($score, $confidence, $recommendations),
            'factors' => $factors,
            'recommendations' => array_values(array_unique($recommendations)),
        ];
    }

    private function connectivityFactor(Device $device): array
    {
        $max = 30;
        $availableChecks = collect([
            'gateway' => $device->gateway_ok,
            'dns' => $device->dns_ok,
            'internet' => $device->internet_ok,
            'monitoring' => $device->monitoring_server_ok,
        ])->filter(fn ($value): bool => $value !== null);

        $available = $device->status !== 'unknown'
            || $availableChecks->isNotEmpty()
            || $device->last_seen_at !== null;

        if (! $available) {
            return $this->missingFactor(
                'connectivity',
                'Łączność',
                $max,
                'Brak danych o połączeniu.'
            );
        }

        $score = $max;
        $problems = [];

        if ($device->status === 'offline') {
            $score = 0;
            $problems[] = 'urządzenie jest offline';
        } elseif ($device->status === 'problem') {
            $score -= 12;
            $problems[] = 'urządzenie zgłasza problem';
        }

        foreach ([
            'gateway_ok' => ['Brama/router', 5],
            'dns_ok' => ['DNS', 5],
            'internet_ok' => ['Internet', 7],
            'monitoring_server_ok' => ['Serwer monitoringu', 3],
        ] as $field => [$label, $penalty]) {
            if ($device->{$field} === false) {
                $score -= $penalty;
                $problems[] = $label;
            }
        }

        $score = max(0, $score);
        $state = $this->factorState($score, $max);

        return [
            'key' => 'connectivity',
            'label' => 'Łączność',
            'score' => $score,
            'max' => $max,
            'state' => $state,
            'value' => $problems === [] ? 'Połączenie prawidłowe' : implode(', ', $problems),
            'description' => $problems === []
                ? 'Połączenie sieciowe działa prawidłowo.'
                : 'Sprawdź połączenie: ' . implode(', ', $problems) . '.',
            'available' => true,
        ];
    }

    private function smartFactor(array $payload): array
    {
        $max = 25;
        $disks = collect(data_get($payload, 'smart_info.disks', []))
            ->filter(fn ($disk): bool => is_array($disk))
            ->values();

        if ($disks->isEmpty()) {
            return $this->missingFactor(
                'smart',
                'SMART dysków',
                $max,
                'Brak danych SMART.'
            );
        }

        $score = $max;
        $messages = [];

        foreach ($disks as $disk) {
            $name = (string) (
                data_get($disk, 'friendly_name')
                ?: data_get($disk, 'model')
                ?: 'Dysk'
            );
            $health = mb_strtolower(trim((string) data_get($disk, 'health_status', '')));
            $temperature = data_get($disk, 'temperature_c');
            $wear = data_get($disk, 'wear_percent_used');
            $predictFailure = (bool) data_get($disk, 'predict_failure', false);

            if (
                $predictFailure
                || ! in_array($health, ['', 'healthy', 'ok', 'unknown'], true)
            ) {
                $score = 0;
                $messages[] = $name . ': krytyczny stan SMART';
                continue;
            }

            if (is_numeric($temperature) && (float) $temperature >= 65) {
                $score = min($score, 4);
                $messages[] = $name . ': temperatura krytyczna';
            } elseif (is_numeric($temperature) && (float) $temperature >= 55) {
                $score = min($score, 15);
                $messages[] = $name . ': podwyższona temperatura';
            }

            if (is_numeric($wear) && (float) $wear >= 95) {
                $score = min($score, 4);
                $messages[] = $name . ': kończąca się żywotność SSD';
            } elseif (is_numeric($wear) && (float) $wear >= 80) {
                $score = min($score, 15);
                $messages[] = $name . ': wysokie zużycie SSD';
            }
        }

        return [
            'key' => 'smart',
            'label' => 'SMART dysków',
            'score' => max(0, $score),
            'max' => $max,
            'state' => $this->factorState($score, $max),
            'value' => $messages === [] ? 'Dyski w dobrym stanie' : implode(', ', $messages),
            'description' => $messages === []
                ? 'Dyski nie zgłaszają problemów SMART.'
                : 'Sprawdź dyski: ' . implode(', ', $messages) . '.',
            'available' => true,
        ];
    }

    private function cpuFactor(array $payload): array
    {
        $max = 7;
        $usage = data_get($payload, 'system_info.cpu.usage_percent');

        if (! is_numeric($usage)) {
            return $this->missingFactor('cpu', 'Procesor', $max, 'Brak danych CPU.');
        }

        $usage = (float) $usage;
        $score = match (true) {
            $usage >= 95 => 0,
            $usage >= 90 => 2,
            $usage >= 75 => 5,
            default => $max,
        };

        return [
            'key' => 'cpu',
            'label' => 'Procesor',
            'score' => $score,
            'max' => $max,
            'state' => $this->factorState($score, $max),
            'value' => number_format($usage, 1, ',', ' ') . '%',
            'description' => $usage >= 90
                ? 'Użycie CPU jest bardzo wysokie. Sprawdź obciążające procesy.'
                : ($usage >= 75
                    ? 'Użycie CPU jest podwyższone.'
                    : 'Obciążenie procesora jest prawidłowe.'),
            'available' => true,
        ];
    }

    private function memoryFactor(array $payload): array
    {
        $max = 7;
        $usage = data_get($payload, 'system_info.memory.usage_percent');

        if (! is_numeric($usage)) {
            return $this->missingFactor('memory', 'Pamięć RAM', $max, 'Brak danych RAM.');
        }

        $usage = (float) $usage;
        $score = match (true) {
            $usage >= 97 => 0,
            $usage >= 92 => 2,
            $usage >= 80 => 5,
            default => $max,
        };

        return [
            'key' => 'memory',
            'label' => 'Pamięć RAM',
            'score' => $score,
            'max' => $max,
            'state' => $this->factorState($score, $max),
            'value' => number_format($usage, 1, ',', ' ') . '%',
            'description' => $usage >= 92
                ? 'Pamięć RAM jest niemal całkowicie wykorzystana.'
                : ($usage >= 80
                    ? 'Wykorzystanie pamięci RAM jest podwyższone.'
                    : 'Wykorzystanie pamięci RAM jest prawidłowe.'),
            'available' => true,
        ];
    }

    private function diskSpaceFactor(array $payload): array
    {
        $max = 6;
        $disks = collect(data_get($payload, 'system_info.disks', []))
            ->filter(fn ($disk): bool =>
                is_array($disk) && is_numeric(data_get($disk, 'usage_percent'))
            )
            ->values();

        if ($disks->isEmpty()) {
            return $this->missingFactor(
                'disk_space',
                'Miejsce na dyskach',
                $max,
                'Brak danych o zajętości dysków.'
            );
        }

        $worst = $disks->sortByDesc(
            fn (array $disk): float => (float) data_get($disk, 'usage_percent', 0)
        )->first();

        $usage = (float) data_get($worst, 'usage_percent');
        $name = (string) data_get($worst, 'name', 'Dysk');

        $score = match (true) {
            $usage >= 97 => 0,
            $usage >= 92 => 2,
            $usage >= 80 => 4,
            default => $max,
        };

        return [
            'key' => 'disk_space',
            'label' => 'Miejsce na dyskach',
            'score' => $score,
            'max' => $max,
            'state' => $this->factorState($score, $max),
            'value' => $name . ' — ' . number_format($usage, 1, ',', ' ') . '%',
            'description' => $usage >= 92
                ? 'Na dysku ' . $name . ' kończy się wolne miejsce.'
                : ($usage >= 80
                    ? 'Dysk ' . $name . ' ma mało wolnego miejsca.'
                    : 'Na dyskach jest wystarczająco dużo wolnego miejsca.'),
            'available' => true,
        ];
    }

    private function windowsServicesFactor(array $payload): array
    {
        $max = 15;
        $services = collect(data_get($payload, 'windows_services', []))
            ->filter(fn ($service): bool => is_array($service))
            ->values();

        if ($services->isEmpty()) {
            return $this->missingFactor(
                'services',
                'Usługi Windows',
                $max,
                'Brak danych o usługach Windows.'
            );
        }

        $required = $services->where('alert', true);
        $failed = $required->where('healthy', false);

        if ($required->isEmpty()) {
            return [
                'key' => 'services',
                'label' => 'Usługi Windows',
                'score' => $max,
                'max' => $max,
                'state' => 'healthy',
                'value' => 'Brak wymaganych usług',
                'description' => 'Nie skonfigurowano usług wymaganych do oceny.',
                'available' => true,
            ];
        }

        $ratio = $failed->count() / max(1, $required->count());
        $score = (int) round($max * (1 - $ratio));

        $failedLabels = $failed
            ->map(fn (array $service): string =>
                (string) data_get($service, 'label', data_get($service, 'name', 'Usługa'))
            )
            ->implode(', ');

        return [
            'key' => 'services',
            'label' => 'Usługi Windows',
            'score' => max(0, $score),
            'max' => $max,
            'state' => $this->factorState($score, $max),
            'value' => ($required->count() - $failed->count())
                . '/'
                . $required->count()
                . ' działa',
            'description' => $failed->isEmpty()
                ? 'Wszystkie wymagane usługi Windows działają.'
                : 'Nie działają wymagane usługi: ' . $failedLabels . '.',
            'available' => true,
        ];
    }

    private function stabilityFactor(Collection $incidents): array
    {
        $max = 10;
        $recent = $incidents->filter(function ($incident): bool {
            return $incident->started_at !== null
                && $incident->started_at->gte(now()->subDays(30));
        });

        $open = $recent->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)->count();
        $count = $recent->count();

        $penalty = min(6, $count) + min(4, $open * 2);
        $score = max(0, $max - $penalty);

        return [
            'key' => 'stability',
            'label' => 'Stabilność 30 dni',
            'score' => $score,
            'max' => $max,
            'state' => $this->factorState($score, $max),
            'value' => $count . ' incydentów, ' . $open . ' aktywnych',
            'description' => $count === 0
                ? 'W ostatnich 30 dniach nie zarejestrowano incydentów.'
                : 'Przeanalizuj incydenty z ostatnich 30 dni, szczególnie aktywne problemy.',
            'available' => true,
        ];
    }

    private function missingFactor(
        string $key,
        string $label,
        int $max,
        string $description
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'score' => 0,
            'max' => $max,
            'state' => 'unknown',
            'value' => 'Brak danych',
            'description' => $description,
            'available' => false,
        ];
    }

    private function factorState(int $score, int $max): string
    {
        $ratio = $max > 0 ? $score / $max : 0;

        return match (true) {
            $ratio >= 0.8 => 'healthy',
            $ratio >= 0.5 => 'warning',
            default => 'critical',
        };
    }

    private function scoreStatus(int $score, int $confidence): array
    {
        if ($confidence < 40) {
            return ['unknown', 'Za mało danych'];
        }

        return match (true) {
            $score >= 90 => ['excellent', 'Bardzo dobry'],
            $score >= 75 => ['good', 'Dobry'],
            $score >= 55 => ['warning', 'Wymaga uwagi'],
            default => ['critical', 'Krytyczny'],
        };
    }

    private function summary(
        int $score,
        int $confidence,
        array $recommendations
    ): string {
        if ($confidence < 40) {
            return 'Ocena jest wstępna, ponieważ brakuje większości danych diagnostycznych.';
        }

        if ($recommendations === []) {
            return 'Nie wykryto parametrów wymagających pilnej interwencji.';
        }

        return $score >= 75
            ? 'Urządzenie działa, ale część parametrów wymaga obserwacji.'
            : 'Urządzenie wymaga działania administratora.';
    }
}
