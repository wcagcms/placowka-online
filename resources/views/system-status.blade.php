@extends('layouts.panel')

@section('title', 'Stan systemu — Placówka Online')
@section('eyebrow', 'Diagnostyka platformy')
@section('page_title', 'Stan systemu')
@section('page_lead', 'Kontrola schedulera, bazy danych, poczty, logów, uprawnień katalogów i aktualności danych monitoringu.')

@push('head')
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-observability.css') }}">
@endpush

@section('page_actions')
    <a class="btn secondary" href="{{ route('system-settings.edit') }}">Ustawienia</a>
    <a class="btn" href="{{ route('system.status') }}">Sprawdź ponownie</a>
@endsection

@section('content')
@php
    $checkCollection = collect($checks);
    $failedChecks = $checkCollection->where('status', 'offline')->count();
    $warningChecks = $checkCollection->where('status', 'problem')->count();
    $passedChecks = $checkCollection->where('status', 'online')->count();
    $unwritableCount = collect($storageChecks)->where('writable', false)->count();

    $systemState = $failedChecks > 0 || $unwritableCount > 0
        ? 'critical'
        : ($warningChecks > 0 ? 'warning' : 'healthy');

    $systemLabel = match($systemState) {
        'critical' => 'System wymaga interwencji',
        'warning' => 'System działa z ostrzeżeniami',
        default => 'System działa prawidłowo',
    };

    $systemDescription = match($systemState) {
        'critical' => 'Co najmniej jeden kluczowy test zakończył się błędem albo katalog nie ma prawa zapisu.',
        'warning' => 'Usługi podstawowe działają, ale konfiguracja wymaga sprawdzenia.',
        default => 'Wszystkie podstawowe testy administracyjne zakończyły się poprawnie.',
    };

    $latestHeartbeatState = ! $lastHeartbeat
        ? 'unknown'
        : ($lastHeartbeat->status === 'online' && $lastHeartbeat->internet_ok && $lastHeartbeat->dns_ok
            ? 'healthy'
            : ($lastHeartbeat->status === 'offline' ? 'critical' : 'warning'));

    $incidentTypeLabel = $lastOpenIncident
        ? match($lastOpenIncident->type) {
            'no_communication' => 'Brak komunikacji',
            'gateway_problem' => 'Problem z routerem lub bramą',
            'dns_problem' => 'Problem z DNS',
            'internet_problem' => 'Brak dostępu do Internetu',
            'monitoring_server_problem' => 'Problem z serwerem monitoringu',
            'windows_service_problem' => 'Problem z usługą Windows',
            'smart_problem' => 'Ostrzeżenie SMART dysku',
            default => $lastOpenIncident->type,
        }
        : null;
@endphp

