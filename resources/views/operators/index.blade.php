@extends('layouts.panel')

@section('title', 'Operatorzy — Placówka Online')
@section('eyebrow', 'Administracja')
@section('page_title', 'Operatorzy')
@section('page_lead', 'Zarządzaj indywidualnymi kontami oraz zakresem placówek widocznych dla każdego operatora.')

@push('head')
<link rel="stylesheet" href="{{ asset('panel/saas-platinum-security.css') }}">
@endpush

@section('page_actions')
<a class="btn secondary" href="{{ route('security-audit.index') }}">Dziennik bezpieczeństwa</a>
<a class="btn" href="{{ route('operators.create') }}">Dodaj operatora</a>
@endsection

@section('content')
<section class="po-security-kpis" aria-label="Podsumowanie kont operatorów">
    <article class="po-security-kpi is-primary">
        <span class="po-security-kpi-icon" aria-hidden="true"><x-platinum.icon name="users" /></span>
        <div>
            <span>Wszyscy operatorzy</span>
            <strong>{{ $stats['total'] }}</strong>
            <small>Utworzone konta</small>
        </div>
    </article>

    <article class="po-security-kpi is-success">
        <span class="po-security-kpi-icon" aria-hidden="true"><x-platinum.icon name="health" /></span>
        <div>
            <span>Konta aktywne</span>
            <strong>{{ $stats['active'] }}</strong>
            <small>Mogą się zalogować</small>
        </div>
    </article>

    <article class="po-security-kpi is-danger">
        <span class="po-security-kpi-icon" aria-hidden="true"><x-platinum.icon name="alarm" /></span>
        <div>
            <span>Konta nieaktywne</span>
            <strong>{{ $stats['inactive'] }}</strong>
            <small>Dostęp zablokowany</small>
        </div>
    </article>

    <article class="po-security-kpi is-warning">
        <span class="po-security-kpi-icon" aria-hidden="true"><x-platinum.icon name="device" /></span>
        <div>
            <span>Bez placówki</span>
            <strong>{{ $stats['unassigned'] }}</strong>
            <small>Brak dostępu do danych</small>
        </div>
    </article>
</section>

<section class="po-security-toolbar" aria-labelledby="operator-filter-title">
    <div class="po-security-toolbar-heading">
        <div>
            <p class="po-security-eyebrow">Zarządzanie dostępem</p>
            <h2 id="operator-filter-title">Lista operatorów</h2>
            <p>Wyszukaj konto po nazwie, adresie e-mail albo przypisanej placówce.</p>
        </div>
        <span class="po-security-result-count">
            {{ $operators->total() }}
            {{ $operators->total() === 1 ? 'operator' : 'operatorów' }}
        </span>
    </div>

    <form method="get" action="{{ route('operators.index') }}" class="po-security-filters">
        <div class="po-security-field po-security-field-search">
            <label for="operator-q">Wyszukaj operatora</label>
            <div class="po-security-input-icon">
                <span aria-hidden="true"><x-platinum.icon name="users" :size="18" /></span>
                <input id="operator-q"
                       name="q"
                       type="search"
                       value="{{ $search }}"
                       maxlength="120"
                       placeholder="Nazwisko, e-mail lub placówka">
            </div>
        </div>

        <div class="po-security-field">
            <label for="operator-status">Status konta</label>
            <select id="operator-status" name="status">
                <option value="">Wszystkie konta</option>
                <option value="active" @selected($selectedStatus === 'active')>Aktywne</option>
                <option value="inactive" @selected($selectedStatus === 'inactive')>Nieaktywne</option>
                <option value="unassigned" @selected($selectedStatus === 'unassigned')>Bez placówki</option>
            </select>
        </div>

        <div class="po-security-filter-actions">
            <button class="btn secondary" type="submit">Zastosuj filtry</button>
            @if($search !== '' || $selectedStatus !== '')
                <a class="po-security-clear-link" href="{{ route('operators.index') }}">Wyczyść</a>
            @endif
        </div>
    </form>
