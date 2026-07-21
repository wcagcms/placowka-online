@php
    $editing = $operator->exists;
    $selectedIds = collect(old('facility_ids', $selectedFacilityIds))
        ->map(fn ($id) => (int) $id);
@endphp

<div class="po-security-editor-sections">
    <section class="po-security-form-section" aria-labelledby="operator-identity-title">
        <header class="po-security-form-section-header">
            <span class="po-security-section-icon" aria-hidden="true"><x-platinum.icon name="users" /></span>
            <div>
                <p class="po-security-eyebrow">Dane podstawowe</p>
                <h2 id="operator-identity-title">Tożsamość operatora</h2>
                <p>Dane używane do identyfikacji konta w panelu i Dzienniku bezpieczeństwa.</p>
            </div>
        </header>

        <div class="po-security-form-grid">
            <div class="po-security-field">
                <label for="name">Imię i nazwisko <span aria-hidden="true">*</span></label>
                <input id="name"
                       name="name"
                       type="text"
                       value="{{ old('name', $operator->name) }}"
                       required
                       maxlength="255"
                       autocomplete="name"
                       @class(['is-invalid' => $errors->has('name')])>
                @error('name')<p class="po-security-field-error">{{ $message }}</p>@enderror
            </div>

            <div class="po-security-field">
                <label for="email">Adres e-mail <span aria-hidden="true">*</span></label>
                <input id="email"
                       name="email"
                       type="email"
                       value="{{ old('email', $operator->email) }}"
                       required
                       maxlength="255"
                       autocomplete="username"
                       @class(['is-invalid' => $errors->has('email')])>
                @error('email')<p class="po-security-field-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="po-security-form-section" aria-labelledby="operator-security-title">
        <header class="po-security-form-section-header">
            <span class="po-security-section-icon is-warning" aria-hidden="true"><x-platinum.icon name="settings" /></span>
            <div>
                <p class="po-security-eyebrow">Uwierzytelnianie</p>
                <h2 id="operator-security-title">Hasło i stan konta</h2>
                <p>Hasło tymczasowe musi zostać zmienione przez operatora po zalogowaniu.</p>
            </div>
        </header>

        <div class="po-security-form-grid">
            <div class="po-security-field">
                <label for="password">{{ $editing ? 'Nowe hasło tymczasowe' : 'Hasło tymczasowe' }} @unless($editing)<span aria-hidden="true">*</span>@endunless</label>
                <div class="po-password-field">
                    <input id="password"
                           name="password"
                           type="password"
                           @unless($editing) required @endunless
                           minlength="12"
                           maxlength="72"
                           autocomplete="new-password"
                           aria-describedby="operator-password-help"
                           @class(['is-invalid' => $errors->has('password')])>
                    <button type="button" class="po-password-toggle" data-password-toggle="password" aria-label="Pokaż hasło">Pokaż</button>
                </div>
                <p id="operator-password-help" class="po-security-field-help">
                    Minimum 12 znaków, mała i wielka litera, cyfra oraz znak specjalny.
                    @if($editing) Pozostaw puste, aby zachować obecne hasło. @endif
                </p>
                @error('password')<p class="po-security-field-error">{{ $message }}</p>@enderror
            </div>

            <div class="po-security-field">
                <label for="password_confirmation">Powtórz hasło @unless($editing)<span aria-hidden="true">*</span>@endunless</label>
                <div class="po-password-field">
                    <input id="password_confirmation"
                           name="password_confirmation"
                           type="password"
                           @unless($editing) required @endunless
                           minlength="12"
                           maxlength="72"
                           autocomplete="new-password">
                    <button type="button" class="po-password-toggle" data-password-toggle="password_confirmation" aria-label="Pokaż powtórzone hasło">Pokaż</button>
                </div>
            </div>
        </div>

        <label class="po-account-status-card">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox"
                   name="is_active"
                   value="1"
                   @checked((bool) old('is_active', $operator->is_active))>
            <span class="po-account-status-switch" aria-hidden="true"></span>
            <span class="po-account-status-copy">
                <strong>Konto aktywne</strong>
                <small>Po wyłączeniu konto natychmiast utraci możliwość logowania, a istniejące sesje zostaną unieważnione.</small>
            </span>
        </label>
    </section>

    <section class="po-security-form-section po-security-form-section-wide" aria-labelledby="operator-facilities-title" data-facility-picker>
        <header class="po-security-form-section-header po-security-form-section-header-split">
            <div class="po-security-form-section-heading">
                <span class="po-security-section-icon is-success" aria-hidden="true"><x-platinum.icon name="device" /></span>
                <div>
                    <p class="po-security-eyebrow">Zakres danych</p>
                    <h2 id="operator-facilities-title">Przypisane placówki</h2>
                    <p>Operator zobaczy wyłącznie zaznaczone placówki, ich urządzenia, incydenty i raporty.</p>
                </div>
            </div>
            <span class="po-selected-facilities" aria-live="polite">
                Wybrano: <strong data-selected-count>{{ $selectedIds->count() }}</strong>
            </span>
        </header>

        <div class="po-facility-picker-toolbar">
            <div class="po-security-field po-facility-search-field">
                <label for="facility-search">Wyszukaj placówkę</label>
                <input id="facility-search"
                       type="search"
                       placeholder="Kod lub nazwa placówki"
                       autocomplete="off"
                       data-facility-search>
            </div>
            <div class="po-facility-picker-actions">
                <button class="btn secondary" type="button" data-select-visible>Zaznacz widoczne</button>
                <button class="po-security-clear-link" type="button" data-clear-facilities>Wyczyść wybór</button>
            </div>
        </div>

        <div class="po-premium-facility-grid" data-facility-list>
            @forelse($facilities as $facility)
                <label class="po-premium-facility-option"
                       data-facility-card
                       data-search-value="{{ mb_strtolower($facility->code.' '.$facility->name) }}">
                    <input type="checkbox"
                           name="facility_ids[]"
                           value="{{ $facility->id }}"
                           @checked($selectedIds->contains((int) $facility->id))>
                    <span class="po-premium-facility-check" aria-hidden="true"></span>
                    <span class="po-premium-facility-code">{{ $facility->code }}</span>
                    <span class="po-premium-facility-copy">
                        <strong>{{ $facility->name }}</strong>
                        <small>{{ $facility->is_active ? 'Placówka aktywna' : 'Placówka nieaktywna' }}</small>
                    </span>
                </label>
            @empty
                <div class="po-security-empty po-security-empty-compact">
                    <h3>Brak placówek</h3>
                    <p>Najpierw dodaj placówkę, a następnie przypisz ją operatorowi.</p>
                </div>
            @endforelse
        </div>

        <p class="po-facility-no-results" data-facility-no-results hidden>Nie znaleziono placówki pasującej do wyszukiwania.</p>
        @error('facility_ids')<p class="po-security-field-error">{{ $message }}</p>@enderror
        @error('facility_ids.*')<p class="po-security-field-error">{{ $message }}</p>@enderror
    </section>
</div>
