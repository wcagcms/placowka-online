@php
    $wu = (array) ($device->windows_update ?? []);
    $security = (array) ($device->defender_status ?? []);
    $firewalls = collect($security['firewall_profiles'] ?? []);
    $products = collect($security['antivirus_products'] ?? []);
    $activeProducts = $products->filter(fn($product) => data_get($product, 'enabled') === true);
    $policyLabels = [
        'auto' => 'Automatyczne wykrywanie',
        'microsoft_defender' => 'Wymagany Microsoft Defender',
        'third_party' => 'Wymagany zewnętrzny antywirus',
    ];
@endphp
<section class="po-security-grid" aria-labelledby="windows-security-title">
    <h2 id="windows-security-title" class="po-security-grid-title">Windows Update i ochrona</h2>

    <article class="po-security-card">
        <h3>Windows Update</h3>
        @if(!$wu)
            <p class="po-security-state">Brak danych. Wymagany aktualny agent.</p>
        @elseif(!($wu['available'] ?? false))
            <p class="po-security-state po-security-state--warning">Moduł niedostępny: {{ $wu['error'] ?? 'brak szczegółów' }}</p>
        @else
            <dl>
                <div><dt>Oczekujące aktualizacje</dt><dd>{{ $wu['pending_updates_count'] ?? 0 }}</dd></div>
                <div><dt>Krytyczne</dt><dd>{{ $wu['pending_critical_count'] ?? 0 }}</dd></div>
                <div><dt>Bezpieczeństwa</dt><dd>{{ $wu['pending_security_count'] ?? 0 }}</dd></div>
                <div><dt>Wymagany restart</dt><dd>{{ ($wu['restart_required'] ?? false) ? 'Tak' : 'Nie' }}</dd></div>
                <div><dt>Ostatnia aktualizacja</dt><dd>{{ !empty($wu['last_installed_update_at']) ? \Carbon\Carbon::parse($wu['last_installed_update_at'])->format('d.m.Y H:i') : 'Brak danych' }}</dd></div>
            </dl>
        @endif
    </article>

    <article class="po-security-card">
        <h3>Ochrona antywirusowa</h3>
        <p class="po-security-state">
            Polityka: {{ $policyLabels[$device->antivirus_policy ?? 'auto'] ?? 'Automatyczne wykrywanie' }}
            @if(($device->antivirus_policy ?? 'auto') === 'third_party' && $device->expected_antivirus_provider)
                — oczekiwany dostawca: {{ $device->expected_antivirus_provider }}
            @endif
        </p>

        @if(!$security)
            <p class="po-security-state">Brak danych. Wymagany agent 1.9.2.</p>
        @elseif(!($security['available'] ?? false))
            <p class="po-security-state po-security-state--warning">Moduł niedostępny: {{ $security['error'] ?? 'brak szczegółów' }}</p>
        @else
            <dl>
                <div>
                    <dt>Aktywny dostawca</dt>
                    <dd>
                        {{ $activeProducts->pluck('display_name')->filter()->implode(', ') ?: 'Nie potwierdzono' }}
                    </dd>
                </div>
                <div><dt>Tryb Defendera</dt><dd>{{ $security['am_running_mode'] ?? 'Brak danych' }}</dd></div>
                <div><dt>Defender — ochrona w czasie rzeczywistym</dt><dd>{{ ($security['real_time_protection_enabled'] ?? false) ? 'Włączona' : 'Wyłączona lub pasywna' }}</dd></div>
                <div><dt>Aktywne zagrożenia</dt><dd>{{ $security['active_threat_count'] ?? 0 }}</dd></div>
                <div><dt>Zapora</dt><dd>{{ $firewalls->isNotEmpty() && $firewalls->every(fn($profile) => (bool)($profile['enabled'] ?? false)) ? 'Włączona dla wszystkich profili' : 'Wymaga sprawdzenia' }}</dd></div>
            </dl>

            @if($products->isNotEmpty())
                <h4>Produkty zarejestrowane w Windows Security Center</h4>
                <ul>
                    @foreach($products as $product)
                        <li>
                            <strong>{{ data_get($product, 'display_name', 'Nieznany produkt') }}</strong>:
                            {{ data_get($product, 'enabled') === true ? 'aktywny' : 'nieaktywny' }},
                            {{ data_get($product, 'up_to_date') === false ? 'wymaga aktualizacji' : 'aktualny lub brak ostrzeżenia' }}
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </article>
</section>
