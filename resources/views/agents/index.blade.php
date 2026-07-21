@extends('layouts.panel')

@section('title', 'Stan agentów — Placówka Online')
@section('eyebrow', 'Monitoring / Agenci')
@section('page_title', 'Stan agentów')
@section('page_lead', 'Wersje, kompletność pomiarów i samokontrola agentów we wszystkich dostępnych placówkach.')

@push('head')
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-agents.css') }}">
@endpush

@section('page_actions')
    <a class="btn secondary" href="{{ route('monitoring-center.index') }}">Centrum monitoringu</a>
    <a class="btn" href="{{ route('agents.index') }}">Odśwież dane</a>
@endsection

@section('content')
<section class="po-agent-overview" aria-labelledby="po-agent-overview-title">
    <div class="po-agent-overview__intro">
        <p class="po-agent-kicker">SaaS Platinum · kontrola agentów</p>
        <h2 id="po-agent-overview-title">Gotowość warstwy monitorującej</h2>
        <p>System odróżnia brak komunikacji, nieaktualną wersję, niepełny pomiar oraz problem z samym agentem.</p>
        <div class="po-agent-overview__latest">
            <span>Wersja produkcyjna</span>
            <strong>{{ $latestVersion }}</strong>
        </div>
    </div>

    <div class="po-agent-scorecards" aria-label="Podsumowanie agentów">
        <article class="po-agent-scorecard po-agent-scorecard--neutral">
            <span>Wszystkie</span>
            <strong>{{ $stats['all'] }}</strong>
            <small>aktywnych i nieaktywnych urządzeń</small>
        </article>
        <article class="po-agent-scorecard po-agent-scorecard--healthy">
            <span>Prawidłowe</span>
            <strong>{{ $stats['healthy'] }}</strong>
            <small>aktualna wersja i komplet pomiarów</small>
        </article>
        <article class="po-agent-scorecard po-agent-scorecard--warning">
            <span>Wymagają uwagi</span>
            <strong>{{ $stats['warning'] }}</strong>
            <small>niepełne dane lub aktualizacja</small>
        </article>
        <article class="po-agent-scorecard po-agent-scorecard--critical">
            <span>Problemy</span>
            <strong>{{ $stats['critical'] }}</strong>
            <small>brak heartbeat lub błąd samokontroli</small>
        </article>
        <article class="po-agent-scorecard po-agent-scorecard--info">
            <span>Do aktualizacji</span>
            <strong>{{ $stats['outdated'] }}</strong>
            <small>starsza wersja agenta</small>
        </article>
        <article class="po-agent-scorecard po-agent-scorecard--muted">
            <span>Bez samokontroli</span>
            <strong>{{ $stats['without_self_check'] }}</strong>
            <small>agent starszy niż 1.8.0</small>
        </article>
    </div>
</section>

<section class="po-agent-toolbar" aria-labelledby="po-agent-filter-title">
    <div>
        <p class="po-agent-kicker">Filtry</p>
        <h2 id="po-agent-filter-title">Znajdź urządzenie</h2>
    </div>
    <div class="po-agent-toolbar__fields">
        <label>
            <span>Wyszukaj</span>
            <input type="search" data-agent-search placeholder="Placówka, urządzenie lub wersja" autocomplete="off">
        </label>
        <label>
            <span>Stan</span>
            <select data-agent-status-filter>
                <option value="all">Wszystkie stany</option>
                <option value="healthy">Prawidłowe</option>
                <option value="warning">Wymagają uwagi</option>
                <option value="critical">Problemy</option>
                <option value="inactive">Nieaktywne</option>
            </select>
        </label>
        <label>
            <span>Wersja</span>
            <select data-agent-version-filter>
                <option value="all">Wszystkie wersje</option>
                <option value="current">Aktualne</option>
                <option value="outdated">Do aktualizacji</option>
                <option value="missing">Brak danych</option>
                <option value="unknown">Nierozpoznane</option>
                <option value="ahead">Nowsze niż produkcyjna</option>
            </select>
        </label>
    </div>
    <p class="po-agent-toolbar__result" role="status" aria-live="polite">
        Widoczne urządzenia: <strong data-agent-visible-count>{{ $items->count() }}</strong>
    </p>
</section>

