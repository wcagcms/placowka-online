@php
    $overallState = match (true) {
        $stats['offline'] > 0 => 'critical',
        $stats['warning'] > 0 => 'warning',
        $stats['unknown'] > 0 || $stats['facilities'] === 0 || ($stats['online'] === 0 && $stats['inactive'] > 0) => 'unknown',
        default => 'healthy',
    };

    $overallLabel = match($overallState) {
        'critical' => 'Wykryto brak komunikacji',
        'warning' => 'Monitoring wymaga uwagi',
        'unknown' => 'Brak wystarczających danych',
        default => 'Monitoring działa prawidłowo',
    };

    $overallDescription = match($overallState) {
        'critical' => 'Co najmniej jedno aktywne urządzenie nie przesyła bieżących heartbeatów. Ostatnie znane pomiary nie są traktowane jako aktualny stan.',
        'warning' => 'Nie ma urządzeń bez komunikacji, ale przynajmniej jedno urządzenie zgłasza problem.',
        'unknown' => 'Nie można potwierdzić bieżącego stanu wszystkich placówek.',
        default => 'Wszystkie aktywne placówki i urządzenia przesyłają bieżące dane.',
    };

    $operationalScore = $stats['operational_score'];
    $scoreState = match (true) {
        $operationalScore === null => 'unknown',
        $operationalScore >= 90 => 'healthy',
        $operationalScore >= 70 => 'warning',
        default => 'critical',
    };
    $scoreLabel = match ($scoreState) {
        'healthy' => 'Stan bardzo dobry',
        'warning' => 'Stan wymaga uwagi',
        'critical' => 'Stan wymaga reakcji',
        default => 'Brak bieżącej oceny',
    };

    $incidentLabels = [
        'no_communication' => 'Brak komunikacji',
        'gateway_problem' => 'Problem z routerem lub bramą',
        'dns_problem' => 'Problem z DNS',
        'internet_problem' => 'Brak dostępu do Internetu',
        'monitoring_server_problem' => 'Problem z serwerem monitoringu',
        'windows_service_problem' => 'Problem z usługą Windows',
        'smart_problem' => 'Ostrzeżenie SMART dysku',
        'smart_failure' => 'Krytyczny stan SMART dysku',
        'smart_disk_problem' => 'Krytyczny problem SMART dysku',
        'windows_update_attention' => 'Windows Update wymaga uwagi',
        'defender_problem' => 'Ochrona Windows wymaga reakcji',
    ];
@endphp

<section class="psw-livebar psw-livebar--{{ $overallState }}"
         data-wow-livebar
         data-generated-at="{{ $generatedAt->toIso8601String() }}"
         aria-label="Bieżący stan odświeżania monitoringu">
    <div class="psw-livebar__state">
        <span class="psw-livebar__pulse" aria-hidden="true"></span>
        <div>
            <strong>{{ $overallState === 'healthy' ? 'System aktywny' : $overallLabel }}</strong>
            <span>Ostatni pomiar panelu: {{ $generatedAt->timezone('Europe/Warsaw')->format('H:i:s') }}</span>
        </div>
    </div>
    <div class="psw-livebar__summary" aria-label="Najważniejsze liczniki">
        <span><strong data-wow-counter data-wow-counter-key="live-online" data-wow-counter-value="{{ $stats['device_online'] }}">{{ $stats['device_online'] }}</strong> online</span>
        <span><strong data-wow-counter data-wow-counter-key="live-warning" data-wow-counter-value="{{ $stats['device_warning'] }}">{{ $stats['device_warning'] }}</strong> ostrzeżeń</span>
        <span><strong data-wow-counter data-wow-counter-key="live-offline" data-wow-counter-value="{{ $stats['device_offline'] }}">{{ $stats['device_offline'] }}</strong> offline</span>
    </div>
    <div class="psw-livebar__refresh">
        <span data-wow-countdown>Następne odświeżenie za 30 s</span>
        <progress data-wow-countdown-progress max="30" value="0">0 z 30 sekund</progress>
    </div>
