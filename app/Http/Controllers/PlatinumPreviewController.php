<?php

namespace App\Http\Controllers;

use App\Support\PlatinumDashboardFactory;
use Illuminate\Contracts\View\View;

final class PlatinumPreviewController extends Controller
{
    public function __invoke(): View
    {
        $userName = (string) (auth()->user()?->name ?? 'Administrator');

        return view('dashboard.platinum', [
            'dashboard' => PlatinumDashboardFactory::make([
                'institution_name' => 'Placówka nr 10',
                'health_score' => 97,
                'reliability' => 'wysoka',
                'devices_online' => 41,
                'devices_total' => 44,
                'warnings' => 2,
                'failures' => 1,
                'active_incidents' => 3,
                'critical_incidents' => 1,
                'response_ms' => 28,
                'internet_uptime' => 99.8,
                'smart_healthy' => 43,
                'smart_warnings' => 1,
                'windows_hosts' => 44,
                'windows_reporting' => 44,
                'services_active' => 126,
                'services_total' => 126,
                'health_trend' => '▲ 2 pkt',
                'health_trend_tone' => 'good',
                'response_trend' => '▼ 4 ms',
                'response_trend_tone' => 'good',
                'internet_trend' => '▲ stabilnie',
                'internet_trend_tone' => 'good',
                'smart_trend' => '▼ 1 dysk',
                'services_trend' => '▲ 1 usługa',
                'services_trend_tone' => 'good',
                'last_refresh' => '38 sekund temu',
                'user_name' => $userName,
                'user_initials' => $this->initials($userName),
                'agent_version' => '1.8.2',
                'agent_message' => 'Wszystkie urządzenia korzystają z aktualnej wersji agenta.',
                'devices' => [
                    [
                        'name' => 'SEKRETARIAT-PC01',
                        'details' => '192.168.1.14 · Windows 11 Pro',
                        'status' => 'Awaria',
                        'tone' => 'danger',
                        'message' => 'Internet niedostępny',
                        'health_score' => 72,
                        'last_seen' => '2 min',
                        'url' => '#',
                    ],
                    [
                        'name' => 'KSIĘGOWOŚĆ-PC02',
                        'details' => '192.168.1.21 · Windows 10 Pro',
                        'status' => 'Ostrzeżenie',
                        'tone' => 'warning',
                        'message' => 'SSD: 18% żywotności',
                        'health_score' => 83,
                        'last_seen' => '38 s',
                        'url' => '#',
                    ],
                    [
                        'name' => 'DYREKTOR-PC01',
                        'details' => '192.168.1.10 · Windows 11 Pro',
                        'status' => 'Online',
                        'tone' => 'success',
                        'message' => 'Wszystkie moduły OK',
                        'health_score' => 99,
                        'last_seen' => '24 s',
                        'url' => '#',
                    ],
                ],
                'incidents' => [
                    [
                        'title' => 'Brak dostępu do Internetu',
                        'description' => 'SEKRETARIAT-PC01 nie odpowiada na testy bramy i DNS.',
                        'priority' => 'krytyczny',
                        'duration' => 'od 2 min',
                        'tone' => 'danger',
                        'symbol' => '!',
                    ],
                    [
                        'title' => 'Niska żywotność SSD',
                        'description' => 'KSIĘGOWOŚĆ-PC02 raportuje 18% pozostałej żywotności.',
                        'priority' => 'wysoki',
                        'duration' => 'od 18 min',
                        'tone' => 'warning',
                        'symbol' => '▲',
                    ],
                    [
                        'title' => 'Usługa zatrzymana',
                        'description' => 'Usługa Windows Update na SALA-03-PC01 nie działa.',
                        'priority' => 'średni',
                        'duration' => 'od 46 min',
                        'tone' => 'warning',
                        'symbol' => '▲',
                    ],
                ],
            ]),
        ]);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(
            static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)),
            array_slice($parts, 0, 2),
        );

        return implode('', $letters) ?: 'AD';
    }
}
