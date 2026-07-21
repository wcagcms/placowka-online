@extends('layouts.panel')

@section('title', 'Panel główny — Placówka Online')
@section('eyebrow', 'SaaS Platinum')
@section('page_title', 'Panel główny')
@section('page_lead', 'Najważniejsze informacje o stanie wszystkich placówek i urządzeń.')

@push('head')
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-dashboard.css') }}">
@endpush

@section('page_actions')
    @if(auth()->user()?->isAdmin())
        <a class="btn" href="{{ route('facilities.create') }}">Dodaj placówkę</a>
    @endif
    <a class="btn secondary" href="{{ route('dashboard') }}">Odśwież dane</a>
@endsection

@section('content')
@php
    $institution = data_get($dashboard, 'institution', []);
    $agent = data_get($dashboard, 'agent', []);
    $links = data_get($dashboard, 'links', []);
    $metrics = data_get($dashboard, 'metrics', []);
    $facilityCards = data_get($dashboard, 'facilities', []);
    $devices = data_get($dashboard, 'devices', []);
    $incidents = data_get($dashboard, 'incidents', []);
    $healthScore = is_numeric(data_get($institution, 'health_score'))
        ? max(0, min(100, (int) data_get($institution, 'health_score')))
        : null;
    $healthTone = data_get($institution, 'health_tone', 'warning');
@endphp

