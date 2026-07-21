<?php

namespace App\Services;

use App\Models\AgentWindowsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AgentWindowsServiceConfigService
{
    private const CACHE_KEY = 'placowka_online.agent_windows_services';

    /**
     * Konfiguracja zapisywana do config.json nowo generowanego agenta.
     *
     * @return array<int, array{
     *   name:string,
     *   label:string,
     *   expected_status:string,
     *   alert:bool
     * }>
     */
    public function forAgent(): array
    {
        if (! Schema::hasTable('agent_windows_services')) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, 3600, function (): array {
            return AgentWindowsService::query()
                ->where('monitoring_enabled', true)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->map(fn (AgentWindowsService $service): array => [
                    'name' => $service->system_name,
                    'label' => $service->label,
                    'expected_status' => $service->expected_status,
                    'alert' => $service->alert_enabled,
                ])
                ->values()
                ->all();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