</section>

<section class="po-operator-premium-list" aria-label="Konta operatorów">
    @forelse($operators as $operator)
        <article class="po-operator-premium-card {{ $operator->is_active ? '' : 'is-inactive' }}">
            <header class="po-operator-premium-header">
                <div class="po-operator-identity">
                    <span class="po-operator-avatar" aria-hidden="true">{{ $operator->initials() }}</span>
                    <div>
                        <div class="po-operator-name-row">
                            <h2>{{ $operator->name }}</h2>
                            <span class="po-security-badge {{ $operator->is_active ? 'is-success' : 'is-danger' }}">
                                {{ $operator->is_active ? 'AKTYWNY' : 'NIEAKTYWNY' }}
                            </span>
                            @if($operator->must_change_password)
                                <span class="po-security-badge is-warning">ZMIANA HASŁA</span>
                            @endif
                        </div>
                        <a class="po-operator-email" href="mailto:{{ $operator->email }}">{{ $operator->email }}</a>
                    </div>
                </div>

                <a class="btn secondary po-operator-edit-button" href="{{ route('operators.edit', $operator) }}">
                    Edytuj dostęp
                </a>
            </header>

            <div class="po-operator-meta-grid">
                <div>
                    <span class="po-operator-meta-label">Przypisane placówki</span>
                    <strong>{{ $operator->facilities_count }}</strong>
                    <small>{{ $operator->facilities_count === 1 ? 'placówka' : 'placówek' }}</small>
                </div>
                <div>
                    <span class="po-operator-meta-label">Ostatnie logowanie</span>
                    <strong>{{ $operator->last_login_at?->timezone('Europe/Warsaw')->format('d.m.Y') ?? 'Nigdy' }}</strong>
                    <small>{{ $operator->last_login_at?->timezone('Europe/Warsaw')->format('H:i') ?? 'Brak aktywności' }}</small>
                </div>
                <div>
                    <span class="po-operator-meta-label">Zakres dostępu</span>
                    <strong>{{ $operator->facilities_count > 0 ? 'Ograniczony' : 'Brak danych' }}</strong>
                    <small>Tylko przypisane placówki</small>
                </div>
            </div>

            <div class="po-operator-facilities">
                <div class="po-operator-facilities-heading">
                    <strong>Widoczne placówki</strong>
                    <span>{{ $operator->facilities_count }}</span>
                </div>

                <div class="po-operator-facility-chips">
                    @forelse($operator->facilities as $facility)
                        <span class="po-facility-chip {{ $facility->is_active ? '' : 'is-inactive' }}">
                            <strong>{{ $facility->code }}</strong>
                            <span>{{ $facility->name }}</span>
                        </span>
                    @empty
                        <span class="po-operator-no-access">
                            Operator nie ma jeszcze dostępu do żadnej placówki.
                        </span>
                    @endforelse
                </div>
            </div>
        </article>
    @empty
        <div class="po-security-empty">
            <span class="po-security-empty-icon" aria-hidden="true"><x-platinum.icon name="users" :size="30" /></span>
            <h2>Nie znaleziono operatorów</h2>
            <p>
                @if($search !== '' || $selectedStatus !== '')
                    Zmień kryteria wyszukiwania albo wyczyść zastosowane filtry.
                @else
                    Dodaj pierwsze indywidualne konto i przypisz mu placówki.
                @endif
            </p>
            @if($search !== '' || $selectedStatus !== '')
                <a class="btn secondary" href="{{ route('operators.index') }}">Wyczyść filtry</a>
            @else
                <a class="btn" href="{{ route('operators.create') }}">Dodaj operatora</a>
            @endif
        </div>
    @endforelse
</section>

@if($operators->hasPages())
    <div class="po-security-pagination">
        {{ $operators->links() }}
    </div>
@endif
@endsection
