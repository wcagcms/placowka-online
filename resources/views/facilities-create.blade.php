@extends('layouts.panel')

@section('title', 'Dodaj placówkę — Placówka Online')
@section('eyebrow', 'Zarządzanie placówkami')
@section('page_title', 'Dodaj placówkę')
@section('page_lead', 'Utwórz placówkę i pierwsze urządzenie, a następnie zarejestruj agent za pomocą stałego instalatora i kodu jednorazowego.')

@section('page_actions')
    <a class="btn secondary" href="{{ route('dashboard') }}">Wróć do panelu</a>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-facility-create.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('panel/saas-platinum-facility-create.js') }}" defer></script>
@endpush

@section('content')
    @php
        $facilityCode = old('facility_code', '');
        $facilityName = old('facility_name', '');
        $deviceName = old('device_name', 'Komputer sekretariat');
        $contactEmail = old('contact_email', '');
    @endphp

    <div class="pof-create" data-facility-create>
        <section class="pof-intro" aria-labelledby="pof-intro-title">
            <div class="pof-intro__copy">
                <span class="pof-intro__icon" aria-hidden="true">
                    <x-platinum.icon name="device" :size="24" />
                </span>
                <div>
                    <p class="pof-kicker">Szybkie wdrożenie</p>
                    <h2 id="pof-intro-title">Nowa placówka i pierwszy agent</h2>
                    <p>Po zapisaniu danych system utworzy urządzenie. Token zostanie wydany dopiero podczas dwuetapowej rejestracji na właściwym komputerze.</p>
                </div>
            </div>

            <ol class="pof-process" aria-label="Etapy dodawania placówki">
                <li>
                    <span aria-hidden="true">01</span>
                    <div><strong>Dane placówki</strong><small>Kod, nazwa i kontakt</small></div>
                </li>
                <li>
                    <span aria-hidden="true">02</span>
                    <div><strong>Pierwsze urządzenie</strong><small>Nazwa komputera w panelu</small></div>
                </li>
                <li>
                    <span aria-hidden="true">03</span>
                    <div><strong>Kod instalacyjny</strong><small>15 minut, jedno użycie</small></div>
                </li>
            </ol>
        </section>

        <div class="pof-layout">
            <form class="pof-form"
                  method="post"
                  action="{{ route('facilities.store') }}"
                  data-facility-form>
                @csrf

                <section class="pof-card" aria-labelledby="pof-facility-data-title">
                    <header class="pof-card__header">
                        <span class="pof-card__number" aria-hidden="true">01</span>
                        <div>
                            <p class="pof-kicker">Dane podstawowe</p>
                            <h2 id="pof-facility-data-title">Informacje o placówce</h2>
                            <p>Podaj krótki kod używany w panelu oraz pełną nazwę widoczną w raportach.</p>
                        </div>
                    </header>

                    <div class="pof-fields pof-fields--facility">
                        <div class="pof-field">
                            <label for="facility_code">Kod placówki <span aria-hidden="true">*</span></label>
                            <input
                                id="facility_code"
                                name="facility_code"
                                type="text"
                                value="{{ $facilityCode }}"
                                maxlength="50"
                                placeholder="np. PP10"
                                autocomplete="off"
                                autocapitalize="characters"
                                spellcheck="false"
                                required
                                data-facility-code
                                @error('facility_code') aria-invalid="true" aria-describedby="facility_code_help facility_code_error" @else aria-describedby="facility_code_help" @enderror
                            >
                            <p class="pof-help" id="facility_code_help">Krótki i jednoznaczny identyfikator, np. PP10, SP3 albo CUS.</p>
                            @error('facility_code')
                                <p class="pof-error" id="facility_code_error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="pof-field pof-field--wide">
                            <label for="facility_name">Nazwa placówki <span aria-hidden="true">*</span></label>
                            <input
                                id="facility_name"
                                name="facility_name"
                                type="text"
                                value="{{ $facilityName }}"
                                maxlength="255"
                                placeholder="np. Przedszkole Publiczne nr 10"
                                autocomplete="organization"
                                required
                                data-facility-name
                                @error('facility_name') aria-invalid="true" aria-describedby="facility_name_help facility_name_error" @else aria-describedby="facility_name_help" @enderror
                            >
                            <p class="pof-help" id="facility_name_help">Pełna nazwa będzie używana w panelu, raportach i procesie rejestracji agenta.</p>
                            @error('facility_name')
                                <p class="pof-error" id="facility_name_error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="pof-card" aria-labelledby="pof-device-data-title">
                    <header class="pof-card__header">
                        <span class="pof-card__number" aria-hidden="true">02</span>
                        <div>
                            <p class="pof-kicker">Urządzenie i kontakt</p>
                            <h2 id="pof-device-data-title">Pierwszy komputer placówki</h2>
                            <p>Urządzenie zostanie utworzone razem z placówką. Kolejne komputery dodasz później z jej szczegółów.</p>
                        </div>
                    </header>

                    <div class="pof-fields">
                        <div class="pof-field pof-field--wide">
                            <label for="device_name">Nazwa urządzenia <span aria-hidden="true">*</span></label>
                            <input
                                id="device_name"
                                name="device_name"
                                type="text"
                                value="{{ $deviceName }}"
                                maxlength="255"
                                autocomplete="off"
                                required
                                data-device-name
                                @error('device_name') aria-invalid="true" aria-describedby="device_name_help device_name_error" @else aria-describedby="device_name_help" @enderror
                            >
                            <p class="pof-help" id="device_name_help">Najczęściej: „Komputer sekretariat”, „Serwer” albo nazwa zgodna z lokalizacją stanowiska.</p>
                            @error('device_name')
                                <p class="pof-error" id="device_name_error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="pof-field pof-field--wide">
                            <label for="contact_email">E-mail kontaktowy placówki <span class="pof-optional">opcjonalnie</span></label>
                            <input
                                id="contact_email"
                                name="contact_email"
                                type="email"
                                value="{{ $contactEmail }}"
                                maxlength="255"
                                placeholder="np. sekretariat@placowka.example.org"
                                autocomplete="email"
                                inputmode="email"
                                data-contact-email
                                @error('contact_email') aria-invalid="true" aria-describedby="contact_email_help contact_email_error" @else aria-describedby="contact_email_help" @enderror
                            >
                            <p class="pof-help" id="contact_email_help">Adres można później wykorzystać do dedykowanych powiadomień i kontaktu administracyjnego.</p>
                            @error('contact_email')
                                <p class="pof-error" id="contact_email_error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                @error('package')
                    <div class="pof-package-error" role="alert">
                        <strong>Nie udało się utworzyć placówki lub urządzenia.</strong>
                        <p>{{ $message }}</p>
                    </div>
                @enderror

                <div class="pof-submit-bar">
                    <div>
                        <strong>Placówka zostanie utworzona od razu.</strong>
                        <span>Po zapisaniu przejdziesz do ekranu urządzenia i wygenerujesz jednorazowy kod.</span>
                    </div>
                    <div class="pof-submit-actions">
                        <a class="btn secondary" href="{{ route('dashboard') }}">Anuluj</a>
                        <button class="btn pof-primary-button" type="submit" data-submit-button>
                            <span data-submit-label>Zapisz placówkę i urządzenie</span>
                        </button>
                    </div>
                </div>
            </form>

            <aside class="pof-aside" aria-label="Podsumowanie tworzonej placówki">
                <section class="pof-preview" aria-labelledby="pof-preview-title">
                    <p class="pof-kicker">Podgląd</p>
                    <h2 id="pof-preview-title">Tworzona placówka</h2>

                    <div class="pof-preview__identity">
                        <span class="pof-preview__code" data-preview-code>{{ $facilityCode !== '' ? $facilityCode : 'KOD' }}</span>
                        <div>
                            <strong data-preview-name>{{ $facilityName !== '' ? $facilityName : 'Nazwa placówki' }}</strong>
                            <span>Nowa placówka</span>
                        </div>
                    </div>

                    <dl class="pof-preview__details">
                        <div>
                            <dt>Pierwsze urządzenie</dt>
                            <dd data-preview-device>{{ $deviceName }}</dd>
                        </div>
                        <div>
                            <dt>Kontakt</dt>
                            <dd data-preview-email>{{ $contactEmail !== '' ? $contactEmail : 'Nie podano' }}</dd>
                        </div>
                        <div>
                            <dt>Status początkowy</dt>
                            <dd><span class="pof-status">Oczekuje na instalację</span></dd>
                        </div>
                    </dl>
                </section>

                <section class="pof-aside-card" aria-labelledby="pof-after-save-title">
                    <span class="pof-aside-card__icon" aria-hidden="true">
                        <x-platinum.icon name="service" :size="21" />
                    </span>
                    <div>
                        <p class="pof-kicker">Po zapisaniu</p>
                        <h2 id="pof-after-save-title">System wykona automatycznie</h2>
                        <ul class="pof-check-list">
                            <li>utworzenie placówki,</li>
                            <li>utworzenie pierwszego urządzenia,</li>
                            <li>wygenerowanie UUID urządzenia,</li>
                            <li>przygotowanie urządzenia do bezpiecznej rejestracji,</li>
                            <li>przejście do generowania kodu instalacyjnego.</li>
                        </ul>
                    </div>
                </section>

                <section class="pof-aside-card pof-aside-card--security" aria-labelledby="pof-security-title">
                    <span class="pof-aside-card__icon" aria-hidden="true">
                        <x-platinum.icon name="health" :size="21" />
                    </span>
                    <div>
                        <p class="pof-kicker">Bezpieczeństwo</p>
                        <h2 id="pof-security-title">Token nie trafia do pobieranego pliku</h2>
                        <p>Stały instalator nie zawiera danych urządzenia. Kod jest ważny 15 minut, działa jeden raz, a token jest wydawany dopiero po skopiowaniu plików na komputer.</p>
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
