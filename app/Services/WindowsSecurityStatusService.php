<?php

namespace App\Services;

use App\Models\Device;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class WindowsSecurityStatusService
{
    public function __construct(
        private readonly IncidentLifecycleService $incidents
    ) {
    }

    public function process(
        Device $device,
        ?array $windowsUpdate,
        ?array $defender,
        IncidentNotificationService $notifications
    ): void {
        if (is_array($windowsUpdate) && $windowsUpdate !== []) {
            $this->processWindowsUpdate($device, $windowsUpdate, $notifications);
        }

        if (is_array($defender) && $defender !== []) {
            $this->processEndpointProtection($device, $defender, $notifications);
        }
    }

    private function processWindowsUpdate(
        Device $device,
        array $status,
        IncidentNotificationService $notifications
    ): void {
        $available = (bool) data_get($status, 'available', false);

        if (! $available) {
            return;
        }

        $pending = max(0, (int) data_get($status, 'pending_updates_count', 0));
        $critical = max(0, (int) data_get($status, 'pending_critical_count', 0));
        $security = max(0, (int) data_get($status, 'pending_security_count', 0));
        $restartRequired = (bool) data_get($status, 'restart_required', false);
        $lastInstalledAt = $this->parseDate(data_get($status, 'last_installed_update_at'));
        $staleDays = max(7, (int) config('placowka.windows_update_stale_days', 45));
        $stale = $lastInstalledAt === null || $lastInstalledAt->lt(now()->subDays($staleDays));

        $requiresAttention = $critical > 0
            || $security > 0
            || $stale
            || ($restartRequired && $pending > 0);

        if (! $requiresAttention) {
            $this->incidents->resolve(
                $device,
                'windows_update_attention',
                'Stan Windows Update wrócił do wartości prawidłowych.',
                $notifications
            );

            return;
        }

        $reasons = [];

        if ($critical > 0) {
            $reasons[] = $critical.' aktualizacji krytycznych';
        }

        if ($security > 0) {
            $reasons[] = $security.' aktualizacji zabezpieczeń';
        }

        if ($stale) {
            $reasons[] = $lastInstalledAt
                ? 'ostatnia instalacja aktualizacji ponad '.$staleDays.' dni temu'
                : 'brak daty ostatniej zainstalowanej aktualizacji';
        }

        if ($restartRequired) {
            $reasons[] = 'wymagany restart systemu';
        }

        $priority = $critical > 0 || $security > 0 ? 'high' : 'medium';

        $this->incidents->openOrTouch(
            $device,
            'windows_update_attention',
            'Windows Update wymaga uwagi: '.implode(', ', $reasons).'.',
            $priority,
            $notifications,
            [
                'pending_updates_count' => $pending,
                'pending_critical_count' => $critical,
                'pending_security_count' => $security,
                'restart_required' => $restartRequired,
                'last_installed_update_at' => data_get($status, 'last_installed_update_at'),
            ]
        );
    }

    private function processEndpointProtection(
        Device $device,
        array $status,
        IncidentNotificationService $notifications
    ): void {
        if (! (bool) data_get($status, 'available', false)) {
            return;
        }

        $policy = in_array(
            $device->antivirus_policy,
            ['auto', 'microsoft_defender', 'third_party'],
            true
        ) ? $device->antivirus_policy : 'auto';

        $expectedProvider = trim((string) $device->expected_antivirus_provider);
        $products = collect(data_get($status, 'antivirus_products', []))
            ->filter(fn (mixed $product): bool => is_array($product))
            ->values();

        $activeProducts = $products
            ->filter(fn (array $product): bool => data_get($product, 'enabled') === true)
            ->values();

        $activeThirdParty = $activeProducts
            ->reject(fn (array $product): bool => $this->isMicrosoftProvider(
                (string) data_get($product, 'display_name', '')
            ))
            ->values();

        $antivirusEnabled = data_get($status, 'antivirus_enabled');
        $realTimeEnabled = data_get($status, 'real_time_protection_enabled');
        $serviceEnabled = data_get($status, 'antimalware_service_enabled');
        $signatureAge = data_get($status, 'signature_age_days');
        $activeThreats = max(0, (int) data_get($status, 'active_threat_count', 0));
        $runningMode = trim((string) data_get($status, 'am_running_mode', ''));

        $defenderActive = $antivirusEnabled === true
            && $realTimeEnabled === true
            && $serviceEnabled !== false;

        $reasons = [];
        $providerMeta = [
            'policy' => $policy,
            'expected_provider' => $expectedProvider !== '' ? $expectedProvider : null,
            'am_running_mode' => $runningMode !== '' ? $runningMode : null,
            'detected_products' => $products->all(),
        ];

        if ($policy === 'microsoft_defender') {
            $this->appendDefenderReasons(
                $reasons,
                $antivirusEnabled,
                $serviceEnabled,
                $realTimeEnabled,
                $signatureAge
            );
        } elseif ($policy === 'third_party') {
            $matchingProducts = $products
                ->filter(fn (array $product): bool => $expectedProvider !== ''
                    && Str::contains(
                        Str::lower((string) data_get($product, 'display_name', '')),
                        Str::lower($expectedProvider)
                    ))
                ->values();

            $matchingActive = $matchingProducts
                ->first(fn (array $product): bool => data_get($product, 'enabled') === true);

            if (! is_array($matchingActive)) {
                $reasons[] = $matchingProducts->isEmpty()
                    ? 'oczekiwany program '.$expectedProvider.' nie został wykryty'
                    : 'oczekiwany program '.$expectedProvider.' nie jest aktywny';
            } elseif (data_get($matchingActive, 'up_to_date') === false) {
                $reasons[] = 'program '.$expectedProvider.' wymaga aktualizacji';
            }
        } elseif ($activeThirdParty->isNotEmpty()) {
            $outdatedProducts = $activeThirdParty
                ->filter(fn (array $product): bool => data_get($product, 'up_to_date') === false)
                ->pluck('display_name')
                ->filter()
                ->values();

            if ($outdatedProducts->isNotEmpty()) {
                $reasons[] = 'nieaktualna ochrona: '.$outdatedProducts->implode(', ');
            }
        } elseif ($defenderActive) {
            $this->appendDefenderReasons(
                $reasons,
                $antivirusEnabled,
                $serviceEnabled,
                $realTimeEnabled,
                $signatureAge
            );
        } else {
            $reasons[] = 'Windows Security Center nie potwierdza aktywnego programu antywirusowego';
        }

        if ($activeThreats > 0) {
            $reasons[] = $activeThreats.' aktywnych wykryć zagrożeń';
        }

        $firewallProfiles = collect(data_get($status, 'firewall_profiles', []));
        $disabledFirewalls = $firewallProfiles
            ->filter(fn (mixed $profile): bool => is_array($profile)
                && data_get($profile, 'enabled') === false)
            ->pluck('name')
            ->filter()
            ->values();

        if ($disabledFirewalls->isNotEmpty()) {
            $reasons[] = 'wyłączona zapora: '.$disabledFirewalls->implode(', ');
        }

        if ($reasons === []) {
            $activeProviderNames = $activeProducts
                ->pluck('display_name')
                ->filter()
                ->unique()
                ->values()
                ->implode(', ');

            $summary = $activeProviderNames !== ''
                ? 'Ochrona antywirusowa działa prawidłowo. Aktywny dostawca: '.$activeProviderNames.'.'
                : 'Ochrona antywirusowa i Zapora systemu Windows działają prawidłowo.';

            $this->incidents->resolve(
                $device,
                'defender_problem',
                $summary,
                $notifications
            );

            return;
        }

        $priority = $activeThreats > 0
            || Str::contains(implode(' ', $reasons), [
                'nie został wykryty',
                'nie jest aktywny',
                'nie potwierdza aktywnego',
            ])
                ? 'critical'
                : 'high';

        $this->incidents->openOrTouch(
            $device,
            'defender_problem',
            'Ochrona urządzenia wymaga reakcji: '.implode(', ', $reasons).'.',
            $priority,
            $notifications,
            array_merge($providerMeta, [
                'antivirus_enabled' => $antivirusEnabled,
                'real_time_protection_enabled' => $realTimeEnabled,
                'signature_age_days' => $signatureAge,
                'active_threat_count' => $activeThreats,
                'disabled_firewall_profiles' => $disabledFirewalls->all(),
            ])
        );
    }

    private function appendDefenderReasons(
        array &$reasons,
        mixed $antivirusEnabled,
        mixed $serviceEnabled,
        mixed $realTimeEnabled,
        mixed $signatureAge
    ): void {
        $maxSignatureAge = max(
            1,
            (int) config('placowka.defender_max_signature_age_days', 3)
        );

        if ($antivirusEnabled === false) {
            $reasons[] = 'Microsoft Defender jest wyłączony';
        }

        if ($serviceEnabled === false) {
            $reasons[] = 'usługa Microsoft Defender nie działa';
        }

        if ($realTimeEnabled === false) {
            $reasons[] = 'ochrona Microsoft Defender w czasie rzeczywistym jest wyłączona';
        }

        if (is_numeric($signatureAge) && (int) $signatureAge > $maxSignatureAge) {
            $reasons[] = 'sygnatury Microsoft Defender mają '.(int) $signatureAge.' dni';
        }
    }

    private function isMicrosoftProvider(string $name): bool
    {
        $normalized = Str::lower($name);

        return Str::contains($normalized, [
            'microsoft defender',
            'windows defender',
        ]);
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
