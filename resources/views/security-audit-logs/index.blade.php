@extends('layouts.panel')

@section('title', 'Dziennik bezpieczeństwa — Placówka Online')
@section('eyebrow', 'Bezpieczeństwo')
@section('page_title', 'Dziennik bezpieczeństwa')
@section('page_lead', 'Kontroluj logowania, blokady i zmiany kont bez zapisywania haseł ani tokenów agentów.')

@push('head')
<link rel="stylesheet" href="{{ asset('panel/saas-platinum-security.css') }}">
@endpush

@section('page_actions')
<a class="btn secondary" href="{{ route('operators.index') }}">Operatorzy</a>
@endsection

@section('content')
<section class="po-security-kpis" aria-label="Podsumowanie zdarzeń bezpieczeństwa">
    <article class="po-security-kpi is-primary">
        <span class="po-security-kpi-icon" aria-hidden="true"><x-platinum.icon name="history" /></span>
        <div>
            <span>Zdarzenia 24 h</span>
            <strong>{{ $stats['last_24_hours'] }}</strong>
            <small>Wszystkie wpisy</small>
        </div>
    </article>

    <article class="po-security-kpi is-success">
        <span class="po-security-kpi-icon" aria-hidden="true"><x-platinum.icon name="health" /></span>
        <div>
            <span>Logowania dzisiaj</span>
            <strong>{{ $stats['successful_logins_today'] }}</strong>
            <small>Pomyślne logowania</small>
        </div>
    </article>

    <article class="po-security-kpi is-danger">
        <span class="po-security-kpi-icon" aria-hidden="true"><x-platinum.icon name="alarm" /></span>
        <div>
            <span>Nieudane próby</span>
            <strong>{{ $stats['failed_logins_7_days'] }}</strong>
            <small>Ostatnie 7 dni</small>
        </div>
    </article>

    <article class="po-security-kpi is-warning">
        <span class="po-security-kpi-icon" aria-hidden="true"><x-platinum.icon name="users" /></span>
        <div>
            <span>Zmiany kont</span>
            <strong>{{ $stats['account_changes_7_days'] }}</strong>
            <small>Ostatnie 7 dni</small>
        </div>
    </article>
</section>

<section class="po-security-toolbar" aria-labelledby="audit-filter-title">
    <div class="po-security-toolbar-heading">
        <div>
            <p class="po-security-eyebrow">Rejestr aktywności</p>
            <h2 id="audit-filter-title">Historia zdarzeń</h2>
            <p>Filtruj wpisy po użytkowniku, adresie IP, rodzaju zdarzenia i przedziale dat.</p>
        </div>
        <span class="po-security-result-count">
            {{ $logs->total() }}
            {{ $logs->total() === 1 ? 'zdarzenie' : 'zdarzeń' }}
        </span>
    </div>

    <form method="get" action="{{ route('security-audit.index') }}" class="po-audit-filters">
        <div class="po-security-field po-audit-search-field">
            <label for="audit-q">Użytkownik, e-mail lub IP</label>
            <div class="po-security-input-icon">
                <span aria-hidden="true"><x-platinum.icon name="history" :size="18" /></span>
                <input id="audit-q"
                       name="q"
                       type="search"
                       value="{{ $search }}"
                       maxlength="120"
                       placeholder="np. operator@domena.pl lub 192.168.1.10">
            </div>
        </div>

        <div class="po-security-field">
            <label for="audit-event">Rodzaj zdarzenia</label>
            <select id="audit-event" name="event">
                <option value="">Wszystkie zdarzenia</option>
                @foreach($eventOptions as $eventValue => $eventLabel)
                    <option value="{{ $eventValue }}" @selected($selectedEvent === $eventValue)>{{ $eventLabel }}</option>
                @endforeach
            </select>
        </div>

        <div class="po-security-field">
            <label for="audit-date-from">Data od</label>
            <input id="audit-date-from" name="date_from" type="date" value="{{ $dateFrom }}">
        </div>

        <div class="po-security-field">
            <label for="audit-date-to">Data do</label>
            <input id="audit-date-to" name="date_to" type="date" value="{{ $dateTo }}">
        </div>

        <div class="po-security-filter-actions po-audit-filter-actions">
            <button class="btn secondary" type="submit">Zastosuj filtry</button>
            @if($search !== '' || $selectedEvent !== '' || $dateFrom !== '' || $dateTo !== '')
                <a class="po-security-clear-link" href="{{ route('security-audit.index') }}">Wyczyść</a>
            @endif
        </div>
    </form>
