<?php

namespace App\Support;

final class PlatinumDashboardFactory
{
    /**
     * Buduje dane widoku z prostego, niezależnego od modeli zestawu wartości.
     * Dzięki temu wygląd nie jest związany z nazwami tabel ani relacji Eloquent.
     */
    public static function make(array $summary): array
    {
        $healthScore = is_numeric($summary['health_score'] ?? null)
            ? self::clamp((int) $summary['health_score'])
            : null;
        $online = max(0, (int) ($summary['devices_online'] ?? 0));
        $total = max($online, (int) ($summary['devices_total'] ?? $online));
        $warnings = max(0, (int) ($summary['warnings'] ?? 0));
        $failures = max(0, (int) ($summary['failures'] ?? 0));
        $activeIncidents = max(0, (int) ($summary['active_incidents'] ?? ($warnings + $failures)));
        $criticalIncidents = max(0, (int) ($summary['critical_incidents'] ?? $failures));
        $availability = $total > 0 ? round(($online / $total) * 100, 1) : 0.0;
        $internetUptime = max(0.0, min(100.0, (float) ($summary['internet_uptime'] ?? 0.0)));
        $smartHealthy = max(0, (int) ($summary['smart_healthy'] ?? 0));
        $smartWarnings = max(0, (int) ($summary['smart_warnings'] ?? 0));
        $windowsHosts = max(0, (int) ($summary['windows_hosts'] ?? $total));
        $windowsReporting = max(0, (int) ($summary['windows_reporting'] ?? $windowsHosts));
        $servicesActive = max(0, (int) ($summary['services_active'] ?? 0));
        $servicesTotal = max($servicesActive, (int) ($summary['services_total'] ?? $servicesActive));
        $links = array_merge([
            'dashboard' => '#main-content',
            'devices' => '#devices',
            'incidents' => '#incidents',
            'history' => '#',
            'settings' => '#',
            'users' => '#',
            'health' => '#',
            'smart' => '#',
            'windows' => '#',
            'services' => '#',
            'agents' => '#',
            'export' => '#',
        ], (array) ($summary['links'] ?? []));

        $healthTone = (string) ($summary['health_tone']
            ?? ($healthScore !== null ? self::scoreTone($healthScore) : 'neutral'));
        $systemTone = (string) ($summary['system_tone'] ?? ($failures > 0 ? 'danger' : ($warnings > 0 ? 'warning' : 'success')));
        $systemMessage = (string) ($summary['system_status'] ?? match ($systemTone) {
            'danger' => 'System wymaga reakcji',
            'warning' => 'System działa z ostrzeżeniami',
            default => 'System działa prawidłowo',
        });

        return [
            'page_title' => (string) ($summary['page_title'] ?? 'Placówka Online — SaaS Platinum'),
            'institution' => [
                'name' => (string) ($summary['institution_name'] ?? 'Placówka Online'),
                'description' => (string) ($summary['institution_description'] ?? 'Najważniejsze informacje o dostępności Internetu, komputerach, usługach Windows i stanie dysków w jednym czytelnym widoku.'),
                'system_status' => $systemMessage,
                'system_tone' => $systemTone,
                'health_score' => $healthScore,
                'health_tone' => $healthTone,
                'health_label' => (string) ($summary['health_label'] ?? ($healthScore !== null ? self::scoreLabel($healthScore) : 'Brak bieżących danych')),
                'reliability' => (string) ($summary['reliability'] ?? 'Brak danych'),
                'online' => $online,
                'online_label' => self::polishCount($online, 'urządzenie online', 'urządzenia online', 'urządzeń online'),
                'warnings' => $warnings,
                'warnings_label' => self::polishCount($warnings, 'ostrzeżenie', 'ostrzeżenia', 'ostrzeżeń'),
                'failures' => $failures,
                'failures_label' => self::polishCount($failures, 'awaria', 'awarie', 'awarii'),
                'active_incidents' => $activeIncidents,
            ],
            'user' => [
                'name' => (string) ($summary['user_name'] ?? 'Administrator'),
                'initials' => (string) ($summary['user_initials'] ?? 'AD'),
            ],
            'agent' => [
                'version' => (string) ($summary['agent_version'] ?? '—'),
                'message' => (string) ($summary['agent_message'] ?? 'Informacja o wersji agenta nie została przekazana.'),
            ],
            'last_refresh' => (string) ($summary['last_refresh'] ?? 'przed chwilą'),
            'links' => $links,
            'metrics' => [
                [
                    'icon' => 'health',
                    'tone' => $healthTone,
                    'trend' => (string) ($summary['health_trend'] ?? '▬ bez zmian'),
                    'trend_tone' => (string) ($summary['health_trend_tone'] ?? 'neutral'),
                    'label' => 'Health Score',
                    'value' => $healthScore ?? '—',
                    'suffix' => $healthScore !== null ? '/ 100' : '',
                    'note' => (string) ($summary['health_label'] ?? ($healthScore !== null ? self::scoreLabel($healthScore) : 'Brak bieżących danych')),
                    'url' => $links['health'],
                    'aria_label' => 'Szczegóły Health Score',
                ],
                [
                    'icon' => 'device',
                    'tone' => $failures > 0 ? 'warning' : 'success',
                    'trend' => (string) ($summary['devices_trend'] ?? '▬ bez zmian'),
                    'trend_tone' => (string) ($summary['devices_trend_tone'] ?? 'neutral'),
                    'label' => 'Urządzenia online',
                    'value' => $online,
                    'suffix' => 'z '.$total,
                    'note' => self::percent($availability).' dostępności',
                    'url' => $links['devices'],
                    'aria_label' => 'Lista urządzeń',
                ],
                [
                    'icon' => 'alarm',
                    'tone' => $activeIncidents > 0 ? 'danger' : 'success',
                    'trend' => (string) ($summary['incidents_trend'] ?? ($activeIncidents > 0 ? '▲ aktywne' : '▬ brak zmian')),
                    'trend_tone' => (string) ($summary['incidents_trend_tone'] ?? ($activeIncidents > 0 ? 'bad' : 'good')),
                    'label' => 'Aktywne alarmy',
                    'value' => $activeIncidents,
                    'suffix' => self::polishCount($activeIncidents, 'zdarzenie', 'zdarzenia', 'zdarzeń'),
                    'note' => $criticalIncidents.' wymaga reakcji',
                    'url' => $links['incidents'],
                    'aria_label' => 'Aktywne alarmy',
                ],
                [
                    'icon' => 'response',
                    'tone' => 'info',
                    'trend' => (string) ($summary['response_trend'] ?? '▬ bez zmian'),
                    'trend_tone' => (string) ($summary['response_trend_tone'] ?? 'neutral'),
                    'label' => 'Średni czas odpowiedzi',
                    'value' => $summary['response_value'] ?? max(0, (int) ($summary['response_ms'] ?? 0)),
                    'suffix' => ($summary['response_value'] ?? null) === '—' ? '' : 'ms',
                    'note' => (string) ($summary['response_note'] ?? 'Średnia z ostatnich pomiarów'),
                    'sparkline' => [
                        'label' => 'Trend czasu odpowiedzi',
                        'fill' => (string) ($summary['response_sparkline_fill'] ?? 'M0 9 L20 13 L40 8 L60 17 L80 15 L100 20 L120 18 L140 25 L160 21 L180 27 L180 34 L0 34Z'),
                        'line' => (string) ($summary['response_sparkline_line'] ?? 'M0 9 L20 13 L40 8 L60 17 L80 15 L100 20 L120 18 L140 25 L160 21 L180 27'),
                    ],
                ],
                [
                    'icon' => 'internet',
                    'tone' => (string) ($summary['internet_tone'] ?? (($summary['internet_value'] ?? null) === '—' ? 'neutral' : ($internetUptime >= 99 ? 'success' : ($internetUptime >= 95 ? 'warning' : 'danger')))),
                    'trend' => (string) ($summary['internet_trend'] ?? '▬ bez zmian'),
                    'trend_tone' => (string) ($summary['internet_trend_tone'] ?? 'neutral'),
                    'label' => 'Internet',
                    'value' => $summary['internet_value'] ?? self::number($internetUptime),
                    'suffix' => ($summary['internet_value'] ?? null) === '—' ? '' : '%',
                    'note' => (string) ($summary['internet_note'] ?? 'Bieżąca dostępność'),
                    'sparkline' => [
                        'label' => 'Trend stabilności Internetu',
                        'fill' => (string) ($summary['internet_sparkline_fill'] ?? 'M0 19 L20 18 L40 19 L60 17 L80 18 L100 16 L120 17 L140 15 L160 16 L180 14 L180 34 L0 34Z'),
                        'line' => (string) ($summary['internet_sparkline_line'] ?? 'M0 19 L20 18 L40 19 L60 17 L80 18 L100 16 L120 17 L140 15 L160 16 L180 14'),
                    ],
                ],
                [
                    'icon' => 'smart',
                    'tone' => (string) ($summary['smart_tone'] ?? ($smartWarnings > 0 ? 'warning' : 'success')),
                    'trend' => (string) ($summary['smart_trend'] ?? '▬ bez zmian'),
                    'trend_tone' => $smartWarnings > 0 ? 'bad' : 'neutral',
                    'label' => 'SMART',
                    'value' => $summary['smart_value'] ?? $smartHealthy,
                    'suffix' => (string) ($summary['smart_suffix'] ?? 'sprawne'),
                    'note' => (string) ($summary['smart_note'] ?? ($smartWarnings.' ostrzeżeń')),
                    'url' => $links['smart'],
                    'aria_label' => 'Szczegóły SMART',
                ],
                [
                    'icon' => 'windows',
                    'tone' => (string) ($summary['windows_tone'] ?? ($windowsReporting === $windowsHosts ? 'success' : 'warning')),
                    'trend' => (string) ($summary['windows_trend'] ?? '▬ bez zmian'),
                    'trend_tone' => 'neutral',
                    'label' => 'Windows',
                    'value' => $summary['windows_value'] ?? $windowsHosts,
                    'suffix' => (string) ($summary['windows_suffix'] ?? 'hosty'),
                    'note' => (string) ($summary['windows_note'] ?? ($windowsReporting.' raportuje')),
                    'url' => $links['windows'],
                    'aria_label' => 'Szczegóły Windows',
                ],
                [
                    'icon' => 'service',
                    'tone' => (string) ($summary['services_tone'] ?? ($servicesActive === $servicesTotal ? 'success' : 'warning')),
                    'trend' => (string) ($summary['services_trend'] ?? '▬ bez zmian'),
                    'trend_tone' => (string) ($summary['services_trend_tone'] ?? 'neutral'),
                    'label' => 'Usługi Windows',
                    'value' => $summary['services_value'] ?? $servicesActive,
                    'suffix' => (string) ($summary['services_suffix'] ?? 'aktywne'),
                    'note' => (string) ($summary['services_note'] ?? ($servicesTotal > 0 ? self::percent(round(($servicesActive / $servicesTotal) * 100, 1)).' monitorowanych' : 'Brak usług')),
                    'url' => $links['services'],
                    'aria_label' => 'Szczegóły usług Windows',
                ],
            ],
            'devices' => array_values((array) ($summary['devices'] ?? [])),
            'incidents' => array_values((array) ($summary['incidents'] ?? [])),
            'facilities' => array_values((array) ($summary['facilities'] ?? [])),
        ];
    }

    private static function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }

    private static function scoreTone(int $score): string
    {
        return match (true) {
            $score >= 90 => 'success',
            $score >= 70 => 'warning',
            default => 'danger',
        };
    }

    private static function scoreLabel(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Stan bardzo dobry',
            $score >= 80 => 'Stan dobry',
            $score >= 70 => 'Stan wymaga uwagi',
            default => 'Stan wymaga reakcji',
        };
    }

    private static function percent(float $value): string
    {
        return number_format($value, 1, ',', ' ').'%';
    }

    private static function number(float $value): string
    {
        $decimals = floor($value) === $value ? 0 : 1;

        return number_format($value, $decimals, ',', ' ');
    }

    private static function polishCount(int $count, string $one, string $few, string $many): string
    {
        if ($count === 1) {
            return $one;
        }

        $lastTwo = $count % 100;
        $last = $count % 10;

        if ($last >= 2 && $last <= 4 && !($lastTwo >= 12 && $lastTwo <= 14)) {
            return $few;
        }

        return $many;
    }
}
