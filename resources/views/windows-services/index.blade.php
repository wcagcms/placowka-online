@extends('layouts.panel')

@section('title', 'Usługi Windows — Placówka Online')
@section('eyebrow', 'Administracja')
@section('page_title', 'Centrum ustawień')
@section('page_lead', 'Zarządzaj usługami Windows sprawdzanymi przez agentów oraz zasadami tworzenia incydentów.')

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
        $monitoredCount = $services->where('monitoring_enabled', true)->count();
        $alertCount = $services->where('monitoring_enabled', true)
            ->where('alert_enabled', true)
            ->count();
        $disabledCount = $services->where('monitoring_enabled', false)->count();
    @endphp

    <section class="pos-summary-grid" aria-label="Podsumowanie konfiguracji usług">
        <article class="pos-summary-card">
            <span class="pos-summary-card__icon" aria-hidden="true">
                <x-platinum.icon name="service" :size="21" />
            </span>
            <div>
                <span class="pos-summary-card__label">Wszystkie usługi</span>
                <strong>{{ $services->count() }}</strong>
                <small>Pozycje zapisane w konfiguracji</small>
            </div>
        </article>

        <article class="pos-summary-card pos-summary-card--success">
            <span class="pos-summary-card__icon" aria-hidden="true">
                <x-platinum.icon name="health" :size="21" />
            </span>
            <div>
                <span class="pos-summary-card__label">Monitorowane</span>
                <strong>{{ $monitoredCount }}</strong>
                <small>Dołączane do nowych paczek agentów</small>
            </div>
        </article>

        <article class="pos-summary-card pos-summary-card--warning">
            <span class="pos-summary-card__icon" aria-hidden="true">
                <x-platinum.icon name="alarm" :size="21" />
            </span>
            <div>
                <span class="pos-summary-card__label">Z aktywnym alertem</span>
                <strong>{{ $alertCount }}</strong>
                <small>Tworzą incydent po wykryciu problemu</small>
            </div>
        </article>

        <article class="pos-summary-card">
            <span class="pos-summary-card__icon" aria-hidden="true">
                <x-platinum.icon name="settings" :size="21" />
            </span>
            <div>
                <span class="pos-summary-card__label">Wyłączone</span>
                <strong>{{ $disabledCount }}</strong>
                <small>Pozostają zapisane, ale nie są monitorowane</small>
            </div>
        </article>
    </section>

    <section class="pos-info-banner" aria-labelledby="services-behaviour-title">
        <span class="pos-info-banner__icon" aria-hidden="true">
            <x-platinum.icon name="windows" :size="27" />
        </span>
        <div>
            <p class="pos-kicker">Konfiguracja agentów</p>
            <h2 id="services-behaviour-title">Jak działają zmiany usług</h2>
            <p>Do pliku <code>config.json</code> trafiają tylko pozycje z włączonym monitoringiem. Zmiany są używane w nowych i regenerowanych paczkach agenta.</p>
        </div>
        <div class="pos-info-banner__note" role="note">
            <strong>Ważne</strong>
            <span>Regeneracja paczki zmienia token urządzenia. Nową paczkę należy ponownie zainstalować na komputerze placówki.</span>
        </div>
    </section>

    <details class="pos-create-panel" @if($errors->any()) open @endif>
        <summary>
            <span class="pos-create-panel__icon" aria-hidden="true">+</span>
            <span>
                <strong>Dodaj nową usługę Windows</strong>
                <small>Utwórz kolejną pozycję w konfiguracji agentów</small>
            </span>
            <span class="pos-create-panel__chevron" aria-hidden="true">⌄</span>
        </summary>

        <form method="post"
              action="{{ route('agent-windows-services.store') }}"
              class="pos-service-form pos-service-form--create">
            @csrf

            <div class="pos-service-fields">
                <div class="pos-field">
                    <label for="new_system_name">Nazwa systemowa</label>
                    <input id="new_system_name"
                           name="system_name"
                           type="text"
                           maxlength="150"
                           value="{{ old('system_name') }}"
                           placeholder="np. MSSQLSERVER"
                           autocomplete="off"
                           required>
                    <p class="pos-help">Wartość z kolumny <code>Name</code> polecenia <code>Get-Service</code>.</p>
                </div>

                <div class="pos-field">
                    <label for="new_label">Nazwa w panelu</label>
                    <input id="new_label"
                           name="label"
                           type="text"
                           maxlength="190"
                           value="{{ old('label') }}"
                           placeholder="np. Microsoft SQL Server"
                           required>
                    <p class="pos-help">Czytelna nazwa widoczna na ekranie urządzenia.</p>
                </div>

                <div class="pos-field pos-field--order">
                    <label for="new_sort_order">Kolejność</label>
                    <input id="new_sort_order"
                           name="sort_order"
                           type="number"
                           min="0"
                           max="100000"
                           value="{{ old('sort_order', 100) }}"
                           required>
                    <p class="pos-help">Niższa liczba oznacza wyższą pozycję.</p>
                </div>
            </div>

            <input type="hidden" name="expected_status" value="Running">

            <div class="pos-option-grid" aria-label="Opcje nowej usługi">
                <label class="pos-option-card">
                    <input type="hidden" name="monitoring_enabled" value="0">
                    <input type="checkbox" name="monitoring_enabled" value="1" checked>
                    <span class="pos-option-card__indicator" aria-hidden="true"></span>
                    <span>
                        <strong>Monitoruj usługę</strong>
                        <small>Dodawaj usługę do konfiguracji agentów.</small>
                    </span>
                </label>

                <label class="pos-option-card">
                    <input type="hidden" name="alert_enabled" value="0">
                    <input type="checkbox" name="alert_enabled" value="1">
                    <span class="pos-option-card__indicator" aria-hidden="true"></span>
                    <span>
                        <strong>Twórz alert</strong>
                        <small>Otwórz incydent, gdy usługa nie działa.</small>
                    </span>
                </label>
            </div>

            <div class="pos-field">
                <label for="new_description">Opis opcjonalny</label>
                <textarea id="new_description"
                          name="description"
                          rows="3"
                          maxlength="1000"
                          placeholder="Np. usługa wymagana do działania programu księgowego.">{{ old('description') }}</textarea>
            </div>

            <div class="pos-form-actions">
                <button class="btn" type="submit">Dodaj usługę</button>
            </div>
        </form>
    </details>

    <section class="pos-services-section" aria-labelledby="configured-services-title">
        <header class="pos-section-heading">
            <div>
                <p class="pos-kicker">Lista usług</p>
                <h2 id="configured-services-title">Skonfigurowane usługi</h2>
                <p>Rozwiń wybraną kartę, aby zmienić parametry. Zapis każdej usługi odbywa się osobno.</p>
            </div>
        </header>

        @if($services->isEmpty())
            <div class="pos-empty-state">
                <span aria-hidden="true"><x-platinum.icon name="service" :size="30" /></span>
                <h3>Brak skonfigurowanych usług</h3>
                <p>Dodaj pierwszą usługę Windows za pomocą formularza powyżej.</p>
            </div>
        @else
            <div class="pos-services-toolbar" data-services-toolbar>
                <div class="pos-search-field">
                    <label for="service_search">Szukaj usługi</label>
                    <div class="pos-search-field__control">
                        <span aria-hidden="true">⌕</span>
                        <input id="service_search"
                               type="search"
                               placeholder="Nazwa w panelu lub nazwa systemowa"
                               autocomplete="off"
                               data-service-search>
                    </div>
                </div>

                <div class="pos-filter-field">
                    <label for="service_filter">Pokaż</label>
                    <select id="service_filter" data-service-filter>
                        <option value="all">Wszystkie usługi</option>
                        <option value="monitored">Tylko monitorowane</option>
                        <option value="alerts">Tylko z alertem</option>
                        <option value="disabled">Tylko wyłączone</option>
                    </select>
                </div>

                <p class="pos-results-count" role="status" aria-live="polite">
                    Widoczne: <strong data-service-visible-count>{{ $services->count() }}</strong>
                    z {{ $services->count() }}
                </p>
            </div>

            <div class="pos-services-list" data-services-list>
                @foreach($services as $service)
                    <details class="pos-service-card {{ $service->monitoring_enabled ? '' : 'is-disabled' }}"
                             data-service-card
                             data-search="{{ $service->label.' '.$service->system_name.' '.($service->description ?? '') }}"
                             data-monitored="{{ $service->monitoring_enabled ? '1' : '0' }}"
                             data-alert="{{ $service->alert_enabled ? '1' : '0' }}">
                        <summary>
                            <span class="pos-service-card__number" aria-hidden="true">{{ $loop->iteration }}</span>
                            <span class="pos-service-card__identity">
                                <strong>{{ $service->label }}</strong>
                                <code>{{ $service->system_name }}</code>
                            </span>
                            <span class="pos-service-card__badges" aria-label="Stan konfiguracji">
                                <span class="pos-status-badge {{ $service->monitoring_enabled ? 'is-success' : 'is-muted' }}">
                                    {{ $service->monitoring_enabled ? 'Monitorowana' : 'Wyłączona' }}
                                </span>
                                <span class="pos-status-badge {{ $service->alert_enabled ? 'is-warning' : 'is-muted' }}">
                                    {{ $service->alert_enabled ? 'Alert aktywny' : 'Bez alertu' }}
                                </span>
                            </span>
                            <span class="pos-service-card__chevron" aria-hidden="true">⌄</span>
                        </summary>

                        <div class="pos-service-card__body">
                            @if($service->description)
                                <p class="pos-service-description">{{ $service->description }}</p>
                            @endif

                            <form method="post"
                                  action="{{ route('agent-windows-services.update', $service) }}"
                                  class="pos-service-form">
                                @csrf
                                @method('PUT')

                                <div class="pos-service-fields">
                                    <div class="pos-field">
                                        <label for="system_name_{{ $service->id }}">Nazwa systemowa</label>
                                        <input id="system_name_{{ $service->id }}"
                                               name="system_name"
                                               type="text"
                                               maxlength="150"
                                               value="{{ $service->system_name }}"
                                               required>
                                    </div>

                                    <div class="pos-field">
                                        <label for="label_{{ $service->id }}">Nazwa w panelu</label>
                                        <input id="label_{{ $service->id }}"
                                               name="label"
                                               type="text"
                                               maxlength="190"
                                               value="{{ $service->label }}"
                                               required>
                                    </div>

                                    <div class="pos-field pos-field--order">
                                        <label for="sort_order_{{ $service->id }}">Kolejność</label>
                                        <input id="sort_order_{{ $service->id }}"
                                               name="sort_order"
                                               type="number"
                                               min="0"
                                               max="100000"
                                               value="{{ $service->sort_order }}"
                                               required>
                                    </div>
                                </div>

                                <input type="hidden" name="expected_status" value="Running">

                                <div class="pos-option-grid">
                                    <label class="pos-option-card">
                                        <input type="hidden" name="monitoring_enabled" value="0">
                                        <input type="checkbox"
                                               name="monitoring_enabled"
                                               value="1"
                                               @checked($service->monitoring_enabled)>
                                        <span class="pos-option-card__indicator" aria-hidden="true"></span>
                                        <span>
                                            <strong>Monitoruj usługę</strong>
                                            <small>Dołącz usługę do konfiguracji agenta.</small>
                                        </span>
                                    </label>

                                    <label class="pos-option-card">
                                        <input type="hidden" name="alert_enabled" value="0">
                                        <input type="checkbox"
                                               name="alert_enabled"
                                               value="1"
                                               @checked($service->alert_enabled)>
                                        <span class="pos-option-card__indicator" aria-hidden="true"></span>
                                        <span>
                                            <strong>Twórz alert</strong>
                                            <small>Otwórz incydent, gdy usługa nie działa.</small>
                                        </span>
                                    </label>
                                </div>

                                <div class="pos-field">
                                    <label for="description_{{ $service->id }}">Opis</label>
                                    <textarea id="description_{{ $service->id }}"
                                              name="description"
                                              rows="3"
                                              maxlength="1000">{{ $service->description }}</textarea>
                                </div>

                                <div class="pos-form-actions">
                                    <button class="btn" type="submit">Zapisz zmiany</button>
                                </div>
                            </form>

                            <div class="pos-danger-zone">
                                <div>
                                    <strong>Usuń usługę</strong>
                                    <span>Pozycja nie będzie dodawana do kolejnych paczek agenta.</span>
                                </div>

                                <form method="post"
                                      action="{{ route('agent-windows-services.destroy', $service) }}"
                                      data-confirm-message="Usunąć usługę „{{ $service->label }}” z konfiguracji nowych agentów?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn secondary small" type="submit">Usuń usługę</button>
                                </form>
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>

            <div class="pos-empty-state pos-empty-state--filtered" data-filter-empty hidden>
                <span aria-hidden="true">⌕</span>
                <h3>Brak pasujących usług</h3>
                <p>Zmień tekst wyszukiwania albo wybierz inny filtr.</p>
            </div>
        @endif
    </section>

    <section class="pos-help-card" aria-labelledby="service-name-help-title">
        <div>
            <p class="pos-kicker">Pomoc techniczna</p>
            <h2 id="service-name-help-title">Jak sprawdzić nazwę systemową usługi</h2>
            <p>Na komputerze Windows uruchom PowerShell jako administrator i wykonaj:</p>
        </div>

        <pre><code>Get-Service | Sort-Object DisplayName | Select-Object Name, DisplayName, Status</code></pre>

        <p>Do pola „Nazwa systemowa” skopiuj wartość z kolumny <code>Name</code>, a nie z kolumny <code>DisplayName</code>.</p>
    </section>
@endsection
