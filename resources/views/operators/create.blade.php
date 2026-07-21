@extends('layouts.panel')

@section('title', 'Dodaj operatora — Placówka Online')
@section('eyebrow', 'Operatorzy')
@section('page_title', 'Dodaj operatora')
@section('page_lead', 'Utwórz indywidualne konto, ustaw hasło tymczasowe i wybierz placówki dostępne dla operatora.')

@push('head')
<link rel="stylesheet" href="{{ asset('panel/saas-platinum-security.css') }}">
@endpush

@section('page_actions')
<a class="btn secondary" href="{{ route('operators.index') }}">Wróć do operatorów</a>
@endsection

@section('content')
<div class="po-security-editor-layout">
    <form method="post" action="{{ route('operators.store') }}" class="po-security-editor-form">
        @csrf
        @include('operators._form')

        <footer class="po-security-form-actions">
            <div>
                <strong>Gotowe do utworzenia?</strong>
                <span>Po zapisaniu operator będzie musiał zmienić hasło przy pierwszym logowaniu.</span>
            </div>
            <div class="actions">
                <a class="btn secondary" href="{{ route('operators.index') }}">Anuluj</a>
                <button class="btn" type="submit">Utwórz operatora</button>
            </div>
        </footer>
    </form>

    <aside class="po-security-side-panel" aria-labelledby="operator-create-help-title">
        <div class="po-security-side-panel-icon" aria-hidden="true"><x-platinum.icon name="users" :size="28" /></div>
        <p class="po-security-eyebrow">Bezpieczne konto</p>
        <h2 id="operator-create-help-title">Zasady dostępu operatora</h2>
        <ul class="po-security-checklist">
            <li>Operator nie ma dostępu do ustawień administracyjnych.</li>
            <li>Widoczne są tylko przypisane placówki i urządzenia.</li>
            <li>Każda zmiana konta jest zapisywana w Dzienniku bezpieczeństwa.</li>
            <li>Hasło tymczasowe musi zostać zmienione po pierwszym logowaniu.</li>
        </ul>
        <div class="po-security-side-note">
            <strong>Ważne</strong>
            <p>Nie przesyłaj hasła tymczasowego w tej samej wiadomości co adres panelu.</p>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
<script src="{{ asset('panel/saas-platinum-security.js') }}" defer></script>
@endpush
