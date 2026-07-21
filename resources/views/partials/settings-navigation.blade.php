<section class="pos-settings-hub" aria-labelledby="settings-hub-title">
    <div class="pos-settings-hub__intro">
        <span class="pos-settings-hub__icon" aria-hidden="true">
            <x-platinum.icon name="settings" :size="24" />
        </span>
        <div>
            <p class="pos-kicker">Centrum konfiguracji</p>
            <h2 id="settings-hub-title">Ustawienia Placówki Online</h2>
            <p>Wybierz obszar, który chcesz skonfigurować. Każda sekcja zapisuje zmiany niezależnie.</p>
        </div>
    </div>

    <nav class="pos-settings-tabs" aria-label="Sekcje ustawień">
        <a href="{{ route('system-settings.edit') }}"
           @class([
               'pos-settings-tab',
               'is-active' => request()->routeIs('system-settings.*'),
           ])
           @if(request()->routeIs('system-settings.*')) aria-current="page" @endif>
            <span class="pos-settings-tab__icon" aria-hidden="true">
                <x-platinum.icon name="settings" :size="22" />
            </span>
            <span class="pos-settings-tab__copy">
                <strong>Ustawienia systemu</strong>
                <small>Panel, alerty, interwały i retencja danych</small>
            </span>
            <span class="pos-settings-tab__arrow" aria-hidden="true">→</span>
        </a>

        <a href="{{ route('agent-windows-services.index') }}"
           @class([
               'pos-settings-tab',
               'is-active' => request()->routeIs('agent-windows-services.*'),
           ])
           @if(request()->routeIs('agent-windows-services.*')) aria-current="page" @endif>
            <span class="pos-settings-tab__icon" aria-hidden="true">
                <x-platinum.icon name="service" :size="22" />
            </span>
            <span class="pos-settings-tab__copy">
                <strong>Usługi Windows</strong>
                <small>Usługi sprawdzane przez agentów i zasady alertów</small>
            </span>
            <span class="pos-settings-tab__arrow" aria-hidden="true">→</span>
        </a>
    </nav>
</section>
