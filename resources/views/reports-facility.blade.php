@extends('layouts.panel')

@php
    $facility = $report['facility'];
    $s24 = $report['summaries']['24h'];
    $s7 = $report['summaries']['7d'];
    $s30 = $report['summaries']['30d'];

    $heroState = ! $facility->is_active
        ? 'inactive'
        : ($s30['open_incidents'] > 0
            ? 'critical'
            : ($s30['incident_count'] > 0 || ($s30['availability_percent'] !== null && $s30['availability_percent'] < 99.9)
                ? 'warning'
                : 'healthy'));

    $heroLabel = match($heroState) {
        'critical' => 'Placówka ma aktywną awarię',
        'warning' => 'Dostępność wymaga analizy',
        'inactive' => 'Placówka jest nieaktywna',
        default => 'Dostępność jest stabilna',
    };
@endphp

@section('title', 'Raport — '.$facility->code.' — Placówka Online')
@section('eyebrow', 'Raport placówki')
@section('page_title', $facility->code.' — '.$facility->name)
@section('page_lead', 'Dostępność Internetu, czas przerw i historia incydentów za 24 godziny, 7 dni i 30 dni.')

@push('head')
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-observability.css') }}">
@endpush

@section('page_actions')
    <button
        class="btn secondary"
        type="button"
        data-print-report
        data-print-title="Raport {{ $facility->code }} — Placówka Online"
    >
        Drukuj raport
    </button>
    <a class="btn secondary" href="{{ route('reports.index') }}">Wszystkie raporty</a>
    <a class="btn secondary" href="{{ route('facilities.show', $facility) }}">Placówka</a>
    <a class="btn" href="{{ route('reports.facility', $facility) }}">Odśwież raport</a>
@endsection

