@extends('layouts.panel')

@section('title', 'Incydenty — Placówka Online')
@section('eyebrow', 'Monitoring i reakcja')
@section('page_title', 'Incydenty')
@section('page_lead', 'Potwierdzaj alarmy, przypisuj operatorów i dokumentuj sposób rozwiązania problemu.')

@push('head')
<link rel="stylesheet" href="{{ asset('panel/saas-platinum-operations.css') }}">
@endpush

@section('content')
<section class="po-ops-stats" aria-label="Podsumowanie incydentów">
    <article><span>Aktywne</span><strong>{{ $counts['active'] }}</strong></article>
    <article><span>Krytyczne</span><strong>{{ $counts['critical'] }}</strong></article>
    <article><span>Przypisane do mnie</span><strong>{{ $counts['assigned_to_me'] }}</strong></article>
    <article><span>Rozwiązane w 30 dni</span><strong>{{ $counts['resolved_30d'] }}</strong></article>
</section>

<form class="po-ops-filter" method="get" action="{{ route('incidents.index') }}" aria-label="Filtrowanie incydentów">
    <div><label for="incident-q">Szukaj</label><input id="incident-q" name="q" value="{{ request('q') }}" placeholder="Placówka, urządzenie lub opis"></div>
    <div><label for="incident-status">Status</label><select id="incident-status" name="status">
        <option value="active" @selected(request('status', 'active') === 'active')>Aktywne</option>
        <option value="open" @selected(request('status') === 'open')>Nowe</option>
        <option value="acknowledged" @selected(request('status') === 'acknowledged')>Potwierdzone</option>
        <option value="in_progress" @selected(request('status') === 'in_progress')>W trakcie</option>
        <option value="resolved" @selected(request('status') === 'resolved')>Rozwiązane</option>
        <option value="closed" @selected(request('status') === 'closed')>Zamknięte</option>
    </select></div>
    <div><label for="incident-priority">Priorytet</label><select id="incident-priority" name="priority">
        <option value="">Wszystkie</option>
        @foreach(['critical' => 'Krytyczny', 'high' => 'Wysoki', 'medium' => 'Średni', 'low' => 'Niski'] as $value => $label)
            <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>
        @endforeach
    </select></div>
    <div><label for="incident-type">Typ</label><select id="incident-type" name="type"><option value="">Wszystkie</option>@foreach($types as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ str_replace('_', ' ', $type) }}</option>@endforeach</select></div>
    <button class="po-ops-button" type="submit">Zastosuj filtry</button>
</form>

<section class="po-ops-list" aria-label="Lista incydentów">
@forelse($incidents as $incident)
    <article class="po-ops-card po-ops-card--{{ $incident->priority }}">
        <div class="po-ops-card-head">
            <div>
                <p class="po-ops-kicker">{{ $incident->facility?->code }} · {{ $incident->device?->name }}</p>
                <h2><a href="{{ route('incidents.show', $incident) }}">{{ $incident->summary ?: 'Incydent techniczny' }}</a></h2>
            </div>
            <div class="po-ops-badges">
                <span class="po-ops-badge">{{ $incident->statusLabel() }}</span>
                <span class="po-ops-badge po-ops-badge--{{ $incident->priority }}">{{ $incident->priorityLabel() }}</span>
            </div>
        </div>
        <dl class="po-ops-meta">
            <div><dt>Typ</dt><dd>{{ str_replace('_', ' ', $incident->type) }}</dd></div>
            <div><dt>Rozpoczęcie</dt><dd>{{ $incident->started_at?->format('d.m.Y H:i') ?? '—' }}</dd></div>
            <div><dt>Operator</dt><dd>{{ $incident->assignedUser?->name ?? 'Nieprzypisany' }}</dd></div>
            <div><dt>Wystąpienia</dt><dd>{{ $incident->occurrence_count ?? 1 }}</dd></div>
        </dl>
    </article>
@empty
    <div class="po-ops-empty"><strong>Brak incydentów dla wybranych filtrów.</strong><p>Aktywne problemy pojawią się tutaj automatycznie.</p></div>
@endforelse
</section>

{{ $incidents->links() }}
@endsection
