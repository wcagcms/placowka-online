@extends('layouts.panel')

@section('title', 'Incydent #'.$incident->id.' — Placówka Online')
@section('eyebrow', 'Obsługa incydentu')
@section('page_title', 'Incydent #'.$incident->id)
@section('page_lead', $incident->summary ?: 'Szczegóły zdarzenia technicznego')

@push('head')
<link rel="stylesheet" href="{{ asset('panel/saas-platinum-operations.css') }}">
@endpush

@section('page_actions')
<a class="po-ops-button po-ops-button--secondary" href="{{ route('incidents.index') }}">Wróć do incydentów</a>
@endsection

@section('content')
<section class="po-ops-detail-grid">
    <article class="po-ops-panel">
        <div class="po-ops-card-head"><h2>Stan zdarzenia</h2><div class="po-ops-badges"><span class="po-ops-badge">{{ $incident->statusLabel() }}</span><span class="po-ops-badge po-ops-badge--{{ $incident->priority }}">{{ $incident->priorityLabel() }}</span></div></div>
        <dl class="po-ops-meta po-ops-meta--wide">
            <div><dt>Placówka</dt><dd>{{ $incident->facility?->code }} — {{ $incident->facility?->name }}</dd></div>
            <div><dt>Urządzenie</dt><dd>{{ $incident->device?->name }}</dd></div>
            <div><dt>Typ</dt><dd>{{ str_replace('_', ' ', $incident->type) }}</dd></div>
            <div><dt>Rozpoczęcie</dt><dd>{{ $incident->started_at?->format('d.m.Y H:i:s') ?? '—' }}</dd></div>
            <div><dt>Ostatnie wystąpienie</dt><dd>{{ $incident->last_seen_at?->format('d.m.Y H:i:s') ?? '—' }}</dd></div>
            <div><dt>Przypisany operator</dt><dd>{{ $incident->assignedUser?->name ?? 'Nieprzypisany' }}</dd></div>
        </dl>

        @if($incident->isActive())
        <div class="po-ops-actions" aria-label="Zmiana statusu">
            @if($incident->status === \App\Models\Incident::STATUS_OPEN)
            <form method="post" action="{{ route('incidents.acknowledge', $incident) }}">@csrf<button class="po-ops-button" type="submit">Potwierdź</button></form>
            @endif
            <form method="post" action="{{ route('incidents.in-progress', $incident) }}">@csrf<button class="po-ops-button" type="submit">Oznacz „W trakcie”</button></form>
        </div>
        @endif
    </article>

    <article class="po-ops-panel">
        <h2>Przypisanie i priorytet</h2>
        <form class="po-ops-form" method="post" action="{{ route('incidents.assign', $incident) }}">
            @csrf @method('PATCH')
            <div><label for="assigned_user_id">Operator</label><select id="assigned_user_id" name="assigned_user_id"><option value="">Nieprzypisany</option>@foreach($operators as $operator)<option value="{{ $operator->id }}" @selected((int)$incident->assigned_user_id === (int)$operator->id)>{{ $operator->name }} — {{ $operator->email }}</option>@endforeach</select></div>
            <div><label for="priority">Priorytet</label><select id="priority" name="priority">@foreach(['critical'=>'Krytyczny','high'=>'Wysoki','medium'=>'Średni','low'=>'Niski'] as $value=>$label)<option value="{{ $value }}" @selected($incident->priority === $value)>{{ $label }}</option>@endforeach</select></div>
            <button class="po-ops-button" type="submit">Zapisz przypisanie</button>
        </form>
    </article>
</section>

@if($incident->isActive())
<section class="po-ops-panel">
    <h2>Rozwiązanie incydentu</h2>
    <form class="po-ops-form" method="post" action="{{ route('incidents.resolve', $incident) }}">@csrf
        <div><label for="resolution_note">Co zostało wykonane?</label><textarea id="resolution_note" name="resolution_note" rows="4" required maxlength="4000" placeholder="Opisz diagnozę, wykonane działania i rezultat.">{{ old('resolution_note') }}</textarea></div>
        <button class="po-ops-button po-ops-button--success" type="submit">Oznacz jako rozwiązany</button>
    </form>
</section>
@elseif($incident->status === \App\Models\Incident::STATUS_RESOLVED)
<section class="po-ops-panel"><h2>Rozwiązanie</h2><p>{{ $incident->resolution_note ?: 'Incydent został rozwiązany automatycznie po powrocie prawidłowego pomiaru.' }}</p><form method="post" action="{{ route('incidents.close', $incident) }}">@csrf<button class="po-ops-button" type="submit">Zamknij incydent</button></form></section>
@endif

<section class="po-ops-panel">
    <h2>Notatki operatorów</h2>
    <form class="po-ops-form" method="post" action="{{ route('incidents.comments.store', $incident) }}">@csrf<div><label for="comment-body">Nowa notatka</label><textarea id="comment-body" name="body" rows="3" required maxlength="4000"></textarea></div><button class="po-ops-button" type="submit">Dodaj notatkę</button></form>
    <div class="po-ops-timeline">
    @forelse($incident->comments as $comment)
        <article><strong>{{ $comment->user?->name ?? 'Użytkownik' }}</strong><time datetime="{{ $comment->created_at?->toIso8601String() }}">{{ $comment->created_at?->format('d.m.Y H:i') }}</time><p>{{ $comment->body }}</p></article>
    @empty
        <p>Brak notatek do tego incydentu.</p>
    @endforelse
    </div>
</section>
@endsection