<section class="po-agent-list" aria-label="Lista agentów" data-agent-list>
    @forelse($items as $item)
        @php
            $device = $item['device'];
            $agent = $item['diagnostics'];
            $searchText = mb_strtolower(implode(' ', [
                $device->facility?->code,
                $device->facility?->name,
                $device->name,
                data_get($agent, 'version.installed'),
                data_get($agent, 'status_label'),
            ]));
        @endphp
        <article class="po-agent-card po-agent-card--{{ $agent['status'] }}"
                 data-agent-card
                 data-agent-status="{{ $agent['status'] }}"
                 data-agent-version="{{ data_get($agent, 'version.status', 'missing') }}"
                 data-agent-search-text="{{ $searchText }}">
            <header class="po-agent-card__header">
                <div class="po-agent-card__identity">
                    <span class="po-agent-card__signal" aria-hidden="true"></span>
                    <div>
                        <p>{{ $device->facility?->code }} · {{ $device->facility?->name }}</p>
                        <h2>{{ $device->name }}</h2>
                    </div>
                </div>
                <span class="po-agent-state po-agent-state--{{ $agent['status'] }}">
                    {{ $agent['status_label'] }}
                </span>
            </header>

            <p class="po-agent-card__message">{{ $agent['status_message'] }}</p>

            <div class="po-agent-card__metrics">
                <div>
                    <span>Wersja</span>
                    <strong>{{ data_get($agent, 'version.installed') ?: 'Brak danych' }}</strong>
                    <small class="po-agent-version po-agent-version--{{ data_get($agent, 'version.status') }}">
                        {{ data_get($agent, 'version.label') }}
                    </small>
                </div>
                <div>
                    <span>Kompletność pomiaru</span>
                    <strong>{{ $agent['telemetry_completeness_label'] }}</strong>
                    @if($agent['telemetry_completeness_percent'] !== null)
                        <progress max="100" value="{{ $agent['telemetry_completeness_percent'] }}"
                                  aria-label="Kompletność pomiaru: {{ $agent['telemetry_completeness_percent'] }} procent">
                            {{ $agent['telemetry_completeness_percent'] }}%
                        </progress>
                    @else
                        <small>Wymagana wersja agenta 1.8.0</small>
                    @endif
                </div>
                <div>
                    <span>Ostatni cykl</span>
                    <strong>{{ $agent['cycle_duration'] }}</strong>
                    <small>{{ $agent['profile'] }}</small>
                </div>
                <div>
                    <span>Ostatni kontakt</span>
                    <strong>{{ $device->last_seen_at?->diffForHumans() ?? 'Brak danych' }}</strong>
                    <small>{{ $agent['freshness_label'] }}</small>
                </div>
            </div>

            <div class="po-agent-card__checks" aria-label="Kontrole wewnętrzne agenta">
                @foreach($agent['checks'] as $check)
                    <span class="po-agent-check po-agent-check--{{ $check['state'] }}">
                        <span aria-hidden="true"></span>
                        {{ $check['label'] }}: {{ $check['text'] }}
                    </span>
                @endforeach
            </div>

            @if($agent['missing_modules'] !== [])
                <div class="po-agent-card__notice">
                    <strong>Brakujące moduły:</strong>
                    {{ implode(', ', $agent['missing_modules']) }}
                </div>
            @endif

            @if($agent['last_failure_reason'])
                <div class="po-agent-card__notice po-agent-card__notice--danger">
                    <strong>Ostatni błąd wysyłki:</strong>
                    {{ $agent['last_failure_reason'] }}
                </div>
            @endif

            <footer class="po-agent-card__footer">
                <dl>
                    <div><dt>Tryb</dt><dd>{{ $agent['run_mode'] }}</dd></div>
                    <div><dt>Różnica czasu</dt><dd>{{ $agent['clock_skew_label'] }}</dd></div>
                    <div><dt>Samokontrola</dt><dd>{{ $agent['updated_at'] }}</dd></div>
                </dl>
                <a class="btn secondary" href="{{ route('devices.heartbeats', ['device' => $device->id]) }}">
                    Szczegóły urządzenia
                </a>
            </footer>
        </article>
    @empty
        <div class="po-agent-empty">
            <h2>Brak urządzeń</h2>
            <p>Nie znaleziono urządzeń dostępnych dla tego konta.</p>
        </div>
    @endforelse

    <div class="po-agent-empty" data-agent-empty-filter hidden>
        <h2>Brak wyników</h2>
        <p>Zmień wyszukiwanie lub wybrane filtry.</p>
    </div>
</section>
@endsection

@push('scripts')
    <script src="{{ asset('panel/saas-platinum-agents.js') }}" defer></script>
@endpush