</section>

<section class="pso-monitoring-hero pso-monitoring-hero--{{ $overallState }}" aria-labelledby="pso-live-state-title">
    <div class="psw-hero-layout">
        <div class="pso-monitoring-hero__content">
            <span class="pso-state-mark" aria-hidden="true">
                @if($overallState === 'healthy')
                    ✓
                @elseif($overallState === 'warning')
                    !
                @elseif($overallState === 'critical')
                    ×
                @else
                    ?
                @endif
            </span>

            <div>
                <p class="pso-kicker">Stan infrastruktury na żywo</p>
                <h2 id="pso-live-state-title">{{ $overallLabel }}</h2>
                <p>{{ $overallDescription }}</p>
            </div>
        </div>

        <div class="psw-score psw-score--{{ $scoreState }}">
            <svg class="psw-score__ring" viewBox="0 0 120 120" role="img" aria-label="Kondycja operacyjna: {{ $operationalScore !== null ? $operationalScore.' na 100' : 'brak danych' }}">
                <circle class="psw-score__track" cx="60" cy="60" r="50" pathLength="100"></circle>
                <circle class="psw-score__value" cx="60" cy="60" r="50" pathLength="100" stroke-dasharray="{{ $operationalScore ?? 0 }} 100"></circle>
            </svg>
            <div class="psw-score__content">
                <strong>{{ $operationalScore ?? '—' }}</strong>
                <span>{{ $operationalScore !== null ? '/ 100' : 'brak oceny' }}</span>
            </div>
            <div class="psw-score__caption">
                <span>Kondycja operacyjna</span>
                <strong>{{ $scoreLabel }}</strong>
                <small>Wiarygodność danych: {{ $stats['data_confidence'] }}%</small>
            </div>
        </div>
    </div>

    <dl class="pso-monitoring-hero__facts">
        <div>
            <dt>Placówki online</dt>
            <dd>{{ $stats['online'] }} / {{ $stats['facilities'] }}</dd>
        </div>
        <div>
            <dt>Świeże dane</dt>
            <dd>{{ $stats['fresh_devices'] }} / {{ $stats['active_devices'] }}</dd>
        </div>
        <div>
            <dt>Aktywne incydenty</dt>
            <dd>{{ $stats['open_incidents'] }}</dd>
        </div>
    </dl>
</section>

<section class="pso-stat-grid" aria-label="Podsumowanie centrum monitoringu">
    @foreach([
        ['label' => 'Placówki', 'value' => $stats['facilities'], 'state' => 'neutral', 'icon' => 'home', 'note' => 'wszystkie w systemie'],
        ['label' => 'Online', 'value' => $stats['online'], 'state' => 'healthy', 'icon' => 'internet', 'note' => 'działają prawidłowo'],
        ['label' => 'Ostrzeżenia', 'value' => $stats['warning'], 'state' => 'warning', 'icon' => 'alarm', 'note' => 'wymagają kontroli'],
        ['label' => 'Awarie', 'value' => $stats['offline'], 'state' => 'critical', 'icon' => 'alarm', 'note' => 'placówki offline'],
        ['label' => 'Urządzenia', 'value' => $stats['devices'], 'state' => 'neutral', 'icon' => 'device', 'note' => 'aktywnych i nieaktywnych'],
        ['label' => 'Incydenty', 'value' => $stats['open_incidents'], 'state' => $stats['open_incidents'] > 0 ? 'critical' : 'healthy', 'icon' => 'bell', 'note' => 'otwarte obecnie'],
    ] as $metric)
        <article class="pso-stat-card pso-stat-card--{{ $metric['state'] }}">
            <span class="pso-stat-card__icon" aria-hidden="true">
                <x-platinum.icon :name="$metric['icon']" :size="22" />
            </span>
            <div>
                <span>{{ $metric['label'] }}</span>
                <strong data-wow-counter data-wow-counter-key="metric-{{ \Illuminate\Support\Str::slug($metric['label']) }}" data-wow-counter-value="{{ $metric['value'] }}">{{ $metric['value'] }}</strong>
                <small>{{ $metric['note'] }}</small>
            </div>
        </article>
    @endforeach
