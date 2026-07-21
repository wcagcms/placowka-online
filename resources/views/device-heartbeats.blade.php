@extends('layouts.panel')

@section('title', 'Urządzenie — '.$device->name.' — Placówka Online')
@section('eyebrow', 'Urządzenie / '.$device->facility->code)
@section('page_title', $device->name)
@section('page_lead', $device->facility->name.' · szczegółowy stan komputera i połączenia')

@push('head')
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-device.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-agents.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-operations.css') }}">
@endpush

@section('page_actions')
    <a class="btn secondary" href="{{ route('facilities.show', ['facility' => $device->facility->id]) }}">
        Powrót do placówki
    </a>
    @if(auth()->user()?->isAdmin())
        <a class="btn secondary" href="{{ route('devices.edit', ['device' => $device->id]) }}">
            Zarządzaj
        </a>
    @endif
    <a class="btn" href="{{ route('devices.heartbeats', ['device' => $device->id]) }}">
        Odśwież dane
    </a>
@endsection

@section('content')
@php
    $dashboard = $deviceDashboard;
    $stateLabels = [
        'healthy' => 'Prawidłowo',
        'warning' => 'Wymaga uwagi',
        'critical' => 'Problem',
        'unknown' => 'Brak danych',
    ];
    $trendSymbols = [
        'up' => '▲',
        'down' => '▼',
        'flat' => '▬',
    ];
@endphp

<section class="pdo-hero pdo-hero--{{ $dashboard['meta']['status_state'] }}"
         aria-labelledby="pdo-overview-title">
    <div class="pdo-hero__primary">
        <div class="pdo-hero__status-line">
            <span class="pdo-status-badge pdo-status-badge--{{ $dashboard['meta']['status_state'] }}">
                <span class="pdo-status-dot" aria-hidden="true"></span>
                {{ $dashboard['meta']['status_label'] }}
            </span>
            <span class="pdo-hero__updated">
                Ostatni kontakt: {{ $dashboard['meta']['last_seen_relative'] }}
            </span>
        </div>

        <div class="pdo-hero__heading">
            <div>
                <p class="pdo-kicker">SaaS Platinum · stan urządzenia</p>
                <h2 id="pdo-overview-title">{{ $dashboard['health']['label'] }}</h2>
                <p>{{ $dashboard['health']['summary'] }}</p>
            </div>

            <x-platinum.gauge
                :value="$dashboard['health']['value']"
                label="Health Score urządzenia"
                :state="$dashboard['health']['state']"
                suffix="/100"
            />
        </div>

        <div class="pdo-confidence">
            <div class="pdo-confidence__heading">
                <span>Wiarygodność danych</span>
                <strong>{{ $dashboard['health']['confidence'] }}%</strong>
            </div>
            <progress max="100"
                      value="{{ $dashboard['health']['confidence'] }}"
                      aria-label="Wiarygodność danych: {{ $dashboard['health']['confidence'] }} procent">
                {{ $dashboard['health']['confidence'] }}%
            </progress>
        </div>
    </div>

    <dl class="pdo-hero__facts">
        <div>
            <dt>Aktualna diagnoza</dt>
            <dd>{{ $dashboard['meta']['diagnostic_label'] }}</dd>
        </div>
        <div>
            <dt>Ostatni pomiar</dt>
            <dd>{{ $dashboard['meta']['measurement_at'] }}</dd>
        </div>
        <div>
            <dt>Adres IPv4</dt>
            <dd><code>{{ $dashboard['meta']['ip_address'] }}</code></dd>
        </div>
        <div>
            <dt>Wersja agenta</dt>
            <dd>{{ $dashboard['meta']['agent_version'] }}</dd>
        </div>
        <div>
            <dt>Aktywne incydenty</dt>
            <dd>{{ $dashboard['meta']['active_incidents'] }}</dd>
        </div>
        <div>
            <dt>Incydenty z 30 dni</dt>
            <dd>{{ $dashboard['meta']['incidents_30d'] }}</dd>
        </div>
    </dl>
</section>

