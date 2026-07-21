<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('legal.service_name') }}</title>
    <meta name="description" content="@yield('description')">
    <meta name="robots" content="index,follow">
    <link rel="stylesheet" href="{{ asset('panel/legal-pages.css') }}">
</head>
<body>
<a class="legal-skip" href="#main-content">Przejdź do treści</a>

<header class="legal-header">
    <div class="legal-header__inner">
        <a class="legal-brand" href="{{ route('panel.login') }}">
            <span class="legal-brand__mark" aria-hidden="true">PO</span>
            <span>
                <strong>{{ config('legal.service_name') }}</strong>
                <small>Dokumenty publiczne</small>
            </span>
        </a>

        <nav class="legal-nav" aria-label="Dokumenty prawne">
            <a href="{{ route('legal.privacy') }}" @if(request()->routeIs('legal.privacy')) aria-current="page" @endif>Polityka prywatności</a>
            <a href="{{ route('legal.rodo') }}" @if(request()->routeIs('legal.rodo')) aria-current="page" @endif>RODO</a>
            <a href="{{ route('legal.terms') }}" @if(request()->routeIs('legal.terms')) aria-current="page" @endif>Regulamin</a>
        </nav>
    </div>
</header>

<main id="main-content" class="legal-main">
    <article class="legal-document">
        <header class="legal-document__header">
            <p class="legal-eyebrow">{{ config('legal.service_name') }}</p>
            <h1>@yield('heading')</h1>
            <p class="legal-lead">@yield('lead')</p>
            <dl class="legal-meta">
                <div>
                    <dt>Wersja</dt>
                    <dd>{{ config('legal.version') }}</dd>
                </div>
                <div>
                    <dt>Obowiązuje od</dt>
                    <dd>{{ config('legal.effective_date') }}</dd>
                </div>
            </dl>
        </header>

        <div class="legal-content">
            @yield('content')
        </div>
    </article>
</main>

<footer class="legal-footer">
    <div>
        <strong>{{ config('legal.service_name') }}</strong>
        <span>Projekt open source do monitorowania stanu technicznego komputerów.</span>
    </div>
    <nav aria-label="Stopka dokumentów prawnych">
        <a href="{{ route('panel.login') }}">Logowanie</a>
        <a href="{{ route('legal.privacy') }}">Prywatność</a>
        <a href="{{ route('legal.rodo') }}">RODO</a>
        <a href="{{ route('legal.terms') }}">Regulamin</a>
        @if(config('legal.source_code_url'))
            <a href="{{ config('legal.source_code_url') }}" rel="external noopener">Kod źródłowy</a>
        @endif
    </nav>
</footer>
</body>
</html>
