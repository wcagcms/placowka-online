<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Placówka Online')</title>
    <meta name="description" content="@yield('meta_description', 'Monitoring infrastruktury placówek i urządzeń.')">
    <link rel="stylesheet" href="{{ asset('panel/panel.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/saas-platinum-layout.css') }}">
    @stack('head')
</head>
<body class="po-panel-body">
<a class="po-skip-link" href="#main-content">Przejdź do głównej treści</a>

@php
    $currentUser = auth()->user();
    $isAdmin = $currentUser?->isAdmin() ?? false;
@endphp

<div class="po-shell">
    <aside class="po-sidebar" id="panelSidebar" aria-label="Główna nawigacja panelu">
        <a class="po-brand" href="{{ route('dashboard') }}" aria-label="Placówka Online — panel główny">
            <span class="po-brand-mark" aria-hidden="true">
                <x-platinum.icon name="home" :size="23" />
            </span>
            <span class="po-brand-copy">
                <strong>Placówka Online</strong>
                <span>Monitoring infrastruktury</span>
            </span>
        </a>

        <p class="po-nav-label">Monitoring</p>
        <ul class="po-nav-list">
            <li>
                <a class="po-nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"
                   href="{{ route('dashboard') }}"
                   @if(request()->routeIs('dashboard')) aria-current="page" @endif
                   data-close-panel-nav>
                    <x-platinum.icon name="dashboard" class="po-nav-icon" />
                    <span>Panel główny</span>
                </a>
            </li>
            <li>
                <a class="po-nav-link {{ request()->routeIs('monitoring-center.*') ? 'is-active' : '' }}"
                   href="{{ route('monitoring-center.index') }}"
                   @if(request()->routeIs('monitoring-center.*')) aria-current="page" @endif
                   data-close-panel-nav>
                    <x-platinum.icon name="internet" class="po-nav-icon" />
                    <span>Centrum monitoringu</span>
                </a>
            </li>
            <li>
                <a class="po-nav-link {{ request()->routeIs('agents.*') ? 'is-active' : '' }}"
                   href="{{ route('agents.index') }}"
                   @if(request()->routeIs('agents.*')) aria-current="page" @endif
                   data-close-panel-nav>
                    <x-platinum.icon name="device" class="po-nav-icon" />
                    <span>Stan agentów</span>
                </a>
            </li>
            <li>
                <a class="po-nav-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}"
                   href="{{ route('reports.index') }}"
                   @if(request()->routeIs('reports.*')) aria-current="page" @endif
                   data-close-panel-nav>
                    <x-platinum.icon name="history" class="po-nav-icon" />
                    <span>Raporty</span>
                </a>
            </li>
            <li>
                <a class="po-nav-link {{ request()->routeIs('incidents.*') ? 'is-active' : '' }}"
                   href="{{ route('incidents.index') }}"
                   @if(request()->routeIs('incidents.*')) aria-current="page" @endif
                   data-close-panel-nav>
                    <x-platinum.icon name="bell" class="po-nav-icon" />
                    <span>Incydenty</span>
                </a>
            </li>
            @if($isAdmin)
                <li>
                    <a class="po-nav-link {{ request()->routeIs('system.status') ? 'is-active' : '' }}"
                       href="{{ route('system.status') }}"
                       @if(request()->routeIs('system.status')) aria-current="page" @endif
                       data-close-panel-nav>
                        <x-platinum.icon name="health" class="po-nav-icon" />
                        <span>Stan systemu</span>
                    </a>
                </li>
            @endif
        </ul>

        @if($isAdmin)
            <p class="po-nav-label">Administracja</p>
            <ul class="po-nav-list">
                <li>
                    <a class="po-nav-link {{ request()->routeIs('facilities.create') ? 'is-active' : '' }}"
                       href="{{ route('facilities.create') }}"
                       @if(request()->routeIs('facilities.create')) aria-current="page" @endif
                       data-close-panel-nav>
                        <x-platinum.icon name="device" class="po-nav-icon" />
                        <span>Dodaj placówkę</span>
                    </a>
                </li>
                <li>
                    <a class="po-nav-link {{ request()->routeIs('operators.*') ? 'is-active' : '' }}"
                       href="{{ route('operators.index') }}"
                       @if(request()->routeIs('operators.*')) aria-current="page" @endif
                       data-close-panel-nav>
                        <x-platinum.icon name="users" class="po-nav-icon" />
                        <span>Operatorzy</span>
                    </a>
                </li>
                <li>
                    <a class="po-nav-link {{ request()->routeIs('agent-windows-services.*') ? 'is-active' : '' }}"
                       href="{{ route('agent-windows-services.index') }}"
                       @if(request()->routeIs('agent-windows-services.*')) aria-current="page" @endif
                       data-close-panel-nav>
                        <x-platinum.icon name="service" class="po-nav-icon" />
                        <span>Usługi Windows</span>
                    </a>
                </li>
                <li>
                    <a class="po-nav-link {{ request()->routeIs('system-settings.*') ? 'is-active' : '' }}"
                       href="{{ route('system-settings.edit') }}"
                       @if(request()->routeIs('system-settings.*')) aria-current="page" @endif
                       data-close-panel-nav>
                        <x-platinum.icon name="settings" class="po-nav-icon" />
                        <span>Ustawienia</span>
                    </a>
                </li>
                <li>
                    <a class="po-nav-link {{ request()->routeIs('backups.*') ? 'is-active' : '' }}"
                       href="{{ route('backups.index') }}"
                       @if(request()->routeIs('backups.*')) aria-current="page" @endif
                       data-close-panel-nav>
                        <x-platinum.icon name="history" class="po-nav-icon" />
                        <span>Kopie zapasowe</span>
                    </a>
                </li>
                <li>
                    <a class="po-nav-link {{ request()->routeIs('security-audit.*') ? 'is-active' : '' }}"
                       href="{{ route('security-audit.index') }}"
                       @if(request()->routeIs('security-audit.*')) aria-current="page" @endif
                       data-close-panel-nav>
                        <x-platinum.icon name="history" class="po-nav-icon" />
                        <span>Dziennik bezpieczeństwa</span>
                    </a>
                </li>
            </ul>
        @endif

        <div class="po-sidebar-footer">
            <strong>{{ $currentUser?->roleLabel() ?? 'Użytkownik' }}</strong>
            <p>{{ $currentUser?->email }}</p>
            <a class="po-mini-button" href="{{ route('account.edit') }}">Moje konto</a>
        </div>
    </aside>

    <div class="po-sidebar-overlay" data-panel-overlay hidden></div>

    <div class="po-main-column">
        <header class="po-topbar">
            <div class="po-topbar-heading">
                <button class="po-menu-button"
                        id="panelMenuButton"
                        type="button"
                        aria-controls="panelSidebar"
                        aria-expanded="false"
                        aria-label="Otwórz menu">
                    <x-platinum.icon name="menu" />
                </button>

                <div>
                    @hasSection('eyebrow')
                        <p class="po-topbar-eyebrow">@yield('eyebrow')</p>
                    @endif
                    <p class="po-topbar-context">
                        Placówka Online / {{ $currentUser?->roleLabel() ?? 'Panel' }}
                    </p>
                </div>
            </div>

            <div class="po-topbar-actions">
                <a class="po-icon-button" href="{{ route('monitoring-center.index') }}" aria-label="Otwórz centrum monitoringu">
                    <x-platinum.icon name="bell" />
                </a>

                <a class="po-profile-button" href="{{ route('account.edit') }}">
                    <span class="po-avatar" aria-hidden="true">{{ $currentUser?->initials() ?? 'U' }}</span>
                    <span>{{ $currentUser?->name ?? 'Konto' }}</span>
                </a>

                <form class="po-logout-form" method="post" action="{{ route('panel.logout') }}">
                    @csrf
                    <button class="po-profile-button" type="submit">
                        <span>Wyloguj</span>
                    </button>
                </form>
            </div>
        </header>

        <main class="po-main" id="main-content" tabindex="-1">
            <header class="po-page-header">
                <div>
                    <h1>@yield('page_title', 'Placówka Online')</h1>
                    @hasSection('page_lead')
                        <p class="lead">@yield('page_lead')</p>
                    @endif
                </div>

                @hasSection('page_actions')
                    <div class="actions po-page-actions">
                        @yield('page_actions')
                    </div>
                @endif
            </header>

            @if(session('success'))
                <div class="notice ok" role="status">{{ session('success') }}</div>
            @endif

            @if(session('warning'))
                <div class="notice warn" role="status">{{ session('warning') }}</div>
            @endif

            @if($errors->any())
                <div class="notice error" role="alert">
                    <strong>Nie udało się wykonać operacji.</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <footer class="po-open-source-notice">
        <span>Placówka Online — oprogramowanie bez gwarancji, AGPL-3.0-or-later.</span>
        @if(config('legal.source_code_url'))
            <a href="{{ config('legal.source_code_url') }}" rel="external noopener">Pobierz odpowiadający kod źródłowy</a>
        @endif
    </footer>