@if(!data_get($dashboard, 'meta.telemetry_fresh', false))
    <section class="pdo-data-warning" role="status" aria-labelledby="pdo-data-warning-title">
        <span class="pdo-data-warning__icon" aria-hidden="true">!</span>
        <div>
            <h2 id="pdo-data-warning-title">Brak bieżącej komunikacji z agentem</h2>
            <p>{{ data_get($dashboard, 'meta.freshness_message') }}</p>
            <p>CPU, RAM, Internet, DNS, SMART, usługi Windows i pozostałe kontrole mają stan „Brak danych”. Historia wcześniejszych heartbeatów pozostaje dostępna poniżej.</p>
        </div>
    </section>
@endif

<section id="agent" class="po-agent-device-panel" aria-labelledby="po-device-agent-title">
    <header class="po-agent-device-panel__header">
        <div>
            <p class="po-agent-kicker">Samokontrola agenta</p>
            <h2 id="po-device-agent-title">{{ $agentDiagnostics['status_label'] }}</h2>
            <p>{{ $agentDiagnostics['status_message'] }}</p>
        </div>
        <span class="po-agent-state po-agent-state--{{ $agentDiagnostics['status'] }}">
            {{ data_get($agentDiagnostics, 'version.label') }}
        </span>
    </header>

    <div class="po-agent-device-panel__grid">
        <div>
            <span>Wersja agenta</span>
            <strong>{{ data_get($agentDiagnostics, 'version.installed') ?: 'Brak danych' }}</strong>
        </div>
        <div>
            <span>Wersja produkcyjna</span>
            <strong>{{ data_get($agentDiagnostics, 'version.latest') }}</strong>
        </div>
        <div>
            <span>Kompletność pomiaru</span>
            <strong>{{ $agentDiagnostics['telemetry_completeness_label'] }}</strong>
        </div>
        <div>
            <span>Czas ostatniego cyklu</span>
            <strong>{{ $agentDiagnostics['cycle_duration'] }}</strong>
        </div>
        <div>
            <span>Profil pracy</span>
            <strong>{{ $agentDiagnostics['profile'] }}</strong>
        </div>
        <div>
            <span>Zadanie automatyczne</span>
            <strong>{{ $agentDiagnostics['task_present'] === true ? 'Prawidłowe' : ($agentDiagnostics['task_present'] === false ? 'Problem' : 'Brak danych') }}</strong>
        </div>
        <div>
            <span>Różnica czasu</span>
            <strong>{{ $agentDiagnostics['clock_skew_label'] }}</strong>
        </div>
        <div>
            <span>Ostatnia samokontrola</span>
            <strong>{{ $agentDiagnostics['updated_at'] }}</strong>
        </div>
    </div>

    @if($agentDiagnostics['missing_modules'] !== [])
        <p class="po-agent-device-panel__missing">
            <strong>Brakujące pomiary:</strong> {{ implode(', ', $agentDiagnostics['missing_modules']) }}.
        </p>
    @endif
</section>

<nav class="pdo-section-nav" aria-label="Sekcje dashboardu urządzenia">
    <a href="#agent">Agent</a>
    <a href="#wydajnosc">Wydajność</a>
    <a href="#polaczenie">Połączenie</a>
    <a href="#system">Windows i dyski</a>
    <a href="#windows-security-title">Aktualizacje i ochrona</a>
    <a href="#smart">SMART</a>
    <a href="#uslugi">Usługi</a>
    <a href="#siec">Sieć</a>
    <a href="#historia">Historia</a>
    <a href="#incydenty">Incydenty</a>
</nav>