<div class="sp-dashboard">
    <section class="sp-hero" aria-labelledby="sp-dashboard-title">
        <div class="sp-hero-copy">
            <div class="sp-eyebrow">
                <span class="sp-eyebrow-dot is-{{ data_get($institution, 'system_tone', 'success') }}" aria-hidden="true"></span>
                {{ data_get($institution, 'system_status', 'Stan systemu') }}
            </div>

            <h2 id="sp-dashboard-title">{{ data_get($institution, 'name', 'Placówka Online') }}</h2>
            <p>{{ data_get($institution, 'description') }}</p>

            <div class="sp-status-list" aria-label="Podsumowanie stanu urządzeń">
                <span class="sp-status is-success">
                    <span aria-hidden="true">●</span>
                    {{ data_get($institution, 'online', 0) }} {{ data_get($institution, 'online_label', 'urządzeń online') }}
                </span>
                <span class="sp-status is-warning">
                    <span aria-hidden="true">▲</span>
                    {{ data_get($institution, 'warnings', 0) }} {{ data_get($institution, 'warnings_label', 'ostrzeżeń') }}
                </span>
                <span class="sp-status is-danger">
                    <span aria-hidden="true">!</span>
                    {{ data_get($institution, 'failures', 0) }} {{ data_get($institution, 'failures_label', 'awarii') }}
                </span>
            </div>
        </div>

        <div class="sp-health-panel">
            <div class="sp-gauge {{ $healthScore !== null ? 'sp-gauge-value-'.$healthScore : 'sp-gauge-value-0' }} is-{{ $healthTone }}"
                 role="img"
                 aria-label="Health Score: {{ $healthScore !== null ? $healthScore.' na 100' : 'brak bieżących danych' }}">
                <div class="sp-gauge-center">
                    <strong>{{ $healthScore !== null ? $healthScore : '—' }}</strong>
                    @if($healthScore !== null)
                        <span>na 100</span>
                    @else
                        <span>brak danych</span>
                    @endif
                </div>
            </div>

            <div class="sp-health-copy">
                <strong>{{ data_get($institution, 'health_label', 'Brak danych') }}</strong>
                <span>Wiarygodność danych: {{ data_get($institution, 'reliability', 'brak danych') }}</span>
                <a href="{{ data_get($links, 'health', route('monitoring-center.index')) }}">
                    {{ auth()->user()?->isAdmin() ? 'Sprawdź stan systemu' : 'Otwórz centrum monitoringu' }}
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </section>

    <div class="sp-section-heading">
        <div>
            <h2>Przegląd infrastruktury</h2>
            <p>Wartości obliczone z najnowszych heartbeatów aktywnych urządzeń.</p>
        </div>
        <span>Odświeżono {{ data_get($dashboard, 'last_refresh', 'przed chwilą') }}</span>
    </div>

    <section class="sp-metric-grid" aria-label="Kluczowe wskaźniki monitoringu">
        @foreach($metrics as $metric)
            @php
                $tone = data_get($metric, 'tone', 'info');
                $trendTone = data_get($metric, 'trend_tone', 'neutral');
                $sparkline = data_get($metric, 'sparkline');
            @endphp
            <article class="sp-metric is-{{ $tone }}">
                <div class="sp-metric-top">
                    <span class="sp-metric-icon" aria-hidden="true">
                        <x-platinum.icon :name="data_get($metric, 'icon', 'health')" :size="22" />
                    </span>
                    <span class="sp-trend is-{{ $trendTone }}">{{ data_get($metric, 'trend', '▬ bez zmian') }}</span>
                </div>

                <p class="sp-metric-label">{{ data_get($metric, 'label') }}</p>
                <div class="sp-metric-value">
                    <strong>{{ data_get($metric, 'value') }}</strong>
                    @if(data_get($metric, 'suffix'))
                        <span>{{ data_get($metric, 'suffix') }}</span>
                    @endif
                </div>

                @if($sparkline)
                    <svg class="sp-sparkline"
                         viewBox="0 0 180 34"
                         preserveAspectRatio="none"
                         role="img"
                         aria-label="{{ data_get($sparkline, 'label', 'Miniwykres trendu') }}">
                        <path class="sp-sparkline-fill" d="{{ data_get($sparkline, 'fill') }}"></path>
                        <path class="sp-sparkline-line" d="{{ data_get($sparkline, 'line') }}"></path>
                    </svg>
                @endif

                <div class="sp-metric-footer">
                    <span>{{ data_get($metric, 'note', 'Bieżący stan') }}</span>
                    @if(data_get($metric, 'url'))
                        <a href="{{ data_get($metric, 'url') }}"
                           aria-label="{{ data_get($metric, 'aria_label', 'Otwórz szczegóły') }}">→</a>
                    @endif
                </div>
            </article>
        @endforeach
    </section>

    <div class="sp-section-heading" id="facilities">
        <div>
            <h2>Placówki</h2>
            <p>Szybki podgląd stanu oraz dostęp do zarządzania i raportów.</p>
        </div>
        @if(auth()->user()?->isAdmin())
            <a href="{{ route('facilities.create') }}">Dodaj nową placówkę</a>
        @endif
    </div>

    <section class="sp-facility-grid" aria-label="Lista placówek">
        @forelse($facilityCards as $facility)
            @php
                $facilityTone = data_get($facility, 'offline', 0) > 0
                    ? 'danger'
                    : (data_get($facility, 'problem', 0) > 0 ? 'warning' : 'success');
            @endphp
            <article class="sp-facility is-{{ $facilityTone }}">
                <div class="sp-facility-header">
                    <div>
                        <span class="sp-facility-code">{{ data_get($facility, 'code') }}</span>
                        <h3>{{ data_get($facility, 'name') }}</h3>
                    </div>
                    <span class="sp-facility-health">
                        @if(data_get($facility, 'health_score') !== null)
                            {{ data_get($facility, 'health_score') }}/100
                        @else
                            brak danych
                        @endif
                    </span>
                </div>

                <div class="sp-facility-stats" aria-label="Stan urządzeń placówki">
                    <div><strong>{{ data_get($facility, 'devices', 0) }}</strong><span>urządzeń</span></div>
                    <div><strong>{{ data_get($facility, 'online', 0) }}</strong><span>online</span></div>
                    <div><strong>{{ data_get($facility, 'problem', 0) }}</strong><span>ostrzeżeń</span></div>
                    <div><strong>{{ data_get($facility, 'offline', 0) }}</strong><span>awarii</span></div>
                </div>

                @unless(data_get($facility, 'active', true))
                    <p class="sp-facility-inactive">Placówka jest obecnie nieaktywna.</p>
                @endunless

                <div class="sp-facility-actions">
                    <a class="sp-button is-primary" href="{{ data_get($facility, 'details_url') }}">Szczegóły</a>
                    @if(auth()->user()?->isAdmin() && data_get($facility, 'manage_url'))
                        <a class="sp-button" href="{{ data_get($facility, 'manage_url') }}">Zarządzaj</a>
                    @endif
                    <a class="sp-button" href="{{ data_get($facility, 'report_url') }}">Raport</a>
                </div>
            </article>
        @empty
            <div class="sp-empty sp-empty-wide">
                <strong>Nie dodano jeszcze żadnej placówki.</strong>
                <span>Dodaj pierwszą placówkę i wygeneruj paczkę agenta.</span>
                @if(auth()->user()?->isAdmin())
                    <a class="sp-button is-primary" href="{{ route('facilities.create') }}">Dodaj placówkę</a>
                @endif
            </div>
        @endforelse
    </section>

    <div class="sp-section-heading" id="devices">
        <div>
            <h2>Stan urządzeń</h2>
            <p>Najpierw wyświetlane są urządzenia z awariami i ostrzeżeniami.</p>
        </div>
        <a href="{{ route('monitoring-center.index') }}">Otwórz centrum monitoringu</a>
    </div>

    <div class="sp-content-grid">
        <section class="sp-panel" aria-labelledby="sp-devices-title">
            <div class="sp-panel-header">
                <div>
                    <h2 id="sp-devices-title">Ostatnia aktywność urządzeń</h2>
                    <p>Układ kart działa również na telefonach i tabletach.</p>
                </div>
                <a class="sp-button" href="{{ route('reports.index') }}">Raporty</a>
            </div>

            <div class="sp-device-list">
                @forelse($devices as $device)
                    <article class="sp-device">
                        <div class="sp-device-main">
                            <span class="sp-device-icon" aria-hidden="true">
                                <x-platinum.icon name="device" :size="20" />
                            </span>
                            <div>
                                <h3>{{ data_get($device, 'name', 'Nieznane urządzenie') }}</h3>
                                <p>{{ data_get($device, 'details', 'Brak danych') }}</p>
                            </div>
                        </div>

                        <div class="sp-device-cell">
                            <strong class="sp-device-state is-{{ data_get($device, 'tone', 'warning') }}">
                                {{ data_get($device, 'status', 'Brak danych') }}
                            </strong>
                            <span>{{ data_get($device, 'message', 'Brak opisu') }}</span>
                        </div>

                        <div class="sp-device-cell">
                            <strong>
                                @if(is_numeric(data_get($device, 'health_score')))
                                    {{ data_get($device, 'health_score') }}/100
                                @else
                                    brak danych
                                @endif
                            </strong>
                            <span>Health Score</span>
                        </div>

                        <div class="sp-device-cell">
                            <strong>{{ data_get($device, 'last_seen', 'brak danych') }}</strong>
                            <span>ostatni sygnał</span>
                        </div>

                        <a class="sp-open-link"
                           href="{{ data_get($device, 'url', '#') }}"
                           aria-label="Otwórz dashboard urządzenia {{ data_get($device, 'name', '') }}">→</a>
                    </article>
                @empty
                    <div class="sp-empty">
                        <strong>Brak aktywnych urządzeń.</strong>
                        <span>Dodaj urządzenie w wybranej placówce.</span>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="sp-panel" id="incidents" aria-labelledby="sp-incidents-title">
            <div class="sp-panel-header">
                <div>
                    <h2 id="sp-incidents-title">Aktywne incydenty</h2>
                    <p>Najważniejsze zdarzenia wymagające sprawdzenia.</p>
                </div>
                <span class="sp-active-count {{ count($incidents) > 0 ? 'is-danger' : 'is-success' }}">
                    {{ count($incidents) }} aktywne
                </span>
            </div>

            <div class="sp-incident-list">
                @forelse($incidents as $incident)
                    <article class="sp-incident is-{{ data_get($incident, 'tone', 'warning') }}">
                        <span class="sp-incident-icon" aria-hidden="true">{{ data_get($incident, 'symbol', '!') }}</span>
                        <div>
                            <h3>{{ data_get($incident, 'title', 'Incydent') }}</h3>
                            <p>{{ data_get($incident, 'description', 'Brak dodatkowych informacji.') }}</p>
                            <div class="sp-incident-meta">
                                <span>Priorytet: {{ data_get($incident, 'priority', 'wysoki') }}</span>
                                <span>{{ data_get($incident, 'duration') }}</span>
                            </div>
                            @if(data_get($incident, 'url'))
                                <a href="{{ data_get($incident, 'url') }}">Otwórz szczegóły</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="sp-empty is-success">
                        <strong>Brak aktywnych incydentów.</strong>
                        <span>System nie zgłasza obecnie awarii wymagających reakcji.</span>
                    </div>
                @endforelse
            </div>

            <a class="sp-button is-primary sp-full-button" href="{{ route('monitoring-center.index') }}">
                Przejdź do centrum monitoringu
            </a>
        </section>
    </div>

    <section class="sp-agent-bar" aria-label="Informacja o agentach">
        <div>
            <span class="sp-agent-icon" aria-hidden="true"><x-platinum.icon name="windows" /></span>
            <div>
                <strong>Agent Windows {{ data_get($agent, 'version', 'brak danych') }}</strong>
                <p>{{ data_get($agent, 'message') }}</p>
            </div>
        </div>
        @if(auth()->user()?->isAdmin())
            <a class="sp-button" href="{{ route('system.status') }}">Stan systemu</a>
        @endif
    </section>
</div>
@endsection