</div>

<nav class="po-mobile-nav" aria-label="Nawigacja mobilna">
    <a class="po-mobile-nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"
       href="{{ route('dashboard') }}"
       @if(request()->routeIs('dashboard')) aria-current="page" @endif>
        <x-platinum.icon name="dashboard" />
        <span>Panel</span>
    </a>
    <a class="po-mobile-nav-link {{ request()->routeIs('monitoring-center.*', 'agents.*', 'incidents.*') ? 'is-active' : '' }}"
       href="{{ route('monitoring-center.index') }}"
       @if(request()->routeIs('monitoring-center.*', 'agents.*', 'incidents.*')) aria-current="page" @endif>
        <x-platinum.icon name="internet" />
        <span>Monitoring</span>
    </a>
    <a class="po-mobile-nav-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}"
       href="{{ route('reports.index') }}"
       @if(request()->routeIs('reports.*')) aria-current="page" @endif>
        <x-platinum.icon name="history" />
        <span>Raporty</span>
    </a>
    <a class="po-mobile-nav-link {{ request()->routeIs('account.*', 'operators.*', 'system-settings.*', 'agent-windows-services.*', 'backups.*') ? 'is-active' : '' }}"
       href="{{ $isAdmin ? route('operators.index') : route('account.edit') }}"
       @if(request()->routeIs('account.*', 'operators.*', 'system-settings.*', 'agent-windows-services.*', 'backups.*')) aria-current="page" @endif>
        <x-platinum.icon name="settings" />
        <span>Więcej</span>
    </a>
</nav>

<script src="{{ asset('panel/saas-platinum-layout.js') }}" defer></script>
@stack('scripts')
</body>
</html>
