@extends('layouts.panel')

@section('title', 'Raporty — Placówka Online')
@section('eyebrow', 'Raporty dostępności')
@section('page_title', 'Raporty')
@section('page_lead', 'Porównanie dostępności Internetu, czasu niedostępności i liczby incydentów dla wszystkich placówek.')

@push('head')
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-observability.css') }}">
@endpush

@section('page_actions')
    <button
        class="btn secondary"
        type="button"
        data-print-report
        data-print-title="Raport zbiorczy dostępności — Placówka Online"
    >
        Drukuj raport
    </button>
    <a class="btn secondary" href="{{ route('monitoring-center.index') }}">Centrum monitoringu</a>
    <a class="btn" href="{{ route('reports.index') }}">Odśwież raport</a>
@endsection

@section('content')
@php
    $rows = $report['facilities'];
    $facilityCount = $rows->count();
    $deviceCount = $rows->sum(fn ($row) => $row['summaries']['30d']['device_count']);
    $openIncidentCount = $rows->sum(fn ($row) => $row['summaries']['30d']['open_incidents']);
    $incidentCount30d = $rows->sum(fn ($row) => $row['summaries']['30d']['incident_count']);

    $averageFor = function (string $period) use ($rows): ?float {
        $values = $rows
            ->map(fn ($row) => $row['summaries'][$period]['availability_percent'])
            ->filter(fn ($value) => $value !== null);

        return $values->isEmpty() ? null : round((float) $values->average(), 2);
    };

    $average24 = $averageFor('24h');
    $average7 = $averageFor('7d');
    $average30 = $averageFor('30d');

    $average30State = $average30 === null
        ? 'unknown'
        : ($average30 >= 99.9 ? 'healthy' : ($average30 >= 99 ? 'warning' : 'critical'));
@endphp