</section>

<section class="pso-section psw-events" aria-labelledby="monitoring-events-title">
    <header class="pso-section-heading">
        <div>
            <p class="pso-kicker">Co właśnie się wydarzyło</p>
            <h2 id="monitoring-events-title">Ostatnie zdarzenia</h2>
            <p>Najświeższe awarie i powroty do prawidłowego działania z widocznych placówek.</p>
        </div>
        <span class="psw-events__live"><span aria-hidden="true"></span> Aktualizowane na żywo</span>
    </header>

    @if($recentEvents->isNotEmpty())
        <ol class="psw-timeline">
            @foreach($recentEvents as $event)
                @php
                    $eventIsActive = in_array($event->status, \App\Models\Incident::ACTIVE_STATUSES, true);
                    $eventState = match($event->status) {
                        \App\Models\Incident::STATUS_ACKNOWLEDGED,
                        \App\Models\Incident::STATUS_IN_PROGRESS => 'warning',
                        \App\Models\Incident::STATUS_RESOLVED,
                        \App\Models\Incident::STATUS_CLOSED => 'healthy',
                        default => 'critical',
                    };
                    $eventAt = $eventIsActive
                        ? $event->started_at
                        : ($event->ended_at
                            ?? $event->closed_at
                            ?? $event->last_status_change_at
                            ?? $event->updated_at);
                    $eventTypeLabel = $incidentLabels[$event->type]
                        ?? \Illuminate\Support\Str::headline((string) $event->type);
                    $eventSummary = trim((string) $event->summary);
                    $eventTitle = $eventSummary !== ''
                        ? ($eventIsActive ? $eventSummary : 'Rozwiązano: '.$eventSummary)
                        : ($eventIsActive
                            ? $eventTypeLabel
                            : 'Rozwiązano incydent: '.$eventTypeLabel);
                    $eventTimeLabel = $eventIsActive ? 'Rozpoczęcie' : 'Przywrócenie';
                @endphp
                <li class="psw-timeline__item psw-timeline__item--{{ $eventState }}">
                    <span class="psw-timeline__dot" aria-hidden="true"></span>
                    <time datetime="{{ $eventAt?->toIso8601String() }}"
                          title="{{ $eventTimeLabel }}: {{ $eventAt?->timezone('Europe/Warsaw')->format('d.m.Y H:i:s') ?? 'brak danych' }}">
                        {{ $eventAt?->timezone('Europe/Warsaw')->format('H:i') ?? '—' }}
                    </time>
                    <div>
                        <strong>{{ $eventTitle }}</strong>
                        <span>
                            {{ $event->facility?->code ?? 'Placówka' }}
                            @if($event->device)
                                / {{ $event->device->name }}
                            @endif
                            · {{ $eventTypeLabel }}
                            · {{ $eventTimeLabel }}
                        </span>
                    </div>
                    <span class="pso-status-chip pso-status-chip--{{ $eventState }}">
                        {{ $event->statusLabel() }}
                    </span>
                </li>
            @endforeach
        </ol>
    @else
        <div class="psw-all-clear">
            <span class="psw-all-clear__mark" aria-hidden="true">✓</span>
            <div>
                <strong>Spokojna praca systemu</strong>
                <p>W ostatnich 24 godzinach nie zarejestrowano nowych zdarzeń wymagających pokazania.</p>
            </div>
        </div>
    @endif
</section>