<section id="wydajnosc" class="pdo-section" aria-labelledby="pdo-performance-title">
    <header class="pdo-section-heading">
        <div>
            <p class="pdo-kicker">Najważniejsze parametry</p>
            <h2 id="pdo-performance-title">Wydajność i dostępność</h2>
        </div>
        <p>Wskaźniki pokazują wyłącznie dane bieżące. Historia wcześniejszych heartbeatów służy tylko do analizy trendu.</p>
    </header>

    <div class="pdo-metric-grid">
        @foreach([
            ['key' => 'cpu', 'icon' => 'health'],
            ['key' => 'memory', 'icon' => 'windows'],
            ['key' => 'smart', 'icon' => 'smart'],
            ['key' => 'internet', 'icon' => 'internet'],
        ] as $metricConfig)
            @php $metric = $dashboard['metrics'][$metricConfig['key']]; @endphp
            <article class="pdo-metric-card pdo-metric-card--{{ $metric['state'] }}">
                <header class="pdo-metric-card__header">
                    <span class="pdo-metric-card__icon" aria-hidden="true">
                        <x-platinum.icon :name="$metricConfig['icon']" :size="22" />
                    </span>
                    <div>
                        <p>{{ $metric['label'] }}</p>
                        <span class="pdo-state-text pdo-state-text--{{ $metric['state'] }}">
                            {{ $stateLabels[$metric['state']] ?? 'Brak danych' }}
                        </span>
                    </div>
                </header>

                <div class="pdo-metric-card__main">
                    @if(in_array($metricConfig['key'], ['cpu', 'memory', 'smart'], true))
                        <x-platinum.gauge
                            :value="$metric['value']"
                            :label="$metric['label']"
                            :state="$metric['state']"
                            size="compact"
                        />
                    @else
                        <strong class="pdo-metric-card__value">{{ $metric['display'] }}</strong>
                    @endif

                    <div class="pdo-metric-card__trend pdo-trend--{{ $metric['trend']['tone'] }}">
                        <span aria-hidden="true">
                            {{ $trendSymbols[$metric['trend']['direction']] ?? '▬' }}
                        </span>
                        <span>{{ $metric['trend']['label'] }}</span>
                    </div>
                </div>

                <x-platinum.sparkline
                    :line="$metric['sparkline']['line']"
                    :fill="$metric['sparkline']['fill']"
                    :label="'Trend: '.$metric['label']"
                    :state="$metric['state']"
                />

                <p class="pdo-metric-card__note">{{ $metric['note'] }}</p>
            </article>
        @endforeach
    </div>
</section>