<div class="pso-page">
    <section class="pso-system-hero pso-system-hero--{{ $systemState }}" aria-labelledby="pso-system-overview-title">
        <div class="pso-system-hero__main">
            <span class="pso-state-mark" aria-hidden="true">
                @if($systemState === 'healthy')
                    ✓
                @elseif($systemState === 'warning')
                    !
                @else
                    ×
                @endif
            </span>
            <div>
                <p class="pso-kicker">Diagnoza platformy</p>
                <h2 id="pso-system-overview-title">{{ $systemLabel }}</h2>
                <p>{{ $systemDescription }}</p>
            </div>
        </div>

        <dl class="pso-system-hero__facts">
            <div>
                <dt>Testy prawidłowe</dt>
                <dd>{{ $passedChecks }}</dd>
            </div>
            <div>
                <dt>Ostrzeżenia</dt>
                <dd>{{ $warningChecks }}</dd>
            </div>
            <div>
                <dt>Błędy</dt>
                <dd>{{ $failedChecks + $unwritableCount }}</dd>
            </div>
            <div>
                <dt>Sprawdzono</dt>
                <dd>{{ $now->format('H:i:s') }}</dd>
            </div>
        </dl>
    </section>

    <section class="pso-stat-grid" aria-label="Podstawowe statystyki systemu">
        @foreach([
            ['label' => 'Placówki aktywne', 'value' => $counts['facilities_active'], 'note' => 'z '.$counts['facilities_total'].' wszystkich', 'state' => 'neutral', 'icon' => 'home'],
            ['label' => 'Urządzenia', 'value' => $counts['devices_total'], 'note' => $counts['devices_archived'].' archiwalnych', 'state' => 'neutral', 'icon' => 'device'],
            ['label' => 'Online', 'value' => $counts['devices_online'], 'note' => 'urządzeń dostępnych', 'state' => 'healthy', 'icon' => 'internet'],
            ['label' => 'Problemy', 'value' => $counts['devices_problem'], 'note' => 'urządzeń z ostrzeżeniem', 'state' => $counts['devices_problem'] > 0 ? 'warning' : 'healthy', 'icon' => 'alarm'],
            ['label' => 'Offline', 'value' => $counts['devices_offline'], 'note' => 'urządzeń niedostępnych', 'state' => $counts['devices_offline'] > 0 ? 'critical' : 'healthy', 'icon' => 'alarm'],
            ['label' => 'Heartbeat 24h', 'value' => $counts['heartbeats_24h'], 'note' => number_format($counts['heartbeats_total'], 0, ',', ' ').' łącznie', 'state' => $counts['heartbeats_24h'] > 0 ? 'healthy' : 'warning', 'icon' => 'history'],
        ] as $metric)
            <article class="pso-stat-card pso-stat-card--{{ $metric['state'] }}">
                <span class="pso-stat-card__icon" aria-hidden="true">
                    <x-platinum.icon :name="$metric['icon']" :size="22" />
                </span>
                <div>
                    <span>{{ $metric['label'] }}</span>
                    <strong>{{ $metric['value'] }}</strong>
                    <small>{{ $metric['note'] }}</small>
                </div>
            </article>
        @endforeach
    </section>

    <section class="pso-section" aria-labelledby="pso-system-checks-title">
        <header class="pso-section-heading">
            <div>
                <p class="pso-kicker">Kontrole podstawowe</p>
                <h2 id="pso-system-checks-title">Kluczowe elementy platformy</h2>
                <p>Każdy test zawiera status tekstowy, wartość techniczną i opis możliwego działania.</p>
            </div>
            <span class="pso-count-badge">{{ count($checks) }}</span>
        </header>

        <div class="pso-system-check-grid">
            @foreach($checks as $check)
                @php
                    $checkState = match($check['status']) {
                        'online' => 'healthy',
                        'problem' => 'warning',
                        'offline' => 'critical',
                        default => 'unknown',
                    };
                    $checkLabel = match($check['status']) {
                        'online' => 'Prawidłowo',
                        'problem' => 'Ostrzeżenie',
                        'offline' => 'Błąd',
                        default => 'Brak danych',
                    };
                    $checkIcon = str_contains(mb_strtolower($check['name']), 'mail')
                        ? 'bell'
                        : (str_contains(mb_strtolower($check['name']), 'cron')
                            ? 'history'
                            : (str_contains(mb_strtolower($check['name']), 'baza')
                                ? 'smart'
                                : 'health'));
                @endphp

                <article class="pso-system-check pso-system-check--{{ $checkState }}">
                    <header>
                        <span class="pso-system-check__icon" aria-hidden="true">
                            <x-platinum.icon :name="$checkIcon" :size="22" />
                        </span>
                        <div>
                            <h3>{{ $check['name'] }}</h3>
                            <span class="pso-status-chip pso-status-chip--{{ $checkState }}">{{ $checkLabel }}</span>
                        </div>
                    </header>

                    <div class="pso-system-check__value">{{ $check['value'] }}</div>
                    <p>{{ $check['description'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="pso-section" aria-labelledby="pso-system-live-data-title">
        <header class="pso-section-heading">
            <div>
                <p class="pso-kicker">Aktualność monitoringu</p>
                <h2 id="pso-system-live-data-title">Ostatnie dane operacyjne</h2>
                <p>Najnowszy heartbeat oraz najnowszy aktywny incydent zapisany w bazie.</p>
            </div>
        </header>

        <div class="pso-live-data-grid">
            <article class="pso-live-card pso-live-card--{{ $latestHeartbeatState }}">
                <header>
                    <span class="pso-live-card__icon" aria-hidden="true">
                        <x-platinum.icon name="history" :size="23" />
                    </span>
                    <div>
                        <p>Agent</p>
                        <h3>Ostatni heartbeat</h3>
                    </div>
                    <span class="pso-status-chip pso-status-chip--{{ $latestHeartbeatState }}">
                        @if($latestHeartbeatState === 'healthy') Aktualny
                        @elseif($latestHeartbeatState === 'warning') Problem
                        @elseif($latestHeartbeatState === 'critical') Offline
                        @else Brak danych
                        @endif
                    </span>
                </header>

                @if($lastHeartbeat)
                    <dl class="pso-detail-list">
                        <div>
                            <dt>Czas</dt>
                            <dd>{{ $lastHeartbeat->created_at->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') }}</dd>
                        </div>
                        <div>
                            <dt>Placówka</dt>
                            <dd>{{ $lastHeartbeat->device?->facility?->code ?: '-' }} — {{ $lastHeartbeat->device?->facility?->name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Urządzenie</dt>
                            <dd>{{ $lastHeartbeat->device?->name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Internet / DNS</dt>
                            <dd>{{ $lastHeartbeat->internet_ok ? 'Internet OK' : 'Internet: błąd' }} · {{ $lastHeartbeat->dns_ok ? 'DNS OK' : 'DNS: błąd' }}</dd>
                        </div>
                        <div>
                            <dt>Opóźnienie</dt>
                            <dd>{{ $lastHeartbeat->latency_ms !== null ? $lastHeartbeat->latency_ms.' ms' : 'brak danych' }}</dd>
                        </div>
                    </dl>

                    @if($lastHeartbeat->device)
                        <div class="pso-card-actions">
                            <a class="btn small" href="{{ route('devices.heartbeats', $lastHeartbeat->device) }}">Otwórz urządzenie</a>
                        </div>
                    @endif
                @else
                    <div class="pso-empty-inline">Brak heartbeatów w bazie.</div>
                @endif
            </article>

            <article class="pso-live-card pso-live-card--{{ $lastOpenIncident ? 'critical' : 'healthy' }}">
                <header>
                    <span class="pso-live-card__icon" aria-hidden="true">
                        <x-platinum.icon name="alarm" :size="23" />
                    </span>
                    <div>
                        <p>Incydenty</p>
                        <h3>Najnowsza aktywna awaria</h3>
                    </div>
                    <span class="pso-status-chip pso-status-chip--{{ $lastOpenIncident ? 'critical' : 'healthy' }}">
                        {{ $lastOpenIncident ? 'Aktywna' : 'Brak awarii' }}
                    </span>
                </header>

                @if($lastOpenIncident)
                    <dl class="pso-detail-list">
                        <div>
                            <dt>Typ</dt>
                            <dd>{{ $incidentTypeLabel }}</dd>
                        </div>
                        <div>
                            <dt>Rozpoczęcie</dt>
                            <dd>{{ $lastOpenIncident->started_at?->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Placówka</dt>
                            <dd>{{ $lastOpenIncident->facility?->code ?: '-' }} — {{ $lastOpenIncident->facility?->name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Urządzenie</dt>
                            <dd>{{ $lastOpenIncident->device?->name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Czas trwania</dt>
                            <dd>{{ $lastOpenIncident->started_at?->diffForHumans(now(), true) ?: '-' }}</dd>
                        </div>
                    </dl>

                    <div class="pso-card-actions">
                        @if($lastOpenIncident->device)
                            <a class="btn small" href="{{ route('devices.heartbeats', $lastOpenIncident->device) }}">Otwórz urządzenie</a>
                        @endif
                        @if($lastOpenIncident->facility)
                            <a class="btn small secondary" href="{{ route('facilities.show', $lastOpenIncident->facility) }}">Placówka</a>
                        @endif
                    </div>
                @else
                    <div class="pso-empty-inline pso-empty-inline--success">Brak aktywnych awarii.</div>
                @endif
            </article>
        </div>
    </section>

    <section class="pso-section" aria-labelledby="pso-device-status-title">
        <header class="pso-section-heading">
            <div>
                <p class="pso-kicker">Baza urządzeń</p>
                <h2 id="pso-device-status-title">Urządzenia według statusu</h2>
                <p>Zestawienie nie obejmuje urządzeń archiwalnych.</p>
            </div>
        </header>

        <div class="pso-status-distribution">
            @foreach([
                ['label' => 'Online', 'value' => $counts['devices_online'], 'state' => 'healthy'],
                ['label' => 'Problem', 'value' => $counts['devices_problem'], 'state' => 'warning'],
                ['label' => 'Offline', 'value' => $counts['devices_offline'], 'state' => 'critical'],
                ['label' => 'Nieznany', 'value' => $counts['devices_unknown'], 'state' => 'unknown'],
                ['label' => 'Archiwalne', 'value' => $counts['devices_archived'], 'state' => 'inactive'],
            ] as $statusItem)
                @php
                    $totalForPercent = max(1, $counts['devices_total'] + $counts['devices_archived']);
                    $statusPercent = round(($statusItem['value'] / $totalForPercent) * 100, 1);
                @endphp
                <article class="pso-distribution-card pso-distribution-card--{{ $statusItem['state'] }}">
                    <header>
                        <span>{{ $statusItem['label'] }}</span>
                        <strong>{{ $statusItem['value'] }}</strong>
                    </header>
                    <progress
                        max="100"
                        value="{{ $statusPercent }}"
                        aria-label="{{ $statusItem['label'] }}: {{ number_format($statusPercent, 1, ',', ' ') }} procent wszystkich zapisanych urządzeń"
                    >
                        {{ number_format($statusPercent, 1, ',', ' ') }}%
                    </progress>
                    <small>{{ number_format($statusPercent, 1, ',', ' ') }}% wszystkich zapisanych urządzeń</small>
                </article>
            @endforeach
        </div>
    </section>

    <section class="pso-section" aria-labelledby="pso-configuration-title">
        <header class="pso-section-heading">
            <div>
                <p class="pso-kicker">Konfiguracja środowiska</p>
                <h2 id="pso-configuration-title">Aplikacja i poczta</h2>
                <p>Bezpieczny podgląd ustawień technicznych bez wyświetlania haseł, tokenów ani kluczy.</p>
            </div>
        </header>

        <div class="pso-config-grid">
            <article class="pso-config-card">
                <header>
                    <span class="pso-icon-box" aria-hidden="true"><x-platinum.icon name="settings" :size="22" /></span>
                    <div>
                        <p>Środowisko</p>
                        <h3>Konfiguracja aplikacji</h3>
                    </div>
                </header>
                <dl class="pso-detail-list">
                    <div><dt>Nazwa</dt><dd>{{ $appInfo['app_name'] }}</dd></div>
                    <div><dt>Środowisko</dt><dd>{{ $appInfo['app_env'] }}</dd></div>
                    <div><dt>Adres</dt><dd><code>{{ $appInfo['app_url'] }}</code></dd></div>
                    <div><dt>Strefa czasu</dt><dd>{{ $appInfo['timezone'] }}</dd></div>
                    <div><dt>PHP</dt><dd>{{ $appInfo['php_version'] }}</dd></div>
                    <div><dt>Laravel</dt><dd>{{ $appInfo['laravel_version'] }}</dd></div>
                </dl>
            </article>

            <article class="pso-config-card">
                <header>
                    <span class="pso-icon-box" aria-hidden="true"><x-platinum.icon name="bell" :size="22" /></span>
                    <div>
                        <p>Powiadomienia</p>
                        <h3>Konfiguracja poczty</h3>
                    </div>
                </header>
                <dl class="pso-detail-list">
                    <div><dt>Mailer</dt><dd>{{ $mail['default_mailer'] ?: 'brak' }}</dd></div>
                    <div><dt>Adres nadawcy</dt><dd>{{ $mail['from_address'] ?: 'brak' }}</dd></div>
                    <div><dt>SMTP host</dt><dd>{{ $mail['smtp_host_present'] ? 'ustawiony' : 'brak / nie dotyczy' }}</dd></div>
                    <div><dt>SMTP port</dt><dd>{{ $mail['smtp_port'] ?: 'brak / nie dotyczy' }}</dd></div>
                    <div><dt>Szyfrowanie</dt><dd>{{ $mail['smtp_encryption'] ?: 'brak / nie dotyczy' }}</dd></div>
                </dl>
                <p class="pso-security-note">Login SMTP, hasła, tokeny i klucze nie są wyświetlane.</p>
            </article>
        </div>
    </section>

    <section class="pso-section" aria-labelledby="pso-storage-title">
        <header class="pso-section-heading">
            <div>
                <p class="pso-kicker">System plików</p>
                <h2 id="pso-storage-title">Uprawnienia katalogów</h2>
                <p>Kontrola możliwości zapisu w katalogach wymaganych przez aplikację i panel.</p>
            </div>
            <span class="pso-count-badge {{ $unwritableCount > 0 ? 'pso-count-badge--critical' : '' }}">
                {{ count($storageChecks) - $unwritableCount }} / {{ count($storageChecks) }}
            </span>
        </header>

        <div class="pso-storage-grid">
            @foreach($storageChecks as $item)
                <article class="pso-storage-card pso-storage-card--{{ $item['writable'] ? 'healthy' : 'critical' }}">
                    <header>
                        <span class="pso-storage-card__mark" aria-hidden="true">{{ $item['writable'] ? '✓' : '×' }}</span>
                        <div>
                            <h3>{{ $item['name'] }}</h3>
                            <span class="pso-status-chip pso-status-chip--{{ $item['writable'] ? 'healthy' : 'critical' }}">
                                {{ $item['writable'] ? 'Zapis OK' : 'Brak zapisu' }}
                            </span>
                        </div>
                    </header>
                    <code>{{ $item['path'] }}</code>
                </article>
            @endforeach
        </div>
    </section>

    <section class="pso-section" aria-labelledby="pso-logs-title">
        <header class="pso-section-heading">
            <div>
                <p class="pso-kicker">Diagnostyka techniczna</p>
                <h2 id="pso-logs-title">Logi systemowe</h2>
                <p>Ostatnie linie najważniejszych logów. Sekcje są domyślnie zwinięte dla większej czytelności.</p>
            </div>
        </header>

        <div class="pso-log-list">
            @foreach($logs as $key => $log)
                @php
                    $logTitle = match($key) {
                        'cron_schedule' => 'Cron scheduler',
                        'check_status' => 'Kontrola statusów urządzeń',
                        'cleanup_heartbeats' => 'Czyszczenie heartbeatów',
                        default => 'Plik kontrolny crona',
                    };
                    $logState = $log['exists'] ? 'healthy' : 'warning';
                @endphp

                <details class="pso-log-card pso-log-card--{{ $logState }}">
                    <summary>
                        <span class="pso-log-card__mark" aria-hidden="true">{{ $log['exists'] ? '✓' : '!' }}</span>
                        <span class="pso-log-card__title">
                            <strong>{{ $logTitle }}</strong>
                            <small>{{ $log['exists'] ? 'Plik dostępny' : 'Plik jeszcze nie istnieje' }}</small>
                        </span>
                        @if($log['exists'])
                            <span class="pso-log-card__meta">
                                {{ number_format($log['size'], 0, ',', ' ') }} B ·
                                {{ $log['modified_at']?->format('Y-m-d H:i:s') }}
                            </span>
                        @endif
                    </summary>

                    <div class="pso-log-card__body">
                        <p><strong>Ścieżka:</strong> <code>{{ $log['path'] }}</code></p>

                        @if($log['exists'] && count($log['last_lines']))
                            <pre class="pso-log-output" tabindex="0">@foreach($log['last_lines'] as $line){{ $line }}
@endforeach</pre>
                        @elseif($log['exists'])
                            <div class="pso-empty-inline">Plik istnieje, ale jest pusty.</div>
                        @else
                            <div class="pso-empty-inline">Plik jeszcze nie istnieje.</div>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    </section>
</div>
@endsection
