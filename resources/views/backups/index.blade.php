@extends('layouts.panel')

@section('title', 'Kopie zapasowe — Placówka Online')
@section('eyebrow', 'Bezpieczeństwo danych')
@section('page_title', 'Kopie zapasowe')
@section('page_lead', 'Prywatne kopie bazy danych z sumą SHA-256 i automatyczną weryfikacją zawartości.')

@push('head')
<link rel="stylesheet" href="{{ asset('panel/saas-platinum-operations.css') }}">
@endpush

@section('page_actions')
<form method="post" action="{{ route('backups.store') }}">@csrf<button class="po-ops-button" type="submit">Utwórz kopię teraz</button></form>
@endsection

@section('content')
<section class="po-ops-stats" aria-label="Stan kopii">
    <article><span>Ostatnia poprawna kopia</span><strong>{{ $latestSuccessful?->completed_at?->diffForHumans() ?? 'Brak' }}</strong></article>
    <article><span>Weryfikacja</span><strong>{{ $latestSuccessful?->verified_at ? 'Potwierdzona' : 'Brak danych' }}</strong></article>
    <article><span>Retencja</span><strong>{{ config('placowka.backup_retention_days', 14) }} dni</strong></article>
    <article><span>Magazyn</span><strong>Prywatny</strong></article>
</section>

<div class="po-ops-notice"><strong>Zakres kopii:</strong> baza danych systemu. Pliki `.env`, tokeny agentów i publiczny instalator nie są umieszczane w archiwum. Kopie znajdują się w `storage/app/backups` poza katalogiem publicznym.</div>

<section class="po-ops-list" aria-label="Historia kopii zapasowych">
@forelse($runs as $run)
    <article class="po-ops-card">
        <div class="po-ops-card-head"><div><p class="po-ops-kicker">Kopia #{{ $run->id }}</p><h2>{{ $run->filename ?: 'Tworzenie lub nieudana próba' }}</h2></div><span class="po-ops-badge po-ops-badge--{{ $run->status === 'success' ? 'low' : 'critical' }}">{{ $run->statusLabel() }}</span></div>
        <dl class="po-ops-meta">
            <div><dt>Rozpoczęcie</dt><dd>{{ $run->started_at?->format('d.m.Y H:i:s') ?? '—' }}</dd></div>
            <div><dt>Rozmiar</dt><dd>{{ $run->sizeLabel() }}</dd></div>
            <div><dt>Zweryfikowana</dt><dd>{{ $run->verified_at?->format('d.m.Y H:i') ?? 'Nie' }}</dd></div>
            <div><dt>SHA-256</dt><dd><code>{{ $run->checksum_sha256 ? substr($run->checksum_sha256, 0, 16).'…' : '—' }}</code></dd></div>
        </dl>
        @if($run->error_message)<p class="po-ops-error">{{ $run->error_message }}</p>@endif
        @if($run->isAvailable())<form method="post" action="{{ route('backups.verify', $run) }}">@csrf<button class="po-ops-button po-ops-button--secondary" type="submit">Sprawdź integralność</button></form>@endif
    </article>
@empty
    <div class="po-ops-empty"><strong>Nie wykonano jeszcze żadnej kopii.</strong><p>Użyj przycisku „Utwórz kopię teraz” albo poczekaj na zadanie harmonogramu.</p></div>
@endforelse
</section>
{{ $runs->links() }}
@endsection