<div class="pso-page pso-print-document" data-report-directory data-print-report-root>
    <header class="pso-print-header">
        <div class="pso-print-brand">
            <span class="pso-print-brand__mark" aria-hidden="true">PO</span>
            <div>
                <p>Placówka Online</p>
                <h2>Raport zbiorczy dostępności</h2>
            </div>
        </div>

        <dl class="pso-print-meta">
            <div>
                <dt>Zakres raportu</dt>
                <dd>Wszystkie placówki</dd>
            </div>
            <div>
                <dt>Wygenerowano</dt>
                <dd>{{ $report['generated_at']->timezone('Europe/Warsaw')->format('d.m.Y, H:i:s') }}</dd>
            </div>
        </dl>
    </header>
    <section class="pso-report-hero pso-report-hero--{{ $average30State }}" aria-labelledby="pso-report-overview-title">
        <div class="pso-report-hero__main">
            <span class="pso-icon-box" aria-hidden="true">
                <x-platinum.icon name="history" :size="26" />
            </span>
            <div>
                <p class="pso-kicker">Dostępność infrastruktury</p>
                <h2 id="pso-report-overview-title">Raport zbiorczy placówek</h2>
                <p>
                    Dane są obliczane na podstawie zarejestrowanych incydentów.
                    Wartość zbiorcza jest średnią dostępności placówek posiadających dane.
                </p>
            </div>
        </div>

        <div class="pso-report-hero__score">
            <span>Średnia dostępność 30 dni</span>
            <strong>{{ $average30 === null ? 'Brak danych' : number_format($average30, 2, ',', ' ').'%' }}</strong>
            <small>
                Wygenerowano {{ $report['generated_at']->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') }}
            </small>
        </div>
    </section>

    <section class="pso-stat-grid pso-stat-grid--reports" aria-label="Podsumowanie raportów">
        @foreach([
            ['label' => 'Placówki', 'value' => $facilityCount, 'note' => 'w raporcie', 'state' => 'neutral', 'icon' => 'home'],
            ['label' => 'Urządzenia', 'value' => $deviceCount, 'note' => 'monitorowane w 30 dni', 'state' => 'neutral', 'icon' => 'device'],
            ['label' => 'Średnia 24h', 'value' => $average24 === null ? '—' : number_format($average24, 2, ',', ' ').'%', 'note' => 'średnia placówek', 'state' => $average24 === null ? 'neutral' : ($average24 < 99 ? 'warning' : 'healthy'), 'icon' => 'response'],
            ['label' => 'Średnia 7 dni', 'value' => $average7 === null ? '—' : number_format($average7, 2, ',', ' ').'%', 'note' => 'średnia placówek', 'state' => $average7 === null ? 'neutral' : ($average7 < 99 ? 'warning' : 'healthy'), 'icon' => 'history'],
            ['label' => 'Incydenty 30 dni', 'value' => $incidentCount30d, 'note' => 'wszystkie awarie', 'state' => $incidentCount30d > 0 ? 'warning' : 'healthy', 'icon' => 'alarm'],
            ['label' => 'Aktywne awarie', 'value' => $openIncidentCount, 'note' => 'w chwili generowania', 'state' => $openIncidentCount > 0 ? 'critical' : 'healthy', 'icon' => 'bell'],
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

    <section class="pso-control-panel" aria-labelledby="pso-report-filters-title">
        <div class="pso-control-panel__intro">
            <span class="pso-icon-box" aria-hidden="true">
                <x-platinum.icon name="settings" :size="22" />
            </span>
            <div>
                <p class="pso-kicker">Katalog raportów</p>
                <h2 id="pso-report-filters-title">Znajdź placówkę</h2>
                <p>Filtry nie zmieniają raportu — pomagają tylko szybciej odnaleźć właściwą placówkę.</p>
            </div>
        </div>

        <div class="pso-filter-grid pso-filter-grid--compact">
            <div class="pso-field">
                <label for="report-search">Nazwa lub kod placówki</label>
                <input id="report-search" type="search" placeholder="np. PP10" autocomplete="off" data-report-search>
            </div>

            <div class="pso-field">
                <label for="report-state-filter">Stan raportu</label>
                <select id="report-state-filter" data-report-state-filter>
                    <option value="all">Wszystkie placówki</option>
                    <option value="healthy">Stabilne</option>
                    <option value="warning">Z incydentami</option>
                    <option value="critical">Z aktywną awarią</option>
                    <option value="inactive">Nieaktywne</option>
                </select>
            </div>
        </div>

        <p class="pso-filter-result" data-report-result aria-live="polite"></p>
    </section>

    <section class="pso-section" aria-labelledby="pso-reports-facilities-title">
        <header class="pso-section-heading">
            <div>
                <p class="pso-kicker">Wszystkie placówki</p>
                <h2 id="pso-reports-facilities-title">Dostępność według okresu</h2>
                <p>Każda karta pokazuje wynik 24h, 7 dni i 30 dni oraz czas przerw i liczbę incydentów.</p>
            </div>
        </header>

        @if($rows->count())
            <div class="pso-report-card-grid" data-report-card-grid>
                @foreach($rows as $row)
                    @php
                        $facility = $row['facility'];
                        $s24 = $row['summaries']['24h'];
                        $s7 = $row['summaries']['7d'];
                        $s30 = $row['summaries']['30d'];

                        $cardState = ! $facility->is_active
                            ? 'inactive'
                            : ($s30['open_incidents'] > 0
                                ? 'critical'
                                : ($s30['incident_count'] > 0 || ($s30['availability_percent'] !== null && $s30['availability_percent'] < 99.9)
                                    ? 'warning'
                                    : 'healthy'));

                        $cardStateLabel = match($cardState) {
                            'critical' => 'Aktywna awaria',
                            'warning' => 'Wymaga analizy',
                            'inactive' => 'Nieaktywna',
                            default => 'Stabilna',
                        };

                        $searchText = mb_strtolower(trim($facility->code.' '.$facility->name.' '.($facility->address ?? '')));
                    @endphp

                    <article class="pso-report-card pso-report-card--{{ $cardState }}"
                             data-report-card
                             data-state="{{ $cardState }}"
                             data-search="{{ $searchText }}">
                        <header class="pso-report-card__header">
                            <div>
                                <p>{{ $facility->code }}</p>
                                <h3>{{ $facility->name }}</h3>
                            </div>
                            <span class="pso-status-chip pso-status-chip--{{ $cardState }}">{{ $cardStateLabel }}</span>
                        </header>

                        <div class="pso-period-grid">
                            @foreach([
                                ['label' => '24 godziny', 'summary' => $s24],
                                ['label' => '7 dni', 'summary' => $s7],
                                ['label' => '30 dni', 'summary' => $s30],
                            ] as $period)
                                @php
                                    $availability = $period['summary']['availability_percent'];
                                    $periodState = $availability === null
                                        ? 'unknown'
                                        : ($availability >= 99.9 ? 'healthy' : ($availability >= 99 ? 'warning' : 'critical'));
                                @endphp
                                <div class="pso-period-card pso-period-card--{{ $periodState }}">
                                    <span>{{ $period['label'] }}</span>
                                    <strong>{{ $period['summary']['availability_text'] }}</strong>
                                    <progress
                                        max="100"
                                        value="{{ $availability ?? 0 }}"
                                        aria-label="Dostępność za {{ $period['label'] }}: {{ $period['summary']['availability_text'] }}"
                                    >
                                        {{ $period['summary']['availability_text'] }}
                                    </progress>
                                    <small>Przerwy: {{ $period['summary']['downtime_text'] }}</small>
                                </div>
                            @endforeach
                        </div>

                        <dl class="pso-report-card__facts">
                            <div>
                                <dt>Urządzenia</dt>
                                <dd>{{ $s30['device_count'] }}</dd>
                            </div>
                            <div>
                                <dt>Incydenty 30 dni</dt>
                                <dd>{{ $s30['incident_count'] }}</dd>
                            </div>
                            <div>
                                <dt>Aktywne awarie</dt>
                                <dd>{{ $s30['open_incidents'] }}</dd>
                            </div>
                        </dl>

                        <div class="pso-card-actions">
                            <a class="btn small" href="{{ route('reports.facility', $facility) }}">Pełny raport</a>
                            <a class="btn small secondary" href="{{ route('facilities.show', $facility) }}">Placówka</a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="pso-empty-state pso-empty-state--filter" data-report-empty hidden>
                <span aria-hidden="true">⌕</span>
                <h3>Brak pasujących raportów</h3>
                <p>Zmień frazę wyszukiwania albo filtr stanu.</p>
            </div>
        @else
            <div class="pso-empty-state">
                <span aria-hidden="true">○</span>
                <h3>Brak danych raportowych</h3>
                <p>Raport pojawi się po dodaniu placówki i urządzeń.</p>
                @if(auth()->user()?->isAdmin())
                    <a class="btn" href="{{ route('facilities.create') }}">Dodaj placówkę</a>
                @endif
            </div>
        @endif
    </section>



    <article class="pso-print-text-report" aria-label="Tekstowy raport zbiorczy dostępności">
        <header class="pso-print-text-header">
            <p class="pso-print-text-brand">Placówka Online</p>
            <h1>Raport zbiorczy dostępności</h1>
            <dl class="pso-print-text-meta">
                <div>
                    <dt>Zakres</dt>
                    <dd>Wszystkie placówki</dd>
                </div>
                <div>
                    <dt>Okresy</dt>
                    <dd>24 godziny, 7 dni i 30 dni</dd>
                </div>
                <div>
                    <dt>Wygenerowano</dt>
                    <dd>{{ $report['generated_at']->timezone('Europe/Warsaw')->format('d.m.Y, H:i:s') }}</dd>
                </div>
            </dl>
        </header>

        <section class="pso-print-text-section pso-print-text-summary" aria-labelledby="print-summary-title">
            <div class="pso-print-text-section-heading">
                <p class="pso-print-text-number">01</p>
                <div>
                    <h2 id="print-summary-title">Podsumowanie</h2>
                    <p>Najważniejsze informacje o dostępności monitorowanej infrastruktury.</p>
                </div>
            </div>

            <p class="pso-print-text-status pso-print-text-status--{{ $average30State }}">
                <strong>Stan ogólny:</strong>
                @if($average30State === 'healthy') STABILNY
                @elseif($average30State === 'warning') WYMAGA UWAGI
                @elseif($average30State === 'critical') KRYTYCZNY
                @else BRAK PEŁNYCH DANYCH
                @endif
            </p>

            <dl class="pso-print-text-facts pso-print-text-facts--two-columns">
                <div><dt>Placówki</dt><dd>{{ $facilityCount }}</dd></div>
                <div><dt>Urządzenia</dt><dd>{{ $deviceCount }}</dd></div>
                <div><dt>Średnia dostępność 24h</dt><dd>{{ $average24 === null ? 'Brak danych' : number_format($average24, 2, ',', ' ').'%' }}</dd></div>
                <div><dt>Średnia dostępność 7 dni</dt><dd>{{ $average7 === null ? 'Brak danych' : number_format($average7, 2, ',', ' ').'%' }}</dd></div>
                <div><dt>Średnia dostępność 30 dni</dt><dd>{{ $average30 === null ? 'Brak danych' : number_format($average30, 2, ',', ' ').'%' }}</dd></div>
                <div><dt>Incydenty w 30 dniach</dt><dd>{{ $incidentCount30d }}</dd></div>
                <div><dt>Aktywne awarie</dt><dd>{{ $openIncidentCount }}</dd></div>
            </dl>
        </section>

        <section class="pso-print-text-section" aria-labelledby="print-facilities-title">
            <div class="pso-print-text-section-heading">
                <p class="pso-print-text-number">02</p>
                <div>
                    <h2 id="print-facilities-title">Dostępność placówek</h2>
                    <p>Zestawienie wyników za 24 godziny, 7 dni i 30 dni.</p>
                </div>
            </div>

            @forelse($rows as $row)
                @php
                    $facility = $row['facility'];
                    $s24 = $row['summaries']['24h'];
                    $s7 = $row['summaries']['7d'];
                    $s30 = $row['summaries']['30d'];

                    $printState = ! $facility->is_active
                        ? 'inactive'
                        : ($s30['open_incidents'] > 0
                            ? 'critical'
                            : ($s30['incident_count'] > 0 || ($s30['availability_percent'] !== null && $s30['availability_percent'] < 99.9)
                                ? 'warning'
                                : 'healthy'));

                    $printStateLabel = match($printState) {
                        'critical' => 'AWARIA',
                        'warning' => 'UWAGA',
                        'inactive' => 'NIEAKTYWNA',
                        default => 'STABILNA',
                    };
                @endphp

                <section class="pso-print-text-entry" aria-labelledby="print-facility-{{ $facility->id }}">
                    <header class="pso-print-text-entry-header">
                        <div>
                            <p class="pso-print-text-code">{{ $facility->code }}</p>
                            <h3 id="print-facility-{{ $facility->id }}">{{ $facility->name }}</h3>
                            @if($facility->address)
                                <p>{{ $facility->address }}</p>
                            @endif
                        </div>
                        <span class="pso-print-text-label pso-print-text-label--{{ $printState }}">{{ $printStateLabel }}</span>
                    </header>

                    <dl class="pso-print-text-periods">
                        <div>
                            <dt>24 godziny</dt>
                            <dd><strong>{{ $s24['availability_text'] }}</strong> · przerwy: {{ $s24['downtime_text'] }} · incydenty: {{ $s24['incident_count'] }}</dd>
                        </div>
                        <div>
                            <dt>7 dni</dt>
                            <dd><strong>{{ $s7['availability_text'] }}</strong> · przerwy: {{ $s7['downtime_text'] }} · incydenty: {{ $s7['incident_count'] }}</dd>
                        </div>
                        <div>
                            <dt>30 dni</dt>
                            <dd><strong>{{ $s30['availability_text'] }}</strong> · przerwy: {{ $s30['downtime_text'] }} · incydenty: {{ $s30['incident_count'] }}</dd>
                        </div>
                    </dl>

                    <p class="pso-print-text-note">
                        Urządzenia: <strong>{{ $s30['device_count'] }}</strong> ·
                        aktywne awarie: <strong>{{ $s30['open_incidents'] }}</strong>
                    </p>
                </section>
            @empty
                <p class="pso-print-text-empty">Brak danych raportowych.</p>
            @endforelse
        </section>

        <footer class="pso-print-text-footer">
            <span>Placówka Online — raport zbiorczy dostępności</span>
            <span>{{ $report['generated_at']->timezone('Europe/Warsaw')->format('d.m.Y, H:i') }}</span>
        </footer>
    </article>

    <footer class="pso-print-footer">
        <span>Placówka Online — raport zbiorczy dostępności</span>
        <span>{{ $report['generated_at']->timezone('Europe/Warsaw')->format('d.m.Y, H:i') }}</span>
    </footer>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('panel/saas-platinum-observability.js') }}" defer></script>
@endpush
