@extends('layouts.panel')

@section('title', $facility->code.' — '.$facility->name.' — Placówka Online')
@section('eyebrow', 'Placówki / '.$facility->code)
@section('page_title', $facility->name)
@section('page_lead', 'Stan infrastruktury, urządzenia, ostatnie pomiary i historia awarii.')

@push('head')
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-facility.css') }}">
@endpush

@section('page_actions')
    <a class="btn secondary" href="{{ route('dashboard') }}">Powrót do panelu</a>
    @if(auth()->user()?->isAdmin())
        <a class="btn secondary" href="{{ route('facilities.manage', ['facility' => $facility->id]) }}">Zarządzaj placówką</a>
    @endif
    <a class="btn secondary" href="{{ route('reports.facility', ['facility' => $facility->id]) }}">Raport</a>
    @if(auth()->user()?->isAdmin())
        <a class="btn" href="{{ route('facilities.devices.create', ['facility' => $facility->id]) }}">Dodaj urządzenie</a>
    @endif
    <a class="btn secondary" href="{{ route('facilities.show', ['facility' => $facility->id]) }}">Odśwież</a>
@endsection

@section('content')
@php
    $dashboard = $facilityDashboard;
    $meta = $dashboard['meta'];
    $counts = $dashboard['counts'];
    $gaugeValue = is_numeric($meta['health_score'] ?? null)
        ? max(0, min(100, (int) $meta['health_score']))
        : null;
    $gaugeCircumference = 289.03;
    $gaugeOffset = $gaugeValue !== null
        ? $gaugeCircumference - (($gaugeValue / 100) * $gaugeCircumference)
        : $gaugeCircumference;
    $stateLabels = [
        'healthy' => 'Prawidłowo',
        'warning' => 'Wymaga uwagi',
        'critical' => 'Problem',
        'unknown' => 'Brak danych',
    ];
@endphp

<section class="pfo-hero pfo-hero--{{ $meta['state'] }}" aria-labelledby="pfo-overview-title">
    <div class="pfo-hero__main">
        <div class="pfo-hero__status-row">
            <span class="pfo-status-badge pfo-status-badge--{{ $meta['state'] }}">
                <span class="pfo-status-dot" aria-hidden="true"></span>
                {{ $meta['status_label'] }}
            </span>
            <span class="pfo-hero__updated">
                Ostatni pomiar: {{ $meta['latest_measurement_relative'] }}
            </span>
        </div>

        <div class="pfo-hero__heading">
            <div>
                <p class="pfo-kicker">SaaS Platinum · szczegóły placówki</p>
                <h2 id="pfo-overview-title">{{ $meta['code'] }} — {{ $meta['name'] }}</h2>
                <p>{{ $meta['summary'] }}</p>
            </div>

            <div class="pfo-gauge pfo-gauge--{{ $meta['state'] }}"
                 role="img"
                 aria-label="Health Score placówki: {{ $gaugeValue !== null ? $gaugeValue.' na 100' : 'brak bieżących danych' }}">
                <svg class="pfo-gauge__svg" viewBox="0 0 112 112" aria-hidden="true" focusable="false">
                    <circle class="pfo-gauge__track" cx="56" cy="56" r="46"></circle>
                    <circle class="pfo-gauge__value"
                            cx="56"
                            cy="56"
                            r="46"
                            stroke-dasharray="{{ number_format($gaugeCircumference, 2, '.', '') }}"
                            stroke-dashoffset="{{ number_format($gaugeOffset, 2, '.', '') }}"></circle>
                </svg>
                <span class="pfo-gauge__content">
                    <strong>{{ $gaugeValue !== null ? $gaugeValue : '—' }}</strong>
                    @if($gaugeValue !== null)
                        <small>/100</small>
                    @endif
                </span>
            </div>
        </div>

        <div class="pfo-confidence">
            <div class="pfo-confidence__heading">
                <span>Wiarygodność danych</span>
                <strong>{{ $meta['confidence'] }}%</strong>
            </div>
            <progress max="100"
                      value="{{ $meta['confidence'] }}"
                      aria-label="Wiarygodność danych placówki: {{ $meta['confidence'] }} procent">
                {{ $meta['confidence'] }}%
            </progress>
        </div>
    </div>

    <dl class="pfo-hero__facts">
        <div>
            <dt>Kod placówki</dt>
            <dd>{{ $meta['code'] }}</dd>
        </div>
        <div>
            <dt>Status placówki</dt>
            <dd>{{ $meta['is_active'] ? 'Aktywna' : 'Nieaktywna' }}</dd>
        </div>
        <div>
            <dt>Adres</dt>
            <dd>{{ $meta['address'] }}</dd>
        </div>
        <div>
            <dt>Kontakt</dt>
            <dd>{{ $meta['contact_email'] }}</dd>
        </div>
        <div>
            <dt>Urządzenia</dt>
            <dd>{{ $counts['total'] }}</dd>
        </div>
        <div>
            <dt>Ostatni pomiar</dt>
            <dd>{{ $meta['latest_measurement'] }}</dd>
        </div>
    </dl>