@section('content')
<div class="pso-page pso-print-document" data-print-report-root>
    <header class="pso-print-header">
        <div class="pso-print-brand">
            <span class="pso-print-brand__mark" aria-hidden="true">PO</span>
            <div>
                <p>Placówka Online</p>
                <h2>Raport dostępności placówki</h2>
            </div>
        </div>

        <dl class="pso-print-meta">
            <div>
                <dt>Placówka</dt>
                <dd>{{ $facility->code }} — {{ $facility->name }}</dd>
            </div>
            @if($facility->address)
                <div>
                    <dt>Adres</dt>
                    <dd>{{ $facility->address }}</dd>
                </div>
            @endif
            <div>
                <dt>Zakres danych</dt>
                <dd>24 godziny, 7 dni i 30 dni</dd>
            </div>
            <div>
                <dt>Wygenerowano</dt>
                <dd>{{ $report['generated_at']->timezone('Europe/Warsaw')->format('d.m.Y, H:i:s') }}</dd>
            </div>
        </dl>
    </header>
    <section class="pso-facility-report-hero pso-facility-report-hero--{{ $heroState }}" aria-labelledby="pso-facility-report-title">
        <div class="pso-facility-report-hero__main">
            <span class="pso-state-mark" aria-hidden="true">
                @if($heroState === 'healthy')
                    ✓
                @elseif($heroState === 'warning')
                    !
                @elseif($heroState === 'critical')
                    ×
                @else
                    –
                @endif
            </span>
            <div>
                <p class="pso-kicker">Ocena ostatnich 30 dni</p>
                <h2 id="pso-facility-report-title">{{ $heroLabel }}</h2>
                <p>
                    Raport obejmuje {{ $s30['device_count'] }}
                    {{ $s30['device_count'] === 1 ? 'urządzenie' : 'urządzeń' }}
                    i {{ $s30['incident_count'] }} zarejestrowanych incydentów.
                </p>
            </div>
        </div>

        <div class="pso-facility-report-hero__score">
            <span>Dostępność 30 dni</span>
            <strong>{{ $s30['availability_text'] }}</strong>
            <small>Łączne przerwy: {{ $s30['downtime_text'] }}</small>
        </div>
    </section>

    <section class="pso-period-summary-grid" aria-label="Podsumowanie okresów raportu">
        @foreach($report['periods'] as $key => $period)
            @php
                $summary = $report['summaries'][$key];
                $availability = $summary['availability_percent'];
                $periodState = $availability === null
                    ? 'unknown'
                    : ($availability >= 99.9 ? 'healthy' : ($availability >= 99 ? 'warning' : 'critical'));
            @endphp

            <article class="pso-period-summary-card pso-period-summary-card--{{ $periodState }}">
                <header>
                    <span>{{ $period['label'] }}</span>
                    <span class="pso-status-chip pso-status-chip--{{ $periodState }}">
                        @if($periodState === 'healthy') Stabilnie
                        @elseif($periodState === 'warning') Uwaga
                        @elseif($periodState === 'critical') Problem
                        @else Brak danych
                        @endif
                    </span>
                </header>

                <strong>{{ $summary['availability_text'] }}</strong>
                <progress
                    max="100"
                    value="{{ $availability ?? 0 }}"
                    aria-label="Dostępność za {{ $period['label'] }}: {{ $summary['availability_text'] }}"
                >
                    {{ $summary['availability_text'] }}
                </progress>

                <dl>
                    <div>
                        <dt>Czas niedostępności</dt>
                        <dd>{{ $summary['downtime_text'] }}</dd>
                    </div>
                    <div>
                        <dt>Incydenty</dt>
                        <dd>{{ $summary['incident_count'] }}</dd>
                    </div>
                    <div>
                        <dt>Aktywne awarie</dt>
                        <dd>{{ $summary['open_incidents'] }}</dd>
                    </div>
                </dl>
            </article>
        @endforeach
    </section>

    <section class="pso-section" aria-labelledby="pso-report-devices-title">
        <header class="pso-section-heading">
            <div>
                <p class="pso-kicker">Urządzenia placówki</p>
                <h2 id="pso-report-devices-title">Dostępność każdego urządzenia</h2>
                <p>Porównanie bieżącego stanu urządzenia z dostępnością wyliczoną na podstawie incydentów.</p>
            </div>
            <span class="pso-count-badge">{{ $report['devices']->count() }}</span>
        </header>

        @if($report['devices']->count())
            <div class="pso-device-report-grid">
                @foreach($report['devices'] as $row)
                    @php
                        $device = $row['device'];
                        $d24 = $row['summaries']['24h'];
                        $d7 = $row['summaries']['7d'];
                        $d30 = $row['summaries']['30d'];

                        $deviceState = match($device->status) {
                            'online' => 'healthy',
                            'problem' => 'warning',
                            'offline' => 'critical',
                            default => 'unknown',
                        };

                        $deviceStatusLabel = match($device->status) {
                            'online' => 'Online',
                            'problem' => 'Problem',
                            'offline' => 'Offline',
                            default => 'Brak danych',
                        };
                    @endphp

                    <article class="pso-device-report-card pso-device-report-card--{{ $deviceState }}">
                        <header>
                            <div class="pso-device-report-card__identity">
                                <span class="pso-device-row__dot pso-device-row__dot--{{ $deviceState }}" aria-hidden="true"></span>
                                <div>
                                    <h3>{{ $device->name }}</h3>
                                    <p>{{ $device->uuid }}</p>
                                </div>
                            </div>
                            <span class="pso-status-chip pso-status-chip--{{ $deviceState }}">{{ $deviceStatusLabel }}</span>
                        </header>

                        <div class="pso-device-report-card__periods">
                            @foreach([
                                ['label' => '24h', 'summary' => $d24],
                                ['label' => '7 dni', 'summary' => $d7],
                                ['label' => '30 dni', 'summary' => $d30],
                            ] as $period)
                                @php $value = $period['summary']['availability_percent']; @endphp
                                <div>
                                    <span>{{ $period['label'] }}</span>
                                    <strong>{{ $period['summary']['availability_text'] }}</strong>
                                    <small>Przerwy: {{ $period['summary']['downtime_text'] }}</small>
                                    <progress
                                        max="100"
                                        value="{{ $value ?? 0 }}"
                                        aria-label="{{ $device->name }}, dostępność za {{ $period['label'] }}: {{ $period['summary']['availability_text'] }}"
                                    >
                                        {{ $period['summary']['availability_text'] }}
                                    </progress>
                                </div>
                            @endforeach
                        </div>

                        <dl class="pso-device-report-card__facts">
                            <div>
                                <dt>Incydenty 30 dni</dt>
                                <dd>{{ $d30['incident_count'] }}</dd>
                            </div>
                            <div>
                                <dt>Aktywne</dt>
                                <dd>{{ $d30['open_incidents'] }}</dd>
                            </div>
                            <div>
                                <dt>Ostatni kontakt</dt>
                                <dd>{{ $device->last_seen_at?->diffForHumans() ?? 'brak danych' }}</dd>
                            </div>
                        </dl>

                        <div class="pso-card-actions">
                            <a class="btn small" href="{{ route('devices.heartbeats', $device) }}">Szczegóły urządzenia</a>
                            @if(auth()->user()?->isAdmin())
                                <a class="btn small secondary" href="{{ route('devices.edit', $device) }}">Zarządzaj</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="pso-empty-state">
                <span aria-hidden="true">○</span>
                <h3>Brak aktywnych urządzeń</h3>
                <p>Dodaj urządzenie do placówki, aby pojawiło się w raporcie.</p>
                @if(auth()->user()?->isAdmin())
                    <a class="btn" href="{{ route('facilities.devices.create', $facility) }}">Dodaj urządzenie</a>
                @endif
            </div>
        @endif
    </section>

    <section class="pso-section" aria-labelledby="pso-report-incidents-title">
        <header class="pso-section-heading">
            <div>
                <p class="pso-kicker">Historia zdarzeń</p>
                <h2 id="pso-report-incidents-title">Ostatnie awarie</h2>
                <p>Do 30 najnowszych incydentów zarejestrowanych w tej placówce.</p>
            </div>
            <span class="pso-count-badge {{ $report['last_incidents']->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)->count() > 0 ? 'pso-count-badge--critical' : '' }}">
                {{ $report['last_incidents']->count() }}
            </span>
        </header>

        @if($report['last_incidents']->count())
            <div class="pso-timeline">
                @foreach($report['last_incidents'] as $incident)
                    @php
                        $duration = '-';
                        if ($incident->duration_seconds !== null) {
                            $minutes = intdiv($incident->duration_seconds, 60);
                            $seconds = $incident->duration_seconds % 60;
                            $duration = $minutes > 0
                                ? $minutes.' min '.$seconds.' sek.'
                                : $seconds.' sek.';
                        }
                        if (in_array($incident->status, \App\Models\Incident::ACTIVE_STATUSES, true) && $incident->started_at) {
                            $duration = $incident->started_at->diffForHumans(now(), true);
                        }

                        $typeLabel = match($incident->type) {
                            'no_communication' => 'Brak komunikacji',
                            'gateway_problem' => 'Problem z routerem lub bramą',
                            'dns_problem' => 'Problem z DNS',
                            'internet_problem' => 'Brak dostępu do Internetu',
                            'monitoring_server_problem' => 'Problem z serwerem monitoringu',
                            'windows_service_problem' => 'Problem z usługą Windows',
                            'smart_problem' => 'Ostrzeżenie SMART dysku',
                            default => $incident->type,
                        };

                        $incidentState = in_array($incident->status, \App\Models\Incident::ACTIVE_STATUSES, true) ? 'critical' : 'healthy';
                    @endphp

                    <article class="pso-timeline-item pso-timeline-item--{{ $incidentState }}">
                        <span class="pso-timeline-item__dot" aria-hidden="true"></span>
                        <div class="pso-timeline-item__content">
                            <header>
                                <div>
                                    <p>{{ $incident->device?->name ?: 'Nieznane urządzenie' }}</p>
                                    <h3>{{ $typeLabel }}</h3>
                                </div>
                                <span class="pso-status-chip pso-status-chip--{{ $incidentState }}">
                                    {{ in_array($incident->status, \App\Models\Incident::ACTIVE_STATUSES, true) ? 'Aktywna' : 'Zakończona' }}
                                </span>
                            </header>

                            <dl>
                                <div>
                                    <dt>Rozpoczęcie</dt>
                                    <dd>{{ $incident->started_at?->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt>Zakończenie</dt>
                                    <dd>{{ $incident->ended_at?->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') ?: 'trwa' }}</dd>
                                </div>
                                <div>
                                    <dt>Czas trwania</dt>
                                    <dd>{{ $duration }}</dd>
                                </div>
                            </dl>

                            @if($incident->summary)
                                <p class="pso-timeline-item__summary">{{ $incident->summary }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="pso-empty-state pso-empty-state--success">
                <span aria-hidden="true">✓</span>
                <h3>Brak zarejestrowanych awarii</h3>
                <p>W historii placówki nie ma incydentów.</p>
            </div>
        @endif
    </section>

    <p class="pso-generated-note">
        Raport wygenerowano
        <time datetime="{{ $report['generated_at']->toIso8601String() }}">
            {{ $report['generated_at']->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') }}
        </time>.
    </p>



    <article class="pso-print-text-report" aria-label="Tekstowy raport dostępności placówki">
        <header class="pso-print-text-header">
            <p class="pso-print-text-brand">Placówka Online</p>
            <h1>Raport dostępności placówki</h1>
            <dl class="pso-print-text-meta">
                <div>
                    <dt>Placówka</dt>
                    <dd>{{ $facility->code }} — {{ $facility->name }}</dd>
                </div>
                @if($facility->address)
                    <div>
                        <dt>Adres</dt>
                        <dd>{{ $facility->address }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Zakres</dt>
                    <dd>24 godziny, 7 dni i 30 dni</dd>
                </div>
                <div>
                    <dt>Wygenerowano</dt>
                    <dd>{{ $report['generated_at']->timezone('Europe/Warsaw')->format('d.m.Y, H:i:s') }}</dd>
                </div>
            </dl>
        </header>

        <section class="pso-print-text-section pso-print-text-summary" aria-labelledby="print-facility-summary-title">
            <div class="pso-print-text-section-heading">
                <p class="pso-print-text-number">01</p>
                <div>
                    <h2 id="print-facility-summary-title">Ocena ogólna</h2>
                    <p>Podsumowanie stanu placówki na podstawie danych z ostatnich 30 dni.</p>
                </div>
            </div>

            <p class="pso-print-text-status pso-print-text-status--{{ $heroState }}">
                <strong>Stan:</strong>
                @if($heroState === 'healthy') STABILNY
                @elseif($heroState === 'warning') WYMAGA UWAGI
                @elseif($heroState === 'critical') AKTYWNA AWARIA
                @else NIEAKTYWNA / BRAK DANYCH
                @endif
            </p>

            <p class="pso-print-text-lead">{{ $heroLabel }}.</p>

            <dl class="pso-print-text-facts pso-print-text-facts--two-columns">
                <div><dt>Dostępność 30 dni</dt><dd>{{ $s30['availability_text'] }}</dd></div>
                <div><dt>Łączny czas przerw</dt><dd>{{ $s30['downtime_text'] }}</dd></div>
                <div><dt>Urządzenia</dt><dd>{{ $s30['device_count'] }}</dd></div>
                <div><dt>Incydenty 30 dni</dt><dd>{{ $s30['incident_count'] }}</dd></div>
                <div><dt>Aktywne awarie</dt><dd>{{ $s30['open_incidents'] }}</dd></div>
            </dl>
        </section>

        <section class="pso-print-text-section" aria-labelledby="print-periods-title">
            <div class="pso-print-text-section-heading">
                <p class="pso-print-text-number">02</p>
                <div>
                    <h2 id="print-periods-title">Dostępność według okresu</h2>
                    <p>Porównanie dostępności, czasu niedostępności i liczby incydentów.</p>
                </div>
            </div>

            <dl class="pso-print-text-periods pso-print-text-periods--large">
                @foreach($report['periods'] as $key => $period)
                    @php
                        $summary = $report['summaries'][$key];
                        $availability = $summary['availability_percent'];
                        $periodState = $availability === null
                            ? 'unknown'
                            : ($availability >= 99.9 ? 'healthy' : ($availability >= 99 ? 'warning' : 'critical'));
                        $periodStateLabel = match($periodState) {
                            'healthy' => 'STABILNIE',
                            'warning' => 'UWAGA',
                            'critical' => 'PROBLEM',
                            default => 'BRAK DANYCH',
                        };
                    @endphp
                    <div>
                        <dt>{{ $period['label'] }}</dt>
                        <dd>
                            <strong>{{ $summary['availability_text'] }}</strong>
                            <span class="pso-print-text-inline-status pso-print-text-inline-status--{{ $periodState }}">{{ $periodStateLabel }}</span><br>
                            Przerwy: {{ $summary['downtime_text'] }} · incydenty: {{ $summary['incident_count'] }} · aktywne: {{ $summary['open_incidents'] }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="pso-print-text-section" aria-labelledby="print-devices-title">
            <div class="pso-print-text-section-heading">
                <p class="pso-print-text-number">03</p>
                <div>
                    <h2 id="print-devices-title">Urządzenia</h2>
                    <p>Stan bieżący oraz dostępność każdego urządzenia.</p>
                </div>
            </div>

            @forelse($report['devices'] as $row)
                @php
                    $device = $row['device'];
                    $d24 = $row['summaries']['24h'];
                    $d7 = $row['summaries']['7d'];
                    $d30 = $row['summaries']['30d'];

                    $devicePrintState = match($device->status) {
                        'online' => 'healthy',
                        'problem' => 'warning',
                        'offline' => 'critical',
                        default => 'unknown',
                    };

                    $devicePrintLabel = match($device->status) {
                        'online' => 'ONLINE',
                        'problem' => 'PROBLEM',
                        'offline' => 'OFFLINE',
                        default => 'BRAK DANYCH',
                    };
                @endphp

                <section class="pso-print-text-entry" aria-labelledby="print-device-{{ $device->id }}">
                    <header class="pso-print-text-entry-header">
                        <div>
                            <h3 id="print-device-{{ $device->id }}">{{ $device->name }}</h3>
                            <p class="pso-print-text-code">UUID: {{ $device->uuid }}</p>
                        </div>
                        <span class="pso-print-text-label pso-print-text-label--{{ $devicePrintState }}">{{ $devicePrintLabel }}</span>
                    </header>

                    <dl class="pso-print-text-periods">
                        <div><dt>24 godziny</dt><dd><strong>{{ $d24['availability_text'] }}</strong> · przerwy: {{ $d24['downtime_text'] }}</dd></div>
                        <div><dt>7 dni</dt><dd><strong>{{ $d7['availability_text'] }}</strong> · przerwy: {{ $d7['downtime_text'] }}</dd></div>
                        <div><dt>30 dni</dt><dd><strong>{{ $d30['availability_text'] }}</strong> · przerwy: {{ $d30['downtime_text'] }}</dd></div>
                    </dl>

                    <p class="pso-print-text-note">
                        Incydenty 30 dni: <strong>{{ $d30['incident_count'] }}</strong> ·
                        aktywne: <strong>{{ $d30['open_incidents'] }}</strong> ·
                        ostatni kontakt: <strong>{{ $device->last_seen_at?->diffForHumans() ?? 'brak danych' }}</strong>
                    </p>
                </section>
            @empty
                <p class="pso-print-text-empty">Brak aktywnych urządzeń.</p>
            @endforelse
        </section>

        <section class="pso-print-text-section" aria-labelledby="print-incidents-title">
            <div class="pso-print-text-section-heading">
                <p class="pso-print-text-number">04</p>
                <div>
                    <h2 id="print-incidents-title">Historia incydentów</h2>
                    <p>Do 30 najnowszych zdarzeń zarejestrowanych w placówce.</p>
                </div>
            </div>

            @if($report['last_incidents']->count())
                <ol class="pso-print-text-incidents">
                    @foreach($report['last_incidents'] as $incident)
                        @php
                            $printDuration = '-';
                            if ($incident->duration_seconds !== null) {
                                $minutes = intdiv($incident->duration_seconds, 60);
                                $seconds = $incident->duration_seconds % 60;
                                $printDuration = $minutes > 0
                                    ? $minutes.' min '.$seconds.' sek.'
                                    : $seconds.' sek.';
                            }
                            if (in_array($incident->status, \App\Models\Incident::ACTIVE_STATUSES, true) && $incident->started_at) {
                                $printDuration = $incident->started_at->diffForHumans(now(), true);
                            }

                            $printTypeLabel = match($incident->type) {
                                'no_communication' => 'Brak komunikacji',
                                'gateway_problem' => 'Problem z routerem lub bramą',
                                'dns_problem' => 'Problem z DNS',
                                'internet_problem' => 'Brak dostępu do Internetu',
                                'monitoring_server_problem' => 'Problem z serwerem monitoringu',
                                'windows_service_problem' => 'Problem z usługą Windows',
                                'smart_problem' => 'Ostrzeżenie SMART dysku',
                                default => $incident->type,
                            };
                        @endphp
                        <li>
                            <header>
                                <h3>{{ $printTypeLabel }}</h3>
                                <span class="pso-print-text-label pso-print-text-label--{{ in_array($incident->status, \App\Models\Incident::ACTIVE_STATUSES, true) ? 'critical' : 'healthy' }}">
                                    {{ in_array($incident->status, \App\Models\Incident::ACTIVE_STATUSES, true) ? 'AKTYWNY' : 'ZAKOŃCZONY' }}
                                </span>
                            </header>
                            <p><strong>Urządzenie:</strong> {{ $incident->device?->name ?: 'Nieznane urządzenie' }}</p>
                            <p>
                                <strong>Rozpoczęcie:</strong> {{ $incident->started_at?->timezone('Europe/Warsaw')->format('d.m.Y, H:i:s') ?: '-' }} ·
                                <strong>Zakończenie:</strong> {{ $incident->ended_at?->timezone('Europe/Warsaw')->format('d.m.Y, H:i:s') ?: 'trwa' }} ·
                                <strong>Czas:</strong> {{ $printDuration }}
                            </p>
                            @if($incident->summary)
                                <p><strong>Opis:</strong> {{ $incident->summary }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="pso-print-text-empty">Brak zarejestrowanych incydentów.</p>
            @endif
        </section>

        <footer class="pso-print-text-footer">
            <span>{{ $facility->code }} — {{ $facility->name }}</span>
            <span>{{ $report['generated_at']->timezone('Europe/Warsaw')->format('d.m.Y, H:i') }}</span>
        </footer>
    </article>

    <footer class="pso-print-footer">
        <span>{{ $facility->code }} — {{ $facility->name }}</span>
        <span>{{ $report['generated_at']->timezone('Europe/Warsaw')->format('d.m.Y, H:i') }}</span>
    </footer>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('panel/saas-platinum-observability.js') }}" defer></script>
@endpush