@if($openIncidents->isNotEmpty())
    <section class="pso-section" aria-labelledby="monitoring-incidents-title">
        <header class="pso-section-heading">
            <div>
                <p class="pso-kicker">Priorytet administratora</p>
                <h2 id="monitoring-incidents-title">Aktywne incydenty</h2>
                <p>Problemy wymagające weryfikacji lub interwencji.</p>
            </div>
            <span class="pso-count-badge pso-count-badge--critical">
                {{ $stats['open_incidents'] }}
                <span class="sr-only">aktywnych incydentów</span>
            </span>
        </header>

        <div class="pso-incident-grid">
            @foreach($openIncidents as $incident)
                @php
                    $incidentTypeLabel = $incidentLabels[$incident->type]
                        ?? str_replace('_', ' ', (string) $incident->type);
                    $incidentTitle = trim((string) $incident->summary) !== ''
                        ? (string) $incident->summary
                        : $incidentTypeLabel;
                    $incidentStatusState = match($incident->status) {
                        \App\Models\Incident::STATUS_ACKNOWLEDGED,
                        \App\Models\Incident::STATUS_IN_PROGRESS => 'warning',
                        default => 'critical',
                    };
                    $incidentPriorityState = match($incident->priority) {
                        'critical' => 'critical',
                        'high', 'medium' => 'warning',
                        default => 'neutral',
                    };
                    $incidentDuration = $incident->started_at
                        ? $incident->started_at->diffForHumans(now(), true)
                        : 'brak danych';
                @endphp

                <article class="pso-incident-card">
                    <header>
                        <span class="pso-incident-card__icon" aria-hidden="true">!</span>
                        <div>
                            <p>{{ $incident->facility?->code ?? 'Placówka' }} · {{ $incident->device?->name ?? 'Nieznane urządzenie' }}</p>
                            <h3>{{ $incidentTitle }}</h3>
                        </div>
                        <span class="pso-status-chip pso-status-chip--{{ $incidentStatusState }}">
                            {{ $incident->statusLabel() }}
                        </span>
                    </header>

                    <dl>
                        <div>
                            <dt>Typ</dt>
                            <dd>{{ $incidentTypeLabel }}</dd>
                        </div>
                        <div>
                            <dt>Priorytet</dt>
                            <dd>
                                <span class="pso-status-chip pso-status-chip--{{ $incidentPriorityState }}">
                                    {{ $incident->priorityLabel() }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt>Operator</dt>
                            <dd>{{ $incident->assignedUser?->name ?? 'Nieprzypisany' }}</dd>
                        </div>
                        <div>
                            <dt>Wystąpienia</dt>
                            <dd>{{ max(1, (int) $incident->occurrence_count) }}</dd>
                        </div>
                        <div>
                            <dt>Czas trwania</dt>
                            <dd>{{ $incidentDuration }}</dd>
                        </div>
                        <div>
                            <dt>Rozpoczęcie</dt>
                            <dd>{{ $incident->started_at?->timezone('Europe/Warsaw')->format('d.m.Y H:i') ?? 'brak danych' }}</dd>
                        </div>
                    </dl>

                    <div class="pso-card-actions">
                        <a class="btn small" href="{{ route('incidents.show', $incident) }}">
                            Otwórz incydent
                        </a>
                        @if($incident->device)
                            <a class="btn small secondary" href="{{ route('devices.heartbeats', $incident->device) }}">
                                Urządzenie
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif

<section class="pso-section" aria-labelledby="monitoring-facilities-title">
    <header class="pso-section-heading">
        <div>
            <p class="pso-kicker">Widok zbiorczy</p>
            <h2 id="monitoring-facilities-title">Placówki</h2>
            <p>Stan placówki jest wyznaczany na podstawie aktywnych urządzeń i ich ostatnich heartbeatów.</p>
        </div>
    </header>

    <div class="pso-facility-grid" data-monitoring-facility-grid>
        @forelse($facilities as $facility)
            @php
                $status = $facility->monitoring_status;
                $statusLabel = match($status) {
                    'online' => 'Online',
                    'warning' => 'Ostrzeżenie',
                    'offline' => 'Awaria',
                    'inactive' => 'Nieaktywna',
                    default => 'Brak danych',
                };

                $statusClass = match($status) {
                    'online' => 'healthy',
                    'warning' => 'warning',
                    'offline' => 'critical',
                    'inactive' => 'inactive',
                    default => 'unknown',
                };

                $latestSeen = $facility->latest_seen_at
                    ? \Illuminate\Support\Carbon::parse($facility->latest_seen_at)->timezone('Europe/Warsaw')
                    : null;

                $searchText = mb_strtolower(trim($facility->code.' '.$facility->name.' '.($facility->address ?? '')));
            @endphp

            <article class="pso-facility-card pso-facility-card--{{ $statusClass }}"
                     data-monitoring-facility-card
                     data-status="{{ $status }}"
                     data-search="{{ $searchText }}">
                <header class="pso-facility-card__header">
                    <span class="pso-facility-card__mark" aria-hidden="true">
                        @if($status === 'online')
                            ✓
                        @elseif($status === 'warning')
                            !
                        @elseif($status === 'offline')
                            ×
                        @elseif($status === 'inactive')
                            –
                        @else
                            ?
                        @endif
                    </span>

                    <div class="pso-facility-card__identity">
                        <p>{{ $facility->code }}</p>
                        <h3>{{ $facility->name }}</h3>
                    </div>

                    <span class="pso-status-chip pso-status-chip--{{ $statusClass }}">
                        {{ $statusLabel }}
                    </span>
                </header>

                <p class="pso-facility-card__summary">{{ $facility->monitoring_summary }}</p>

                <dl class="pso-facility-metrics">
                    <div>
                        <dt>Urządzenia</dt>
                        <dd>{{ $facility->devices->count() }}</dd>
                    </div>
                    <div>
                        <dt>Online</dt>
                        <dd>{{ $facility->online_devices_count }}</dd>
                    </div>
                    <div>
                        <dt>Problem</dt>
                        <dd>{{ $facility->problem_devices_count }}</dd>
                    </div>
                    <div>
                        <dt>Offline</dt>
                        <dd>{{ $facility->offline_devices_count }}</dd>
                    </div>
                </dl>

                <div class="pso-facility-card__meta">
                    <span>
                        <strong>Ostatni kontakt:</strong>
                        {{ $latestSeen ? $latestSeen->diffForHumans() : 'brak danych' }}
                    </span>
                    <span>
                        <strong>Aktywne incydenty:</strong>
                        {{ $facility->incidents->count() }}
                    </span>
                </div>

                <div class="psw-facility-pulse">
                    <div class="psw-facility-pulse__heading">
                        <span>Puls placówki teraz</span>
                        <strong>{{ $facility->monitoring_confidence }}% danych bieżących</strong>
                    </div>
                    @if(count($facility->monitoring_pulse) > 0)
                        <div class="psw-facility-pulse__segments" role="img" aria-label="Stan aktywnych urządzeń placówki {{ $facility->name }}">
                            @foreach($facility->monitoring_pulse as $pulseItem)
                                <span class="psw-facility-pulse__segment psw-facility-pulse__segment--{{ $pulseItem['state'] }}"
                                      title="{{ $pulseItem['name'] }}: {{ $pulseItem['label'] }}"></span>
                            @endforeach
                        </div>
                    @else
                        <div class="psw-facility-pulse__empty">Brak aktywnych urządzeń.</div>
                    @endif
                </div>

                @if($facility->devices->isNotEmpty())
                    <div class="pso-device-list" aria-label="Urządzenia placówki {{ $facility->name }}">
                        @foreach($facility->devices as $device)
                            @php
                                $deviceMonitoringStatus = $device->getAttribute('monitoring_status') ?: $device->status;
                                $deviceFreshness = $device->getAttribute('telemetry_freshness') ?: [];
                                $deviceState = match($deviceMonitoringStatus) {
                                    'online' => 'healthy',
                                    'problem' => 'warning',
                                    'offline' => 'critical',
                                    'inactive' => 'inactive',
                                    default => 'unknown',
                                };
                                $deviceLabel = match($deviceMonitoringStatus) {
                                    'online' => 'Online',
                                    'problem' => 'Problem',
                                    'offline' => 'Brak komunikacji',
                                    'inactive' => 'Nieaktywne',
                                    default => 'Brak danych',
                                };
                            @endphp

                            @php
                                $deviceIsFresh = data_get($deviceFreshness, 'is_fresh', false);
                                $deviceLastSeen = $deviceIsFresh
                                    ? ($device->last_seen_at?->diffForHumans() ?? 'brak kontaktu')
                                    : 'ostatni kontakt '.($device->last_seen_at?->diffForHumans() ?? 'nieznany');
                                $deviceNotice = $deviceIsFresh
                                    ? 'Urządzenie przesyła bieżące dane telemetryczne.'
                                    : (string) data_get($deviceFreshness, 'description', 'Brak bieżących danych telemetrycznych.');
                            @endphp

                            <div class="pso-device-row psw-device-row">
                                <span class="pso-device-row__dot pso-device-row__dot--{{ $deviceState }}" aria-hidden="true"></span>
                                <a class="psw-device-row__main" href="{{ route('devices.heartbeats', $device) }}">
                                    <span class="pso-device-row__name">{{ $device->name }}</span>
                                    <span class="pso-device-row__status">{{ $deviceLabel }}</span>
                                    <span class="pso-device-row__time">{{ $deviceLastSeen }}</span>
                                </a>
                                <button class="psw-device-row__preview"
                                        type="button"
                                        data-wow-device-preview
                                        data-device-name="{{ $device->name }}"
                                        data-device-facility="{{ $facility->code }} — {{ $facility->name }}"
                                        data-device-status="{{ $deviceLabel }}"
                                        data-device-state="{{ $deviceState }}"
                                        data-device-last-seen="{{ $deviceLastSeen }}"
                                        data-device-ip="{{ $device->last_ip ?: 'Brak danych' }}"
                                        data-device-latency="{{ $deviceIsFresh && $device->last_latency_ms !== null ? $device->last_latency_ms.' ms' : 'Brak bieżących danych' }}"
                                        data-device-agent="{{ $device->agent_version ?: 'Brak danych' }}"
                                        data-device-notice="{{ $deviceNotice }}"
                                        data-device-url="{{ route('devices.heartbeats', $device) }}"
                                        aria-label="Szybki podgląd urządzenia {{ $device->name }}">
                                    <span aria-hidden="true">›</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="pso-empty-inline">Brak urządzeń przypisanych do placówki.</div>
                @endif

                <div class="pso-card-actions">
                    <a class="btn small" href="{{ route('facilities.show', $facility) }}">Otwórz placówkę</a>
                    <a class="btn small secondary" href="{{ route('reports.facility', $facility) }}">Raport</a>
                </div>
            </article>
        @empty
            <div class="pso-empty-state">
                <span aria-hidden="true">○</span>
                <h3>Brak placówek</h3>
                <p>Dodaj pierwszą placówkę, aby rozpocząć monitoring.</p>
                @if(auth()->user()?->isAdmin())
                    <a class="btn" href="{{ route('facilities.create') }}">Dodaj placówkę</a>
                @endif
            </div>
        @endforelse
    </div>

    <div class="pso-empty-state pso-empty-state--filter" data-monitoring-empty-filter hidden>
        <span aria-hidden="true">⌕</span>
        <h3>Brak pasujących placówek</h3>
        <p>Zmień wyszukiwaną frazę albo wybrany filtr stanu.</p>
    </div>
</section>