<div class="pdo-two-column">
    <section id="polaczenie" class="pdo-card" aria-labelledby="pdo-connectivity-title">
        <header class="pdo-card__header">
            <div>
                <p class="pdo-kicker">Diagnostyka</p>
                <h2 id="pdo-connectivity-title">Połączenie</h2>
            </div>
            <span class="pdo-card__meta">{{ $dashboard['connection']['current'] ? $dashboard['connection']['availability_display'].' dostępności' : 'Brak bieżących danych' }}</span>
        </header>

        <div class="pdo-connectivity-grid">
            @foreach($dashboard['connection']['checks'] as $check)
                <article class="pdo-check pdo-check--{{ $check['state'] }}">
                    <span class="pdo-check__icon" aria-hidden="true"></span>
                    <div>
                        <h3>{{ $check['label'] }}</h3>
                        <strong>{{ $check['status'] }}</strong>
                        <p>{{ $check['detail'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="pdo-latency-summary">
            <div>
                <span>Średnie opóźnienie</span>
                <strong>{{ $dashboard['connection']['average_latency'] }}</strong>
            </div>
            <div>
                <span>Najniższe</span>
                <strong>{{ $dashboard['connection']['minimum_latency'] }}</strong>
            </div>
            <div>
                <span>Najwyższe</span>
                <strong>{{ $dashboard['connection']['maximum_latency'] }}</strong>
            </div>
        </div>

        <x-platinum.sparkline
            :line="$dashboard['connection']['latency_sparkline']['line']"
            :fill="$dashboard['connection']['latency_sparkline']['fill']"
            label="Trend opóźnienia Internetu"
            state="healthy"
            class="pdo-latency-chart"
        />
    </section>

    <section class="pdo-card" aria-labelledby="pdo-health-factors-title">
        <header class="pdo-card__header">
            <div>
                <p class="pdo-kicker">Składniki oceny</p>
                <h2 id="pdo-health-factors-title">Health Score</h2>
            </div>
            <span class="pdo-card__meta">{{ count($dashboard['health']['factors']) }} modułów</span>
        </header>

        <div class="pdo-factor-list">
            @foreach($dashboard['health']['factors'] as $factor)
                <article class="pdo-factor pdo-factor--{{ $factor['state'] }}">
                    <div class="pdo-factor__heading">
                        <div>
                            <h3>{{ $factor['label'] }}</h3>
                            <p>{{ $factor['value'] }}</p>
                        </div>
                        <strong>{{ $factor['available'] ? $factor['percent'].'%' : '—' }}</strong>
                    </div>
                    <progress max="100"
                              value="{{ $factor['available'] ? $factor['percent'] : 0 }}"
                              aria-label="{{ $factor['label'] }}: {{ $factor['available'] ? $factor['percent'].' procent' : 'brak danych' }}">
                        {{ $factor['percent'] }}%
                    </progress>
                    <p class="pdo-factor__description">{{ $factor['description'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
</div>

<section id="system" class="pdo-card pdo-card--wide" aria-labelledby="pdo-system-title">
    <header class="pdo-card__header">
        <div>
            <p class="pdo-kicker">Komputer</p>
            <h2 id="pdo-system-title">Windows, CPU, RAM i przestrzeń dyskowa</h2>
        </div>
        <span class="pdo-card__meta">Pomiar: {{ $dashboard['meta']['measurement_at'] }}</span>
    </header>

    @if(!$dashboard['system']['available'])
        <div class="pdo-empty">
            Brak bieżących danych systemowych. Sprawdź działanie agenta i zaczekaj na kolejny heartbeat.
        </div>
    @else
        <div class="pdo-system-overview">
            <article class="pdo-system-card">
                <span class="pdo-system-card__icon" aria-hidden="true">
                    <x-platinum.icon name="windows" :size="24" />
                </span>
                <h3>System</h3>
                <dl>
                    <div><dt>Komputer</dt><dd>{{ $dashboard['system']['computer_name'] }}</dd></div>
                    <div><dt>Windows</dt><dd>{{ $dashboard['system']['os_caption'] }}</dd></div>
                    <div><dt>Wersja</dt><dd>{{ $dashboard['system']['os_version'] }}</dd></div>
                    <div><dt>Czas działania</dt><dd>{{ $dashboard['system']['uptime'] }}</dd></div>
                </dl>
            </article>

            <article class="pdo-system-card">
                <span class="pdo-system-card__icon" aria-hidden="true">
                    <x-platinum.icon name="health" :size="24" />
                </span>
                <h3>Procesor</h3>
                <dl>
                    <div><dt>Model</dt><dd>{{ $dashboard['system']['cpu']['model'] }}</dd></div>
                    <div><dt>Rdzenie</dt><dd>{{ $dashboard['system']['cpu']['cores'] }}</dd></div>
                    <div><dt>Procesory logiczne</dt><dd>{{ $dashboard['system']['cpu']['logical_processors'] }}</dd></div>
                    <div><dt>Obciążenie</dt><dd>{{ $dashboard['metrics']['cpu']['display'] }}</dd></div>
                </dl>
            </article>

            <article class="pdo-system-card">
                <span class="pdo-system-card__icon" aria-hidden="true">
                    <x-platinum.icon name="device" :size="24" />
                </span>
                <h3>Pamięć RAM</h3>
                <dl>
                    <div><dt>Wykorzystano</dt><dd>{{ $dashboard['system']['memory']['used'] }}</dd></div>
                    <div><dt>Wolne</dt><dd>{{ $dashboard['system']['memory']['free'] }}</dd></div>
                    <div><dt>Łącznie</dt><dd>{{ $dashboard['system']['memory']['total'] }}</dd></div>
                    <div><dt>Wykorzystanie</dt><dd>{{ $dashboard['metrics']['memory']['display'] }}</dd></div>
                </dl>
            </article>
        </div>

        <div class="pdo-subsection-heading">
            <h3>Dyski lokalne</h3>
            <span>{{ count($dashboard['system']['disks']) }} wykrytych</span>
        </div>

        @if($dashboard['system']['disks'] === [])
            <div class="pdo-empty">Brak danych o partycjach i wolnym miejscu.</div>
        @else
            <div class="pdo-disk-grid">
                @foreach($dashboard['system']['disks'] as $disk)
                    <article class="pdo-disk pdo-disk--{{ $disk['state'] }}">
                        <header>
                            <div>
                                <h4>{{ $disk['name'] }}</h4>
                                @if($disk['label'] !== '')
                                    <p>{{ $disk['label'] }}</p>
                                @endif
                            </div>
                            <strong>{{ $disk['usage_display'] }}</strong>
                        </header>
                        @if($disk['usage'] !== null)
                            <progress max="100"
                                      value="{{ $disk['usage'] }}"
                                      aria-label="Zajęcie dysku {{ $disk['name'] }}: {{ $disk['usage_display'] }}">
                                {{ $disk['usage_display'] }}
                            </progress>
                        @endif
                        <dl>
                            <div><dt>Wykorzystano</dt><dd>{{ $disk['used'] }}</dd></div>
                            <div><dt>Wolne</dt><dd>{{ $disk['free'] }}</dd></div>
                            <div><dt>Pojemność</dt><dd>{{ $disk['total'] }}</dd></div>
                            <div><dt>System plików</dt><dd>{{ $disk['filesystem'] }}</dd></div>
                        </dl>
                    </article>
                @endforeach
            </div>
        @endif
    @endif
</section>

@include('partials.device-windows-security', ['device' => $device])

<section id="smart" class="pdo-card pdo-card--wide" aria-labelledby="pdo-smart-title">
    <header class="pdo-card__header">
        <div>
            <p class="pdo-kicker">Kondycja sprzętu</p>
            <h2 id="pdo-smart-title">SMART dysków</h2>
        </div>
        <div class="pdo-inline-summary" aria-label="Podsumowanie SMART">
            <span><strong>{{ $dashboard['smart']['count'] }}</strong> dysków</span>
            <span><strong>{{ $dashboard['smart']['healthy'] }}</strong> prawidłowych</span>
            <span><strong>{{ $dashboard['smart']['attention'] }}</strong> wymaga uwagi</span>
        </div>
    </header>

    @if(!$dashboard['smart']['available'])
        <div class="pdo-empty">
            Brak bieżących danych SMART. Ostatni znany pomiar nie jest traktowany jako aktualny stan dysków.
        </div>
    @else
        <div class="pdo-smart-grid">
            @foreach($dashboard['smart']['items'] as $disk)
                <article class="pdo-smart-disk pdo-smart-disk--{{ $disk['state'] }}">
                    <header>
                        <div>
                            <h3>{{ $disk['name'] }}</h3>
                            @if($disk['model'] !== '' && $disk['model'] !== $disk['name'])
                                <p>{{ $disk['model'] }}</p>
                            @endif
                        </div>
                        <span class="pdo-status-badge pdo-status-badge--{{ $disk['state'] }}">
                            {{ $disk['state_label'] }}
                        </span>
                    </header>

                    <div class="pdo-smart-metrics">
                        <div><span>Temperatura</span><strong>{{ $disk['temperature'] }}</strong></div>
                        <div><span>Żywotność SSD</span><strong>{{ $disk['remaining_life_display'] }}</strong></div>
                        <div><span>Czas pracy</span><strong>{{ $disk['power_on_hours'] }}</strong></div>
                    </div>

                    @if($disk['remaining_life'] !== null)
                        <progress max="100"
                                  value="{{ $disk['remaining_life'] }}"
                                  aria-label="Pozostała żywotność dysku {{ $disk['name'] }}: {{ $disk['remaining_life_display'] }}">
                            {{ $disk['remaining_life_display'] }}
                        </progress>
                    @endif

                    <dl class="pdo-detail-list">
                        <div><dt>Stan SMART</dt><dd>{{ $disk['health_status'] }}</dd></div>
                        <div><dt>Stan operacyjny</dt><dd>{{ $disk['operational_status'] }}</dd></div>
                        <div><dt>Typ nośnika</dt><dd>{{ $disk['media_type'] }}</dd></div>
                        <div><dt>Magistrala</dt><dd>{{ $disk['bus_type'] }}</dd></div>
                        <div><dt>Pojemność</dt><dd>{{ $disk['capacity'] }}</dd></div>
                        <div><dt>Błędy odczytu</dt><dd>{{ $disk['read_errors'] }}</dd></div>
                        <div><dt>Błędy zapisu</dt><dd>{{ $disk['write_errors'] }}</dd></div>
                        <div><dt>Liczniki SMART</dt><dd>{{ $disk['smart_supported'] ? 'Dostępne' : 'Ograniczone' }}</dd></div>
                    </dl>

                    <p class="pdo-card-message">{{ $disk['message'] }}</p>
                </article>
            @endforeach
        </div>

        <p class="pdo-disclaimer">
            Brak temperatury lub żywotności może wynikać ze sterownika, RAID, połączenia USB albo ograniczeń sprzętu. Sam brak tych wartości nie oznacza awarii.
        </p>
    @endif
</section>

<section id="uslugi" class="pdo-card pdo-card--wide" aria-labelledby="pdo-services-title">
    <header class="pdo-card__header">
        <div>
            <p class="pdo-kicker">Monitoring Windows</p>
            <h2 id="pdo-services-title">Usługi systemowe</h2>
        </div>
        <div class="pdo-inline-summary" aria-label="Podsumowanie usług Windows">
            <span><strong>{{ $dashboard['services']['count'] }}</strong> monitorowanych</span>
            <span><strong>{{ $dashboard['services']['healthy'] }}</strong> prawidłowych</span>
            <span><strong>{{ $dashboard['services']['attention'] }}</strong> wymaga uwagi</span>
        </div>
    </header>

    @if(!$dashboard['services']['available'])
        <div class="pdo-empty">
            Brak bieżących danych o usługach Windows. Ostatni znany stan usług nie jest traktowany jako aktualny.
        </div>
    @else
        <div class="pdo-service-grid">
            @foreach($dashboard['services']['items'] as $service)
                <article class="pdo-service pdo-service--{{ $service['state'] }}">
                    <header>
                        <div>
                            <h3>{{ $service['label'] }}</h3>
                            <code>{{ $service['name'] }}</code>
                        </div>
                        <span class="pdo-status-badge pdo-status-badge--{{ $service['state'] }}">
                            {{ $service['status_label'] }}
                        </span>
                    </header>
                    <dl class="pdo-detail-list">
                        <div><dt>Nazwa Windows</dt><dd>{{ $service['display_name'] }}</dd></div>
                        <div><dt>Tryb uruchamiania</dt><dd>{{ $service['start_type'] }}</dd></div>
                        <div><dt>Stan oczekiwany</dt><dd>{{ $service['expected_status'] }}</dd></div>
                        <div><dt>Alert</dt><dd>{{ $service['alert'] ? 'Włączony' : 'Tylko informacja' }}</dd></div>
                    </dl>
                    <p class="pdo-card-message">{{ $service['message'] }}</p>
                </article>
            @endforeach
        </div>
    @endif
</section>

<section id="siec" class="pdo-card pdo-card--wide" aria-labelledby="pdo-network-title">
    <header class="pdo-card__header">
        <div>
            <p class="pdo-kicker">Konfiguracja techniczna</p>
            <h2 id="pdo-network-title">Sieć i karta sieciowa</h2>
        </div>
        <span class="pdo-card__meta">{{ $dashboard['network']['data_label'] }} · {{ $dashboard['network']['connection_type'] }}</span>
    </header>

    @if(!$dashboard['network']['current'])
        <div class="pdo-inline-warning">
            Konfiguracja sieci pochodzi z ostatniego odebranego heartbeat i może być nieaktualna.
        </div>
    @endif

    <div class="pdo-network-grid">
        <article>
            <h3>Adresacja</h3>
            <dl class="pdo-detail-list">
                <div><dt>Typ połączenia</dt><dd>{{ $dashboard['network']['connection_type'] }}</dd></div>
                <div><dt>IPv4</dt><dd><code>{{ $dashboard['network']['ipv4_address'] }}</code></dd></div>
                <div><dt>Prefiks</dt><dd>{{ $dashboard['network']['ipv4_prefix'] }}</dd></div>
                <div><dt>Brama</dt><dd><code>{{ $dashboard['network']['gateway'] }}</code></dd></div>
                <div><dt>Serwery DNS</dt><dd>{{ $dashboard['network']['dns_servers'] }}</dd></div>
                <div><dt>DHCP</dt><dd>{{ $dashboard['network']['dhcp'] }}</dd></div>
                <div><dt>MTU</dt><dd>{{ $dashboard['network']['mtu'] }}</dd></div>
                <div><dt>IPv6</dt><dd>{{ $dashboard['network']['ipv6_addresses'] }}</dd></div>
            </dl>
        </article>

        <article>
            <h3>Karta sieciowa</h3>
            <dl class="pdo-detail-list">
                <div><dt>Interfejs</dt><dd>{{ $dashboard['network']['interface_alias'] }}</dd></div>
                <div><dt>Status</dt><dd>{{ $dashboard['network']['adapter_status'] }}</dd></div>
                <div><dt>Model</dt><dd>{{ $dashboard['network']['description'] }}</dd></div>
                <div><dt>Prędkość</dt><dd>{{ $dashboard['network']['link_speed'] }}</dd></div>
                <div><dt>MAC</dt><dd><code>{{ $dashboard['network']['mac_address'] }}</code></dd></div>
                <div><dt>Producent</dt><dd>{{ $dashboard['network']['manufacturer'] }}</dd></div>
                <div><dt>Sterownik</dt><dd>{{ $dashboard['network']['driver_version'] }}</dd></div>
                <div><dt>Data sterownika</dt><dd>{{ $dashboard['network']['driver_date'] }}</dd></div>
            </dl>
        </article>

        <article>
            <h3>Transfer i błędy</h3>
            <dl class="pdo-detail-list">
                <div><dt>Odebrano</dt><dd>{{ $dashboard['network']['received'] }}</dd></div>
                <div><dt>Wysłano</dt><dd>{{ $dashboard['network']['sent'] }}</dd></div>
                <div><dt>Błędy odbioru</dt><dd>{{ $dashboard['network']['received_errors'] }}</dd></div>
                <div><dt>Błędy wysyłania</dt><dd>{{ $dashboard['network']['sent_errors'] }}</dd></div>
                <div><dt>Odrzucone RX</dt><dd>{{ $dashboard['network']['received_discards'] }}</dd></div>
                <div><dt>Odrzucone TX</dt><dd>{{ $dashboard['network']['sent_discards'] }}</dd></div>
            </dl>
        </article>

        @if($dashboard['network']['wifi']['available'])
            <article>
                <h3>Wi-Fi</h3>
                <dl class="pdo-detail-list">
                    <div><dt>SSID</dt><dd>{{ $dashboard['network']['wifi']['ssid'] }}</dd></div>
                    <div><dt>Siła sygnału</dt><dd>{{ $dashboard['network']['wifi']['signal'] }}</dd></div>
                    <div><dt>Standard</dt><dd>{{ $dashboard['network']['wifi']['radio_type'] }}</dd></div>
                    <div><dt>Kanał</dt><dd>{{ $dashboard['network']['wifi']['channel'] }}</dd></div>
                    <div><dt>Odbieranie</dt><dd>{{ $dashboard['network']['wifi']['receive_rate'] }}</dd></div>
                    <div><dt>Wysyłanie</dt><dd>{{ $dashboard['network']['wifi']['transmit_rate'] }}</dd></div>
                    <div><dt>BSSID</dt><dd><code>{{ $dashboard['network']['wifi']['bssid'] }}</code></dd></div>
                </dl>
            </article>
        @endif
    </div>
</section>

@if($dashboard['health']['recommendations'] !== [])
    <section class="pdo-recommendations" aria-labelledby="pdo-recommendations-title">
        <div class="pdo-recommendations__icon" aria-hidden="true">
            <x-platinum.icon name="alarm" :size="26" />
        </div>
        <div>
            <p class="pdo-kicker">Priorytety administratora</p>
            <h2 id="pdo-recommendations-title">Zalecane działania</h2>
            <ol>
                @foreach($dashboard['health']['recommendations'] as $recommendation)
                    <li>{{ $recommendation }}</li>
                @endforeach
            </ol>
        </div>
    </section>
@endif

<section id="historia" class="pdo-card pdo-card--wide" aria-labelledby="pdo-history-title">
    <header class="pdo-card__header">
        <div>
            <p class="pdo-kicker">Ostatnie pomiary</p>
            <h2 id="pdo-history-title">Historia heartbeatów</h2>
        </div>
        <span class="pdo-card__meta">{{ count($dashboard['history']) }} najnowszych wpisów</span>
    </header>

    @if($dashboard['history'] === [])
        <div class="pdo-empty">Brak pomiarów diagnostycznych.</div>
    @else
        <div class="pdo-history-desktop">
            <table class="pdo-table">
                <thead>
                    <tr>
                        <th scope="col">Czas</th>
                        <th scope="col">Diagnoza</th>
                        <th scope="col">Dostarczenie</th>
                        <th scope="col">Brama</th>
                        <th scope="col">DNS</th>
                        <th scope="col">Internet</th>
                        <th scope="col">Monitoring</th>
                        <th scope="col">Internet</th>
                        <th scope="col">DNS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dashboard['history'] as $entry)
                        <tr>
                            <td>{{ $entry['time'] }}</td>
                            <td>
                                <span class="pdo-status-badge pdo-status-badge--{{ $entry['state'] }}">
                                    {{ $entry['diagnostic'] }}
                                </span>
                            </td>
                            <td>{{ $entry['delivery_label'] }}</td>
                            @foreach(['gateway', 'dns', 'internet', 'monitoring'] as $checkKey)
                                <td>
                                    <span class="pdo-mini-state pdo-mini-state--{{ $entry[$checkKey]['state'] }}">
                                        {{ $entry[$checkKey]['label'] }}
                                    </span>
                                </td>
                            @endforeach
                            <td>{{ $entry['latency'] }}</td>
                            <td>{{ $entry['dns_latency'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pdo-history-mobile" aria-label="Historia heartbeatów w układzie kart">
            @foreach($dashboard['history'] as $entry)
                <article class="pdo-history-card pdo-history-card--{{ $entry['state'] }}">
                    <header>
                        <strong>{{ $entry['time'] }}</strong>
                        <span class="pdo-status-badge pdo-status-badge--{{ $entry['state'] }}">
                            {{ $entry['diagnostic'] }}
                        </span>
                    </header>
                    <dl>
                        <div><dt>Dostarczenie</dt><dd>{{ $entry['delivery_label'] }}</dd></div>
                        <div><dt>Brama</dt><dd>{{ $entry['gateway']['label'] }}</dd></div>
                        <div><dt>DNS</dt><dd>{{ $entry['dns']['label'] }}</dd></div>
                        <div><dt>Internet</dt><dd>{{ $entry['internet']['label'] }}</dd></div>
                        <div><dt>Monitoring</dt><dd>{{ $entry['monitoring']['label'] }}</dd></div>
                        <div><dt>Opóźnienie Internetu</dt><dd>{{ $entry['latency'] }}</dd></div>
                        <div><dt>Opóźnienie DNS</dt><dd>{{ $entry['dns_latency'] }}</dd></div>
                    </dl>
                </article>
            @endforeach
        </div>
    @endif
</section>

<section id="incydenty" class="pdo-card pdo-card--wide" aria-labelledby="pdo-incidents-title">
    <header class="pdo-card__header">
        <div>
            <p class="pdo-kicker">Ostatnie 30 dni</p>
            <h2 id="pdo-incidents-title">Incydenty i awarie</h2>
        </div>
        <span class="pdo-card__meta">{{ count($dashboard['incidents']) }} wpisów</span>
    </header>

    @if($dashboard['incidents'] === [])
        <div class="pdo-empty pdo-empty--success">
            Brak incydentów dla tego urządzenia w ostatnich 30 dniach.
        </div>
    @else
        <div class="pdo-incident-list">
            @foreach($dashboard['incidents'] as $incident)
                <article class="pdo-incident pdo-incident--{{ $incident['state'] }}">
                    <span class="pdo-incident__marker" aria-hidden="true"></span>
                    <div class="pdo-incident__content">
                        <header>
                            <div>
                                <h3>{{ $incident['title'] }}</h3>
                                <p>{{ $incident['summary'] }}</p>
                            </div>
                            <span class="pdo-status-badge pdo-status-badge--{{ $incident['state'] }}">
                                {{ $incident['status'] }}
                            </span>
                        </header>
                        <dl>
                            <div><dt>Start</dt><dd>{{ $incident['started_at'] }}</dd></div>
                            <div><dt>Koniec</dt><dd>{{ $incident['ended_at'] }}</dd></div>
                            <div><dt>Czas trwania</dt><dd>{{ $incident['duration'] }}</dd></div>
                        </dl>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

<p class="pdo-footnote">
    Health Score jest wskaźnikiem pomocniczym. Nie zastępuje diagnozy administratora, testów producenta sprzętu ani aktualnej kopii bezpieczeństwa.
</p>
@endsection
