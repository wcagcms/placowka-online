@extends('layouts.panel')

@section('title', 'Centrum monitoringu — Placówka Online')
@section('eyebrow', 'Monitoring na żywo')
@section('page_title', 'Centrum monitoringu')
@section('page_lead', 'Jedno miejsce do szybkiej oceny stanu wszystkich placówek, urządzeń i aktywnych incydentów.')

@push('head')
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-observability.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-wow.css') }}">
@endpush

@section('page_actions')
    <a class="btn secondary" href="{{ route('dashboard') }}">Panel główny</a>
    <button class="btn" type="button" id="monitoring-refresh-button">
        Odśwież dane
    </button>
@endsection

@section('content')
<div class="pso-page"
     data-monitoring-center
     data-wow-monitoring
     data-refresh-interval="30"
     data-snapshot-url="{{ route('monitoring-center.snapshot') }}">

    <section class="pso-control-panel" aria-labelledby="pso-monitoring-controls-title">
        <div class="pso-control-panel__intro">
            <span class="pso-icon-box" aria-hidden="true">
                <x-platinum.icon name="internet" :size="24" />
            </span>
            <div>
                <p class="pso-kicker">Podgląd operacyjny</p>
                <h2 id="pso-monitoring-controls-title">Filtry i odświeżanie</h2>
                <p>Znajdź placówkę po nazwie lub kodzie i ogranicz widok do wybranego stanu.</p>
            </div>
        </div>

        <div class="pso-filter-grid">
            <div class="pso-field">
                <label for="monitoring-search">Szukaj placówki</label>
                <input
                    id="monitoring-search"
                    type="search"
                    placeholder="np. PP10 lub Głogów"
                    autocomplete="off"
                    data-monitoring-search
                >
            </div>

            <div class="pso-field">
                <label for="monitoring-status-filter">Stan placówki</label>
                <select id="monitoring-status-filter" data-monitoring-status-filter>
                    <option value="all">Wszystkie stany</option>
                    <option value="online">Online</option>
                    <option value="warning">Ostrzeżenie</option>
                    <option value="offline">Awaria</option>
                    <option value="inactive">Nieaktywna</option>
                    <option value="unknown">Brak danych</option>
                </select>
            </div>

            <label class="pso-switch-card" for="monitoring-auto-refresh">
                <span>
                    <strong>Odświeżanie automatyczne</strong>
                    <small id="monitoring-refresh-state">Włączone co 30 sekund</small>
                </span>
                <span class="pso-switch">
                    <input type="checkbox" id="monitoring-auto-refresh" checked>
                    <span aria-hidden="true"></span>
                </span>
            </label>
        </div>

        <div class="pso-refresh-meta">
            <span>
                Ostatnia aktualizacja:
                <time id="monitoring-generated-at" datetime="{{ $generatedAt->toIso8601String() }}">
                    {{ $generatedAt->timezone('Europe/Warsaw')->format('H:i:s') }}
                </time>
            </span>
            <span id="monitoring-filter-result" aria-live="polite"></span>
        </div>
    </section>

    <div id="monitoring-live-message" class="sr-only" role="status" aria-live="polite"></div>

    <div id="monitoring-snapshot">
        @include('monitoring-center.partials.snapshot')
    </div>
</div>

<div class="psw-drawer-backdrop" data-wow-drawer-backdrop hidden></div>
<aside class="psw-drawer"
       data-wow-device-drawer
       aria-labelledby="psw-device-drawer-title"
       aria-hidden="true"
       hidden>
    <header class="psw-drawer__header">
        <div>
            <p class="pso-kicker">Szybki podgląd</p>
            <h2 id="psw-device-drawer-title" data-wow-drawer-name>Urządzenie</h2>
            <p data-wow-drawer-facility>Placówka</p>
        </div>
        <button class="psw-drawer__close" type="button" data-wow-drawer-close aria-label="Zamknij szybki podgląd">
            <span aria-hidden="true">×</span>
        </button>
    </header>

    <div class="psw-drawer__content">
        <div class="psw-drawer__state">
            <span class="psw-drawer__state-dot" data-wow-drawer-dot aria-hidden="true"></span>
            <div>
                <span>Stan urządzenia</span>
                <strong data-wow-drawer-status>Brak danych</strong>
            </div>
        </div>

        <dl class="psw-drawer__facts">
            <div>
                <dt>Ostatni kontakt</dt>
                <dd data-wow-drawer-last-seen>Brak danych</dd>
            </div>
            <div>
                <dt>Adres IP</dt>
                <dd data-wow-drawer-ip>Brak danych</dd>
            </div>
            <div>
                <dt>Opóźnienie</dt>
                <dd data-wow-drawer-latency>Brak danych</dd>
            </div>
            <div>
                <dt>Wersja agenta</dt>
                <dd data-wow-drawer-agent>Brak danych</dd>
            </div>
        </dl>

        <div class="psw-drawer__notice" data-wow-drawer-notice>
            Dane zostaną pokazane po wybraniu urządzenia.
        </div>
    </div>

    <footer class="psw-drawer__footer">
        <a class="btn" href="#" data-wow-drawer-link>Pełne szczegóły urządzenia</a>
        <button class="btn secondary" type="button" data-wow-drawer-close>Zamknij</button>
    </footer>
</aside>
@endsection

@push('scripts')
    <script src="{{ asset('panel/saas-platinum-observability.js') }}" defer></script>
    <script src="{{ asset('panel/saas-platinum-wow.js') }}" defer></script>
@endpush
