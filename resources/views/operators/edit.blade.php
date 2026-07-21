@extends('layouts.panel')

@section('title', 'Edytuj operatora — Placówka Online')
@section('eyebrow', 'Operatorzy')
@section('page_title', 'Edytuj operatora')
@section('page_lead', 'Zmień dane konta, hasło tymczasowe oraz zakres placówek dostępnych dla operatora.')

@push('head')
<link rel="stylesheet" href="{{ asset('panel/saas-platinum-security.css') }}">
@endpush

@section('page_actions')
<a class="btn secondary" href="{{ route('security-audit.index', ['q' => $operator->email]) }}">Zobacz zdarzenia konta</a>
<a class="btn secondary" href="{{ route('operators.index') }}">Wróć do operatorów</a>
@endsection

@section('content')
<div class="po-security-editor-layout">
    <form method="post" action="{{ route('operators.update', $operator) }}" class="po-security-editor-form">
        @csrf
        @method('PUT')
        @include('operators._form')

        <footer class="po-security-form-actions">
            <div>
                <strong>Zapis zmian</strong>
                <span>Zmiana hasła albo dezaktywacja konta unieważni aktywne sesje operatora.</span>
            </div>
            <div class="actions">
                <a class="btn secondary" href="{{ route('operators.index') }}">Anuluj</a>
                <button class="btn" type="submit">Zapisz zmiany</button>
            </div>
        </footer>
    </form>

    <aside class="po-security-side-panel" aria-labelledby="operator-summary-title">
        <div class="po-operator-summary-head">
            <span class="po-operator-avatar is-large" aria-hidden="true">{{ $operator->initials() }}</span>
            <div>
                <p class="po-security-eyebrow">Konto operatora</p>
                <h2 id="operator-summary-title">{{ $operator->name }}</h2>
                <span class="po-security-badge {{ $operator->is_active ? 'is-success' : 'is-danger' }}">
                    {{ $operator->is_active ? 'AKTYWNY' : 'NIEAKTYWNY' }}
                </span>
            </div>
        </div>

        <dl class="po-security-summary-list">
            <div>
                <dt>Adres e-mail</dt>
                <dd>{{ $operator->email }}</dd>
            </div>
            <div>
                <dt>Przypisane placówki</dt>
                <dd>{{ $operator->facilities->count() }}</dd>
            </div>
            <div>
                <dt>Ostatnie logowanie</dt>
                <dd>{{ $operator->last_login_at?->timezone('Europe/Warsaw')->format('d.m.Y, H:i') ?? 'Nigdy' }}</dd>
            </div>
            <div>
                <dt>Ostatni adres IP</dt>
                <dd>{{ $operator->last_login_ip ?? 'Brak danych' }}</dd>
            </div>
            <div>
                <dt>Zmiana hasła</dt>
                <dd>{{ $operator->must_change_password ? 'Wymagana' : 'Wykonana' }}</dd>
            </div>
        </dl>

        <div class="po-security-side-note {{ $operator->must_change_password ? 'is-warning' : 'is-success' }}">
            <strong>{{ $operator->must_change_password ? 'Operator musi zmienić hasło' : 'Hasło zostało ustawione przez operatora' }}</strong>
            <p>{{ $operator->must_change_password ? 'Po zalogowaniu konto zostanie przekierowane do formularza zmiany hasła.' : 'Ponowne wymuszenie nastąpi po ustawieniu nowego hasła tymczasowego.' }}</p>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
<script src="{{ asset('panel/saas-platinum-security.js') }}" defer></script>
@endpush