</section>

@if(!data_get($meta, 'has_current_data', false) || data_get($meta, 'current_reporting', 0) < data_get($meta, 'active_devices', 0))
    <section class="pfo-data-warning" role="status" aria-labelledby="pfo-data-warning-title">
        <span class="pfo-data-warning__icon" aria-hidden="true">!</span>
        <div>
            <h2 id="pfo-data-warning-title">
                {{ data_get($meta, 'has_current_data', false) ? 'Niepełne dane bieżące' : 'Brak bieżących danych telemetrycznych' }}
            </h2>
            <p>{{ data_get($meta, 'freshness_message') }}</p>
            <p>Wartości z wcześniejszych heartbeatów pozostają dostępne wyłącznie w sekcji historii i nie potwierdzają aktualnego stanu urządzeń.</p>
        </div>
    </section>
@endif

<nav class="pfo-section-nav" aria-label="Sekcje szczegółów placówki">
    <a href="#podsumowanie">Podsumowanie</a>
    <a href="#moduly">Moduły</a>
    <a href="#urzadzenia">Urządzenia</a>
    <a href="#pomiary">Pomiary</a>
    <a href="#awarie">Awarie</a>
</nav>

<section id="podsumowanie" class="pfo-section" aria-labelledby="pfo-summary-title">
    <header class="pfo-section-heading">
        <div>
            <p class="pfo-kicker">Bieżący stan</p>
            <h2 id="pfo-summary-title">Podsumowanie placówki</h2>
        </div>
        <p>Najważniejsze dane wymagające szybkiej oceny administratora.</p>
    </header>

    <div class="pfo-stat-grid">
        @foreach([
            ['label' => 'Urządzenia online', 'value' => $counts['online'], 'state' => 'healthy', 'icon' => 'device', 'note' => 'z '.$counts['total'].' wszystkich urządzeń'],
            ['label' => 'Wymaga uwagi', 'value' => $counts['problem'] + $counts['unknown'], 'state' => ($counts['problem'] + $counts['unknown']) > 0 ? 'warning' : 'healthy', 'icon' => 'health', 'note' => $counts['problem'].' problem · '.$counts['unknown'].' bez danych'],
            ['label' => 'Urządzenia offline', 'value' => $counts['offline'], 'state' => $counts['offline'] > 0 ? 'critical' : 'healthy', 'icon' => 'alarm', 'note' => 'urządzenia bez komunikacji'],
            ['label' => 'Aktywne awarie', 'value' => $counts['open_incidents'], 'state' => $counts['open_incidents'] > 0 ? 'critical' : 'healthy', 'icon' => 'bell', 'note' => $counts['incidents_24h'].' zdarzeń w ostatnich 24 h'],
        ] as $stat)
            <article class="pfo-stat-card pfo-stat-card--{{ $stat['state'] }}">
                <span class="pfo-stat-card__icon" aria-hidden="true">
                    <x-platinum.icon :name="$stat['icon']" :size="22" />
                </span>
                <div>
                    <p>{{ $stat['label'] }}</p>
                    <strong>{{ $stat['value'] }}</strong>
                    <span>{{ $stat['note'] }}</span>
                </div>
            </article>
        @endforeach
    </div>

    <div class="pfo-incident-periods" aria-label="Statystyka awarii według okresu">
        <div><span>Ostatnie 24 godziny</span><strong>{{ $counts['incidents_24h'] }}</strong></div>
        <div><span>Ostatnie 7 dni</span><strong>{{ $counts['incidents_7d'] }}</strong></div>
        <div><span>Ostatnie 30 dni</span><strong>{{ $counts['incidents_30d'] }}</strong></div>
        <div><span>Aktywne urządzenia</span><strong>{{ $counts['active'] }}</strong></div>
        <div><span>Urządzenia archiwalne</span><strong>{{ $counts['archived'] }}</strong></div>
    </div>
</section>

