@extends('layouts.panel')

@section('title', 'Ustawienia systemu — Placówka Online')
@section('eyebrow', 'Administracja')
@section('page_title', 'Centrum ustawień')
@section('page_lead', 'Zarządzaj nazwą panelu, powiadomieniami i parametrami monitoringu bez edytowania pliku .env.')

@section('page_actions')
    <a class="btn secondary" href="{{ route('dashboard') }}">Wróć do panelu</a>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-settings.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('panel/saas-platinum-settings.js') }}" defer></script>
@endpush

@section('content')
    @include('partials.settings-navigation')

    @php
        $systemName = old('panel_system_name', $settings['panel_system_name'] ?? 'Placówka Online');
        $adminEmail = old('admin_email', $settings['admin_email'] ?? '');
        $emailAlertsEnabled = (bool) old('email_alerts_enabled', $settings['email_alerts_enabled'] ?? true);
        $missingMinutes = (int) old('default_missing_after_minutes', $settings['default_missing_after_minutes'] ?? 5);
        $alertMinutes = (int) old('default_alert_after_minutes', $settings['default_alert_after_minutes'] ?? 10);
        $intervalSeconds = (int) old('default_check_interval_seconds', $settings['default_check_interval_seconds'] ?? 60);
        $retentionDays = (int) old('heartbeat_retention_days', $settings['heartbeat_retention_days'] ?? 60);
    @endphp

    <section class="pos-summary-grid" aria-label="Najważniejsze ustawienia systemu">
        <article class="pos-summary-card pos-summary-card--primary">
            <span class="pos-summary-card__icon" aria-hidden="true">
                <x-platinum.icon name="bell" :size="21" />
            </span>
            <div>
                <span class="pos-summary-card__label">Powiadomienia e-mail</span>
                <strong data-alert-summary>{{ $emailAlertsEnabled ? 'Włączone' : 'Wyłączone' }}</strong>
                <small>{{ $adminEmail !== '' ? $adminEmail : 'Brak adresu administratora' }}</small>
            </div>
        </article>

        <article class="pos-summary-card">
            <span class="pos-summary-card__icon" aria-hidden="true">
                <x-platinum.icon name="alarm" :size="21" />
            </span>
            <div>
                <span class="pos-summary-card__label">Brak komunikacji po</span>
                <strong><span data-missing-summary>{{ $missingMinutes }}</span> min</strong>
                <small>Po tym czasie urządzenie zmieni stan</small>
            </div>
        </article>

        <article class="pos-summary-card">
            <span class="pos-summary-card__icon" aria-hidden="true">
                <x-platinum.icon name="response" :size="21" />
            </span>
            <div>
                <span class="pos-summary-card__label">Interwał agenta</span>
                <strong><span data-interval-summary>{{ $intervalSeconds }}</span> s</strong>
                <small>Częstotliwość wysyłania heartbeatów</small>
            </div>
        </article>

        <article class="pos-summary-card">
            <span class="pos-summary-card__icon" aria-hidden="true">
                <x-platinum.icon name="history" :size="21" />
            </span>
            <div>
                <span class="pos-summary-card__label">Retencja heartbeatów</span>
                <strong><span data-retention-summary>{{ $retentionDays }}</span> dni</strong>
                <small>Okres przechowywania danych historycznych</small>
            </div>
        </article>
    </section>

    <div class="pos-settings-layout">
        <form method="POST"
              action="{{ route('system-settings.update') }}"
              class="pos-settings-form"
              data-settings-form>
            @csrf
            @method('PUT')

            <section class="pos-settings-card" aria-labelledby="panel-contact-title">
                <header class="pos-settings-card__header">
                    <span class="pos-settings-card__number" aria-hidden="true">01</span>
                    <div>
                        <p class="pos-kicker">Panel i kontakt</p>
                        <h2 id="panel-contact-title">Tożsamość systemu i powiadomienia</h2>
                        <p>Nazwa jest widoczna w panelu, a adres e-mail służy do komunikatów administracyjnych.</p>
                    </div>
                </header>

                <div class="pos-form-grid">
                    <div class="pos-field pos-field--wide">
                        <label for="panel_system_name">Nazwa systemu</label>
                        <input
                            id="panel_system_name"
                            name="panel_system_name"
                            type="text"
                            value="{{ $systemName }}"
                            maxlength="120"
                            autocomplete="organization"
                            required
                            data-system-name-input
                            @error('panel_system_name') aria-invalid="true" aria-describedby="panel_system_name_error" @enderror
                        >
                        <p class="pos-help">Nazwa wyświetlana w nagłówkach i komunikatach panelu administratora.</p>
                        @error('panel_system_name')
                            <p class="pos-error" id="panel_system_name_error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pos-field pos-field--wide">
                        <label for="admin_email">E-mail administratora</label>
                        <input
                            id="admin_email"
                            name="admin_email"
                            type="email"
                            value="{{ $adminEmail }}"
                            maxlength="190"
                            autocomplete="email"
                            placeholder="administrator@example.pl"
                            @error('admin_email') aria-invalid="true" aria-describedby="admin_email_error" @enderror
                        >
                        <p class="pos-help">Adres do informacji administracyjnych i alertów systemowych.</p>
                        @error('admin_email')
                            <p class="pos-error" id="admin_email_error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <label class="pos-toggle-card" for="email_alerts_enabled">
                    <input type="hidden" name="email_alerts_enabled" value="0">
                    <input
                        id="email_alerts_enabled"
                        name="email_alerts_enabled"
                        type="checkbox"
                        value="1"
                        data-alert-toggle
                        @checked($emailAlertsEnabled)
                    >
                    <span class="pos-toggle-card__switch" aria-hidden="true"></span>
                    <span class="pos-toggle-card__copy">
                        <strong>Wysyłaj powiadomienia e-mail</strong>
                        <small>Wyłączenie zatrzyma alerty o awariach oraz wiadomości o przywróceniu połączenia.</small>
                    </span>
                </label>
            </section>

            <section class="pos-settings-card" aria-labelledby="monitoring-parameters-title">
                <header class="pos-settings-card__header">
                    <span class="pos-settings-card__number" aria-hidden="true">02</span>
                    <div>
                        <p class="pos-kicker">Monitoring urządzeń</p>
                        <h2 id="monitoring-parameters-title">Progi i częstotliwość kontroli</h2>
                        <p>Parametry określają, kiedy urządzenie jest niedostępne, kiedy wysłać alert i jak długo przechowywać historię.</p>
                    </div>
                </header>

                <div class="pos-parameter-grid">
                    <div class="pos-parameter-card">
                        <div class="pos-field">
                            <label for="default_missing_after_minutes">Brak komunikacji po</label>
                            <div class="pos-input-unit">
                                <input
                                    id="default_missing_after_minutes"
                                    name="default_missing_after_minutes"
                                    type="number"
                                    min="1"
                                    max="1440"
                                    step="1"
                                    value="{{ $missingMinutes }}"
                                    required
                                    data-summary-target="missing"
                                    @error('default_missing_after_minutes') aria-invalid="true" aria-describedby="default_missing_after_minutes_error" @enderror
                                >
                                <span>min</span>
                            </div>
                            <p class="pos-help">Po tym czasie bez heartbeat urządzenie zostaje oznaczone jako niedostępne.</p>
                            @error('default_missing_after_minutes')
                                <p class="pos-error" id="default_missing_after_minutes_error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="pos-parameter-card">
                        <div class="pos-field">
                            <label for="default_alert_after_minutes">Wyślij alert po</label>
                            <div class="pos-input-unit">
                                <input
                                    id="default_alert_after_minutes"
                                    name="default_alert_after_minutes"
                                    type="number"
                                    min="0"
                                    max="10080"
                                    step="1"
                                    value="{{ $alertMinutes }}"
                                    required
                                    @error('default_alert_after_minutes') aria-invalid="true" aria-describedby="default_alert_after_minutes_error" @enderror
                                >
                                <span>min</span>
                            </div>
                            <p class="pos-help">Wartość 0 oznacza wysłanie alertu natychmiast po wykryciu niedostępności.</p>
                            @error('default_alert_after_minutes')
                                <p class="pos-error" id="default_alert_after_minutes_error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="pos-parameter-card">
                        <div class="pos-field">
                            <label for="default_check_interval_seconds">Interwał agenta</label>
                            <div class="pos-input-unit">
                                <input
                                    id="default_check_interval_seconds"
                                    name="default_check_interval_seconds"
                                    type="number"
                                    min="15"
                                    max="3600"
                                    step="1"
                                    value="{{ $intervalSeconds }}"
                                    required
                                    data-summary-target="interval"
                                    @error('default_check_interval_seconds') aria-invalid="true" aria-describedby="default_check_interval_seconds_error" @enderror
                                >
                                <span>s</span>
                            </div>
                            <p class="pos-help">Domyślny odstęp pomiędzy heartbeatami generowanych agentów.</p>
                            @error('default_check_interval_seconds')
                                <p class="pos-error" id="default_check_interval_seconds_error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="pos-parameter-card">
                        <div class="pos-field">
                            <label for="heartbeat_retention_days">Przechowuj heartbeaty przez</label>
                            <div class="pos-input-unit">
                                <input
                                    id="heartbeat_retention_days"
                                    name="heartbeat_retention_days"
                                    type="number"
                                    min="7"
                                    max="3650"
                                    step="1"
                                    value="{{ $retentionDays }}"
                                    required
                                    data-summary-target="retention"
                                    @error('heartbeat_retention_days') aria-invalid="true" aria-describedby="heartbeat_retention_days_error" @enderror
                                >
                                <span>dni</span>
                            </div>
                            <p class="pos-help">Starsze rekordy zostaną usunięte przez istniejące zadanie schedulera.</p>
                            @error('heartbeat_retention_days')
                                <p class="pos-error" id="heartbeat_retention_days_error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </section>

            <div class="pos-save-bar" data-save-bar>
                <div>
                    <strong data-save-state>Ustawienia są gotowe do zapisania</strong>
                    <span>Zmiany zaczną obowiązywać po zapisaniu formularza.</span>
                </div>
                <div class="pos-save-bar__actions">
                    <a href="{{ route('dashboard') }}" class="btn secondary">Anuluj</a>
                    <button type="submit" class="btn">Zapisz ustawienia</button>
                </div>
            </div>
        </form>

        <aside class="pos-settings-aside" aria-label="Informacje o konfiguracji">
            <section class="pos-aside-card pos-aside-card--accent">
                <p class="pos-kicker">Bieżąca konfiguracja</p>
                <h2 data-system-name-preview>{{ $systemName }}</h2>
                <dl class="pos-config-list">
                    <div>
                        <dt>Status alertów</dt>
                        <dd data-alert-preview>{{ $emailAlertsEnabled ? 'Aktywne' : 'Wyłączone' }}</dd>
                    </div>
                    <div>
                        <dt>Alert po awarii</dt>
                        <dd>{{ $alertMinutes === 0 ? 'Natychmiast' : $alertMinutes.' min' }}</dd>
                    </div>
                    <div>
                        <dt>Heartbeat</dt>
                        <dd>{{ $intervalSeconds }} s</dd>
                    </div>
                    <div>
                        <dt>Historia</dt>
                        <dd>{{ $retentionDays }} dni</dd>
                    </div>
                </dl>
            </section>

            <section class="pos-aside-card">
                <p class="pos-kicker">Dobre ustawienia startowe</p>
                <h2>Rekomendacja</h2>
                <ul class="pos-check-list">
                    <li>Heartbeat co 60 sekund</li>
                    <li>Brak komunikacji po 5 minutach</li>
                    <li>Alert po 10 minutach</li>
                    <li>Historia przez minimum 60 dni</li>
                </ul>
                <p class="pos-aside-note">Krótsze czasy zwiększają szybkość wykrywania, ale mogą powodować więcej krótkotrwałych alarmów.</p>
            </section>

            <section class="pos-aside-card">
                <p class="pos-kicker">Bezpieczeństwo</p>
                <h2>Bez zmian w .env</h2>
                <p>Wartości są przechowywane w tabeli ustawień i pobierane przez istniejący serwis systemowy. Ta strona nie wyświetla haseł ani kluczy.</p>
            </section>
        </aside>
    </div>
@endsection
