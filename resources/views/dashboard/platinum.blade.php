@extends('layouts.platinum')

@section('title', data_get($dashboard, 'page_title', 'Placówka Online — SaaS Platinum'))

@section('body')
@php
    $institution = data_get($dashboard, 'institution', []);
    $user = data_get($dashboard, 'user', []);
    $agent = data_get($dashboard, 'agent', []);
    $links = data_get($dashboard, 'links', []);
    $metrics = data_get($dashboard, 'metrics', []);
    $devices = data_get($dashboard, 'devices', []);
    $incidents = data_get($dashboard, 'incidents', []);
    $healthScore = (int) data_get($institution, 'health_score', 0);
    $healthTone = data_get($institution, 'health_tone', 'danger');
@endphp

<a class="skip-link" href="#main-content">Przejdź do głównej treści</a>

<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Główna nawigacja">
        <a class="brand" href="{{ data_get($links, 'dashboard', '#main-content') }}" aria-label="Placówka Online — strona główna">
            <span class="brand-mark" aria-hidden="true"><x-platinum.icon name="home" :size="23" /></span>
            <span class="brand-copy"><strong>Placówka Online</strong><span>Monitoring infrastruktury</span></span>
        </a>

        <p class="nav-label">Monitoring</p>
        <ul class="nav-list">
            <li><a class="nav-link" href="{{ data_get($links, 'dashboard', '#main-content') }}" aria-current="page" data-close-mobile-nav><x-platinum.icon name="dashboard" class="nav-icon" />Dashboard</a></li>
            <li><a class="nav-link" href="{{ data_get($links, 'devices', '#devices') }}" data-close-mobile-nav><x-platinum.icon name="device" class="nav-icon" />Urządzenia</a></li>
            <li><a class="nav-link" href="{{ data_get($links, 'incidents', '#incidents') }}" data-close-mobile-nav><x-platinum.icon name="alarm" class="nav-icon" />Alarmy <span class="nav-count" aria-label="{{ data_get($institution, 'active_incidents', count($incidents)) }} aktywnych alarmów">{{ data_get($institution, 'active_incidents', count($incidents)) }}</span></a></li>
            <li><a class="nav-link" href="{{ data_get($links, 'history', '#') }}" data-close-mobile-nav><x-platinum.icon name="history" class="nav-icon" />Historia</a></li>
        </ul>

        <p class="nav-label">Zarządzanie</p>
        <ul class="nav-list">
            <li><a class="nav-link" href="{{ data_get($links, 'settings', '#') }}" data-close-mobile-nav><x-platinum.icon name="settings" class="nav-icon" />Konfiguracja</a></li>
            <li><a class="nav-link" href="{{ data_get($links, 'users', '#') }}" data-close-mobile-nav><x-platinum.icon name="users" class="nav-icon" />Użytkownicy</a></li>
        </ul>

        <div class="sidebar-footer">
            <strong>Agent Windows {{ data_get($agent, 'version', '—') }}</strong>
            <p>{{ data_get($agent, 'message') }}</p>
            <a class="mini-button" href="{{ data_get($links, 'agents', '#') }}">Sprawdź agentów</a>
        </div>
    </aside>

    <main class="main" id="main-content" tabindex="-1">
        <header class="topbar">
            <div class="topbar-heading">
                <button class="menu-button" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Otwórz menu" id="menuButton"><x-platinum.icon name="menu" /></button>
                <div class="breadcrumbs" aria-label="Okruszki">Panel / Dashboard placówki</div>
            </div>
            <div class="topbar-actions">
                <a class="icon-button" href="{{ data_get($links, 'incidents', '#incidents') }}" aria-label="Powiadomienia, {{ data_get($institution, 'active_incidents', count($incidents)) }} aktywne"><x-platinum.icon name="bell" /></a>
                <div class="profile-button profile-button-static"><span class="avatar" aria-hidden="true">{{ data_get($user, 'initials', 'AD') }}</span><span>{{ data_get($user, 'name', 'Administrator') }}</span></div>
            </div>
        </header>

        <section class="hero" aria-labelledby="dashboard-title">
            <div>
                <div class="eyebrow"><span class="eyebrow-dot {{ data_get($institution, 'system_tone', 'success') }}" aria-hidden="true"></span>{{ data_get($institution, 'system_status') }}</div>
                <h1 id="dashboard-title">{{ data_get($institution, 'name') }}</h1>
                <p class="hero-lead">{{ data_get($institution, 'description') }}</p>
                <div class="hero-statuses" aria-label="Podsumowanie stanu urządzeń">
                    <span class="status-chip success"><span class="status-symbol" aria-hidden="true">●</span>{{ data_get($institution, 'online', 0) }} {{ data_get($institution, 'online_label', 'urządzeń online') }}</span>
                    <span class="status-chip warning"><span class="status-symbol" aria-hidden="true">▲</span>{{ data_get($institution, 'warnings', 0) }} {{ data_get($institution, 'warnings_label', 'ostrzeżeń') }}</span>
                    <span class="status-chip danger"><span class="status-symbol" aria-hidden="true">!</span>{{ data_get($institution, 'failures', 0) }} {{ data_get($institution, 'failures_label', 'awarii') }}</span>
                </div>
            </div>
            <div class="health-panel">
                <div class="gauge gauge-value-{{ $healthScore }}" data-gauge-value="{{ $healthScore }}" data-gauge-tone="{{ $healthTone }}" role="img" aria-label="Health Score: {{ $healthScore }} na 100">
                    <div class="gauge-content"><span class="gauge-value">{{ $healthScore }}</span><span class="gauge-label">na 100</span></div>
                </div>
                <div class="health-copy">
                    <strong>{{ data_get($institution, 'health_label') }}</strong>
                    <span>Wiarygodność danych: {{ data_get($institution, 'reliability') }}</span>
                    <a href="{{ data_get($links, 'health', '#') }}">Zobacz zalecenia <span aria-hidden="true">→</span></a>
                </div>
            </div>
        </section>

        <div class="section-heading">
            <div><h2>Przegląd infrastruktury</h2><p>Aktualne dane z ostatniego heartbeat urządzeń.</p></div>
            <span class="section-link">Odświeżono {{ data_get($dashboard, 'last_refresh', 'przed chwilą') }}</span>
        </div>

        <section class="metric-grid" aria-label="Kluczowe wskaźniki">
            @foreach($metrics as $metric)
                <x-platinum.metric-card :metric="$metric" />
            @endforeach
        </section>

        <div class="section-heading" id="devices">
            <div><h2>Urządzenia wymagające uwagi</h2><p>Priorytetowa lista komputerów i usług z odchyleniami.</p></div>
            <a class="section-link" href="{{ data_get($links, 'devices', '#devices') }}">Zobacz wszystkie urządzenia</a>
        </div>

        <div class="content-grid">
            <section class="panel" aria-labelledby="device-list-title">
                <div class="panel-header">
                    <div><h2 id="device-list-title">Ostatnia aktywność urządzeń</h2><p>Na urządzeniach mobilnych dane pozostają w układzie kart.</p></div>
                    <a class="mini-button" href="{{ data_get($links, 'export', '#') }}">Eksportuj</a>
                </div>
                <div class="device-list">
                    @forelse($devices as $device)
                        <x-platinum.device-card :device="$device" />
                    @empty
                        <div class="empty-state">Brak urządzeń wymagających uwagi.</div>
                    @endforelse
                </div>
            </section>

            <section class="panel" id="incidents" aria-labelledby="incidents-title">
                <div class="panel-header">
                    <div><h2 id="incidents-title">Aktywne incydenty</h2><p>Najważniejsze zdarzenia do sprawdzenia.</p></div>
                    <span class="status-chip {{ data_get($institution, 'active_incidents', count($incidents)) > 0 ? 'danger' : 'success' }} incident-count">{{ data_get($institution, 'active_incidents', count($incidents)) }} aktywne</span>
                </div>
                <div class="incident-list">
                    @forelse($incidents as $incident)
                        <x-platinum.incident-card :incident="$incident" />
                    @empty
                        <div class="empty-state">Brak aktywnych incydentów.</div>
                    @endforelse
                </div>
                <a class="primary-button incidents-button" href="{{ data_get($links, 'incidents', '#incidents') }}">Przejdź do centrum alarmów</a>
            </section>
        </div>
    </main>
</div>

<nav class="mobile-bottom-nav" aria-label="Nawigacja mobilna">
    <a class="mobile-nav-link" href="{{ data_get($links, 'dashboard', '#main-content') }}" aria-current="page"><x-platinum.icon name="dashboard" />Dashboard</a>
    <a class="mobile-nav-link" href="{{ data_get($links, 'devices', '#devices') }}"><x-platinum.icon name="device" />Urządzenia</a>
    <a class="mobile-nav-link" href="{{ data_get($links, 'incidents', '#incidents') }}"><x-platinum.icon name="alarm" />Alarmy</a>
    <a class="mobile-nav-link" href="{{ data_get($links, 'settings', '#') }}"><x-platinum.icon name="settings" />Więcej</a>
</nav>
@endsection