<section id="moduly" class="pfo-section" aria-labelledby="pfo-modules-title">
    <header class="pfo-section-heading">
        <div>
            <p class="pfo-kicker">Stan techniczny</p>
            <h2 id="pfo-modules-title">Moduły monitoringu</h2>
        </div>
        <p>Wyłącznie bieżące dane agentów mieszczące się w dozwolonym czasie braku komunikacji.</p>
    </header>

    <div class="pfo-module-grid">
        @foreach([
            ['key' => 'internet', 'icon' => 'internet'],
            ['key' => 'latency', 'icon' => 'response'],
            ['key' => 'dns', 'icon' => 'internet'],
            ['key' => 'monitoring', 'icon' => 'health'],
            ['key' => 'smart', 'icon' => 'smart'],
            ['key' => 'services', 'icon' => 'service'],
            ['key' => 'windows', 'icon' => 'windows'],
            ['key' => 'resources', 'icon' => 'dashboard'],
        ] as $moduleConfig)
            @php $module = $dashboard['modules'][$moduleConfig['key']]; @endphp
            <article class="pfo-module-card pfo-module-card--{{ $module['state'] }}">
                <header>
                    <span class="pfo-module-card__icon" aria-hidden="true">
                        <x-platinum.icon :name="$moduleConfig['icon']" :size="22" />
                    </span>
                    <span class="pfo-state-text pfo-state-text--{{ $module['state'] }}">
                        {{ $stateLabels[$module['state']] ?? 'Brak danych' }}
                    </span>
                </header>
                <p>{{ $module['label'] }}</p>
                <strong>{{ $module['value'] }}</strong>
                <span>{{ $module['description'] }}</span>

                @if($moduleConfig['key'] === 'latency')
                    <svg class="pfo-sparkline pfo-sparkline--{{ $module['state'] }}"
                         viewBox="0 0 180 32"
                         role="img"
                         aria-label="Trend średniego opóźnienia"
                         preserveAspectRatio="none">
                        <path class="pfo-sparkline__fill" d="{{ $module['sparkline']['fill'] }}"></path>
                        <path class="pfo-sparkline__line" d="{{ $module['sparkline']['line'] }}"></path>
                    </svg>
                @endif
            </article>
        @endforeach
    </div>
</section>

<section id="urzadzenia" class="pfo-section" aria-labelledby="pfo-devices-title">
    <header class="pfo-section-heading">
        <div>
            <p class="pfo-kicker">Infrastruktura</p>
            <h2 id="pfo-devices-title">Urządzenia w placówce</h2>
        </div>
        <p>{{ $counts['total'] }} urządzeń · problemy są wyświetlane jako pierwsze.</p>
    </header>

    @if(count($dashboard['devices']))
        <div class="pfo-device-grid">
            @foreach($dashboard['devices'] as $device)
                <article class="pfo-device-card pfo-device-card--{{ $device['state'] }}">
                    <header class="pfo-device-card__header">
                        <div class="pfo-device-card__identity">
                            <span class="pfo-device-card__icon" aria-hidden="true">
                                <x-platinum.icon name="device" :size="22" />
                            </span>
                            <div>
                                <h3>{{ $device['name'] }}</h3>
                                <p>{{ $device['diagnostic_label'] }}</p>
                            </div>
                        </div>
                        <span class="pfo-status-badge pfo-status-badge--{{ $device['state'] }}">
                            <span class="pfo-status-dot" aria-hidden="true"></span>
                            {{ $device['status_label'] }}
                        </span>
                    </header>

                    <div class="pfo-device-card__score">
                        <div>
                            <span>Health Score</span>
                            <strong>{{ $device['health_score_display'] }}</strong>
                        </div>
                        <div>
                            <span>Wiarygodność</span>
                            <strong>{{ $device['confidence_display'] }}</strong>
                        </div>
                    </div>

                    <dl class="pfo-device-card__facts">
                        <div><dt>Ostatni raport</dt><dd>{{ $device['last_seen_relative'] }}</dd></div>
                        <div><dt>Opóźnienie</dt><dd>{{ $device['latency'] }}</dd></div>
                        <div><dt>Adres IP</dt><dd><code>{{ $device['ip_address'] }}</code></dd></div>
                        <div><dt>Agent</dt><dd>{{ $device['agent_version'] }}</dd></div>
                    </dl>

                    <ul class="pfo-check-list" aria-label="Kontrole połączenia urządzenia {{ $device['name'] }}">
                        @foreach($device['checks'] as $check)
                            <li class="pfo-check pfo-check--{{ $check['state'] }}">
                                <span aria-hidden="true"></span>
                                <strong>{{ $check['label'] }}</strong>
                                <small>{{ $check['value'] }}</small>
                            </li>
                        @endforeach
                    </ul>

                    @if(!$device['telemetry_fresh'] && $device['is_active'] && !$device['is_archived'])
                        <p class="pfo-device-card__notice pfo-device-card__notice--critical">
                            {{ $device['telemetry_notice'] }}
                        </p>
                    @endif

                    @if($device['archive_label'] || !$device['is_active'])
                        <p class="pfo-device-card__notice">
                            {{ $device['archive_label'] ?: 'Urządzenie jest nieaktywne.' }}
                        </p>
                    @endif

                    <footer class="pfo-device-card__actions">
                        @if(auth()->user()?->isAdmin())
                            <a class="btn small secondary" href="{{ $device['manage_url'] }}">Zarządzaj</a>
                        @endif
                        <a class="btn small" href="{{ $device['details_url'] }}">Szczegóły urządzenia</a>
                    </footer>
                </article>
            @endforeach
        </div>
    @else
        <div class="pfo-empty">
            <strong>Brak urządzeń w tej placówce.</strong>
            @if(auth()->user()?->isAdmin())
                <p>Dodaj pierwsze urządzenie, aby rozpocząć monitoring.</p>
                <a class="btn" href="{{ route('facilities.devices.create', ['facility' => $facility->id]) }}">Dodaj urządzenie</a>
            @else
                <p>Do tej placówki nie dodano jeszcze żadnego urządzenia.</p>
            @endif
        </div>
    @endif
