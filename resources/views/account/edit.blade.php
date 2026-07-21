@extends('layouts.panel')

@section('title', 'Moje konto — Placówka Online')
@section('eyebrow', 'Bezpieczeństwo')
@section('page_title', 'Moje konto')
@section('page_lead', 'Zmień hasło używane do logowania do panelu.')

@section('content')
<div class="po-security-grid">
    <section class="card padded" aria-labelledby="account-data-title">
        <h2 id="account-data-title">Dane konta</h2>
        <dl class="po-definition-list">
            <div><dt>Użytkownik</dt><dd>{{ $user->name }}</dd></div>
            <div><dt>Adres e-mail</dt><dd>{{ $user->email }}</dd></div>
            <div><dt>Rola</dt><dd>{{ $user->roleLabel() }}</dd></div>
            <div>
                <dt>Ostatnie logowanie</dt>
                <dd>{{ $user->last_login_at?->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') ?? 'brak danych' }}</dd>
            </div>
        </dl>
    </section>

    <section class="card padded" aria-labelledby="password-title">
        <h2 id="password-title">Zmiana hasła</h2>

        @if($user->must_change_password)
            <div class="notice warn" role="status">
                Konto wymaga ustawienia własnego hasła przed dalszą pracą.
            </div>
        @endif

        <form method="post" action="{{ route('account.update') }}" class="po-form-stack">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password">Aktualne hasło</label>
                <input id="current_password"
                       name="current_password"
                       type="password"
                       required
                       autocomplete="current-password">
            </div>

            <div>
                <label for="password">Nowe hasło</label>
                <input id="password"
                       name="password"
                       type="password"
                       required
                       minlength="12"
                       maxlength="72"
                       autocomplete="new-password"
                       aria-describedby="password-help">
                <p id="password-help" class="muted">Minimum 12 znaków, mała i wielka litera, cyfra oraz znak specjalny.</p>
            </div>

            <div>
                <label for="password_confirmation">Powtórz nowe hasło</label>
                <input id="password_confirmation"
                       name="password_confirmation"
                       type="password"
                       required
                       minlength="12"
                       maxlength="72"
                       autocomplete="new-password">
            </div>

            <button class="btn" type="submit">Zmień hasło</button>
        </form>
    </section>
</div>
@endsection