</section>

<section class="po-audit-timeline" aria-label="Zdarzenia bezpieczeństwa">
    @forelse($logs as $log)
        <article class="po-audit-event is-{{ $log->eventTone() }}">
            <div class="po-audit-event-marker" aria-hidden="true">
                <x-platinum.icon :name="$log->eventIcon()" :size="20" />
            </div>

            <div class="po-audit-event-card">
                <header class="po-audit-event-header">
                    <div>
                        <div class="po-audit-title-row">
                            <h2>{{ $log->eventLabel() }}</h2>
                            <span class="po-security-badge is-{{ $log->eventTone() }}">{{ $log->eventToneLabel() }}</span>
                        </div>
                        <p>{{ $log->eventDescription() }}</p>
                    </div>
                    <time datetime="{{ $log->created_at?->toIso8601String() }}">
                        <strong>{{ $log->created_at?->timezone('Europe/Warsaw')->format('d.m.Y') }}</strong>
                        <span>{{ $log->created_at?->timezone('Europe/Warsaw')->format('H:i:s') }}</span>
                    </time>
                </header>

                <dl class="po-audit-details-grid">
                    <div>
                        <dt>Użytkownik</dt>
                        <dd>{{ $log->actorLabel() }}</dd>
                    </div>
                    <div>
                        <dt>Adres IP</dt>
                        <dd>{{ $log->ip_address ?? 'Brak danych' }}</dd>
                    </div>
                    <div>
                        <dt>Obiekt</dt>
                        <dd>{{ $log->subjectLabel() }}</dd>
                    </div>
                    <div>
                        <dt>Kod zdarzenia</dt>
                        <dd><code>{{ $log->event }}</code></dd>
                    </div>
                </dl>

                @php($contextRows = $log->contextRows())
                @if($contextRows !== [] || $log->user_agent)
                    <details class="po-audit-more">
                        <summary>Szczegóły techniczne</summary>
                        <div class="po-audit-more-content">
                            @if($contextRows !== [])
                                <dl class="po-audit-context-list">
                                    @foreach($contextRows as $row)
                                        <div>
                                            <dt>{{ $row['label'] }}</dt>
                                            <dd>{{ $row['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif

                            @if($log->user_agent)
                                <div class="po-audit-user-agent">
                                    <strong>Przeglądarka / klient</strong>
                                    <p>{{ $log->user_agent }}</p>
                                </div>
                            @endif
                        </div>
                    </details>
                @endif
            </div>
        </article>
    @empty
        <div class="po-security-empty">
            <span class="po-security-empty-icon" aria-hidden="true"><x-platinum.icon name="history" :size="30" /></span>
            <h2>Brak zdarzeń</h2>
            <p>
                @if($search !== '' || $selectedEvent !== '' || $dateFrom !== '' || $dateTo !== '')
                    Nie znaleziono wpisów spełniających zastosowane kryteria.
                @else
                    Dziennik nie zawiera jeszcze zapisanych zdarzeń bezpieczeństwa.
                @endif
            </p>
            @if($search !== '' || $selectedEvent !== '' || $dateFrom !== '' || $dateTo !== '')
                <a class="btn secondary" href="{{ route('security-audit.index') }}">Wyczyść filtry</a>
            @endif
        </div>
    @endforelse
</section>

@if($logs->hasPages())
    <div class="po-security-pagination">
        {{ $logs->links() }}
    </div>
@endif
@endsection
