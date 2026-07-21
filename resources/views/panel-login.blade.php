<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Logowanie — Placówka Online</title>
    <meta name="description" content="Bezpieczne logowanie do panelu monitoringu Placówka Online.">
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-login.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/legal-pages.css') }}">
</head>
<body>
<a class="login-skip" href="#login-form">Przejdź do formularza logowania</a>

<main class="login-shell">
    <section class="login-presentation" aria-labelledby="login-product-title">
        <div class="login-brand">
            <span class="login-mark" aria-hidden="true">PO</span>
            <span>
                <strong>Placówka Online</strong>
                <small>Monitoring infrastruktury</small>
            </span>
        </div>

        <div>
            <p class="login-eyebrow">SaaS Platinum</p>
            <h1 id="login-product-title">Pełny obraz stanu placówek w jednym panelu.</h1>
            <p class="login-lead">Administrator zarządza systemem i operatorami. Operator widzi wyłącznie przypisane mu placówki.</p>
        </div>

        <ul class="login-features">
            <li><span aria-hidden="true">✓</span> Indywidualne konta użytkowników</li>
            <li><span aria-hidden="true">✓</span> Dostęp ograniczony do przypisanych placówek</li>
            <li><span aria-hidden="true">✓</span> Dziennik operacji bezpieczeństwa</li>
        </ul>
    </section>

    <section class="login-card" aria-labelledby="login-title">
        <div class="login-card-heading">
            <span class="login-security" aria-hidden="true">●</span>
            <span>Bezpieczny panel użytkownika</span>
        </div>

        <h2 id="login-title">Zaloguj się</h2>
        <p>Podaj adres e-mail i hasło przypisane do Twojego konta.</p>

        @if($errors->any())
            <div class="login-error" role="alert">
                <strong>Nie udało się zalogować.</strong>
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form id="login-form" method="post" action="{{ route('panel.login.store') }}">
            @csrf

            <label for="email">Adres e-mail</label>
            <input id="email"
                   name="email"
                   type="email"
                   value="{{ old('email') }}"
                   required
                   maxlength="255"
                   autocomplete="username"
                   autofocus>

            <label for="password">Hasło</label>
            <input id="password"
                   name="password"
                   type="password"
                   required
                   maxlength="255"
                   autocomplete="current-password">

            <button type="submit">Zaloguj do panelu</button>
        </form>

        <p class="login-note">Po pięciu błędnych próbach logowanie zostanie czasowo zablokowane.</p>

        <div class="login-legal-links">
            <nav class="login-legal-links__nav" aria-label="Dokumenty prawne i kod źródłowy">
                <a href="{{ route('legal.privacy') }}">Polityka prywatności</a>
                <a href="{{ route('legal.rodo') }}">RODO</a>
                <a href="{{ route('legal.terms') }}">Regulamin</a>
                @if(config('legal.source_code_url'))
                    <a href="{{ config('legal.source_code_url') }}" rel="external noopener">Kod źródłowy</a>
                @endif
            </nav>
            <span>Dokumenty i kod źródłowy są dostępne bez logowania.</span>
        </div>
    </section>
</main>
</body>
</html>