</section>

<section id="pomiary" class="pfo-section" aria-labelledby="pfo-heartbeats-title">
    <header class="pfo-section-heading">
        <div>
            <p class="pfo-kicker">Historia komunikacji</p>
            <h2 id="pfo-heartbeats-title">Ostatnie pomiary heartbeat</h2>
        </div>
        <p>15 najnowszych wpisów ze wszystkich urządzeń placówki.</p>
    </header>

    @if(count($dashboard['heartbeats']))
        <div class="pfo-timeline">
            @foreach($dashboard['heartbeats'] as $heartbeat)
                <article class="pfo-timeline-item pfo-timeline-item--{{ $heartbeat['state'] }}">
                    <div class="pfo-timeline-item__marker" aria-hidden="true"></div>
                    <div class="pfo-timeline-item__content">
                        <header>
                            <div>
                                <h3>{{ $heartbeat['device_name'] }}</h3>
                                <p>{{ $heartbeat['checked_at'] }}</p>
                            </div>
                            <span class="pfo-status-badge pfo-status-badge--{{ $heartbeat['state'] }}">
                                {{ $heartbeat['status_label'] }}
                            </span>
                        </header>

                        <p class="pfo-timeline-item__diagnosis">{{ $heartbeat['diagnostic_label'] }}</p>

                        <dl class="pfo-timeline-item__facts">
                            <div><dt>Opóźnienie</dt><dd>{{ $heartbeat['latency'] }}</dd></div>
                            <div><dt>Agent</dt><dd>{{ $heartbeat['agent_version'] }}</dd></div>
                        </dl>

                        <ul class="pfo-check-list pfo-check-list--compact">
                            @foreach($heartbeat['checks'] as $check)
                                <li class="pfo-check pfo-check--{{ $check['state'] }}">
                                    <span aria-hidden="true"></span>
                                    <strong>{{ $check['label'] }}</strong>
                                    <small>{{ $check['value'] }}</small>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="pfo-empty">
            <strong>Brak pomiarów heartbeat.</strong>
            <p>Dane pojawią się po pierwszym prawidłowym połączeniu agenta.</p>
        </div>
    @endif
</section>

<section id="awarie" class="pfo-section" aria-labelledby="pfo-incidents-title">
    <header class="pfo-section-heading">
        <div>
            <p class="pfo-kicker">Historia zdarzeń</p>
            <h2 id="pfo-incidents-title">Awarie i incydenty</h2>
        </div>
        <p>Ostatnie 50 zarejestrowanych zdarzeń placówki.</p>
    </header>

    @if(count($dashboard['incidents']))
        <div class="pfo-incident-list">
            @foreach($dashboard['incidents'] as $incident)
                <article class="pfo-incident pfo-incident--{{ $incident['state'] }}">
                    <header>
                        <div>
                            <p class="pfo-incident__device">{{ $incident['device_name'] }}</p>
                            <h3>{{ $incident['type_label'] }}</h3>
                        </div>
                        <span class="pfo-status-badge pfo-status-badge--{{ $incident['state'] }}">
                            {{ $incident['status_label'] }}
                        </span>
                    </header>

                    <dl>
                        <div><dt>Początek</dt><dd>{{ $incident['started_at'] }}</dd></div>
                        <div><dt>Koniec</dt><dd>{{ $incident['ended_at'] }}</dd></div>
                        <div><dt>Czas trwania</dt><dd>{{ $incident['duration'] }}</dd></div>
                        <div><dt>Powiadomienie</dt><dd>{{ $incident['notification_label'] }}</dd></div>
                    </dl>

                    @if($incident['notification_error'])
                        <p class="pfo-incident__error">
                            Błąd powiadomienia: {{ $incident['notification_error'] }}
                        </p>
                    @endif
                </article>
            @endforeach
        </div>
    @else
        <div class="pfo-empty pfo-empty--success">
            <strong>Brak zarejestrowanych awarii.</strong>
            <p>W historii tej placówki nie ma obecnie żadnych incydentów.</p>
        </div>
    @endif
</section>
@endsection
