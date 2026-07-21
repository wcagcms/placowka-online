@php
    $latestHeartbeat = $heartbeats->first();
    $windowsServices = collect(data_get($latestHeartbeat?->payload, 'windows_services', []));
    $serviceStatusLabels = [
        'Running' => 'Działa',
        'Stopped' => 'Zatrzymana',
        'Paused' => 'Wstrzymana',
        'Start Pending' => 'Uruchamianie',
        'Stop Pending' => 'Zatrzymywanie',
        'Missing' => 'Brak usługi',
        'Unknown' => 'Brak danych',
    ];
@endphp

<section class="card windows-services-card" aria-labelledby="windows-services-title">
    <header class="card-header">
        <div>
            <h2 id="windows-services-title">Usługi Windows</h2>
            <p class="muted">Stan usług skonfigurowanych dla tego agenta.</p>
        </div>
        @if($latestHeartbeat)
            <span class="muted">
                Pomiar:
                {{ ($latestHeartbeat->checked_at ?: $latestHeartbeat->created_at)
                    ->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') }}
            </span>
        @endif
    </header>

    @if($windowsServices->isEmpty())
        <div class="empty">
            Brak danych o usługach Windows. Zainstaluj agenta
            <strong>exe-1.4.0</strong> i zaczekaj na kolejny heartbeat.
        </div>
    @else
        <div class="windows-services-summary" aria-label="Podsumowanie usług">
            <div><strong>{{ $windowsServices->count() }}</strong><span>monitorowanych</span></div>
            <div><strong>{{ $windowsServices->where('healthy', true)->count() }}</strong><span>prawidłowych</span></div>
            <div><strong>{{ $windowsServices->where('healthy', false)->count() }}</strong><span>wymagających uwagi</span></div>
        </div>

        <div class="windows-services-grid">
            @foreach($windowsServices as $service)
                @php
                    $healthy = (bool) data_get($service, 'healthy', false);
                    $exists = (bool) data_get($service, 'exists', false);
                    $status = (string) data_get($service, 'status', 'Unknown');
                    $alertEnabled = (bool) data_get($service, 'alert', false);
                    $cssStatus = $healthy ? 'healthy' : ($alertEnabled ? 'failed' : 'warning');
                @endphp

                <article class="windows-service windows-service--{{ $cssStatus }}">
                    <div class="windows-service__heading">
                        <h3>{{ data_get($service, 'label', data_get($service, 'name', 'Usługa')) }}</h3>
                        <span class="badge {{ $healthy ? 'online' : ($alertEnabled ? 'offline' : 'problem') }}">
                            {{ $serviceStatusLabels[$status] ?? $status }}
                        </span>
                    </div>

                    <dl class="windows-service__details">
                        <div><dt>Nazwa systemowa</dt><dd><code>{{ data_get($service, 'name', '-') }}</code></dd></div>
                        <div><dt>Nazwa Windows</dt><dd>{{ data_get($service, 'display_name', '-') ?: '-' }}</dd></div>
                        <div><dt>Tryb uruchamiania</dt><dd>{{ data_get($service, 'start_type', '-') ?: '-' }}</dd></div>
                        <div><dt>Wymagany stan</dt><dd>{{ data_get($service, 'expected_status', 'Running') === 'Running' ? 'Uruchomiona' : data_get($service, 'expected_status') }}</dd></div>
                        <div><dt>Alert</dt><dd>{{ $alertEnabled ? 'Włączony' : 'Tylko informacja' }}</dd></div>
                    </dl>

                    @if(!$exists)
                        <p class="windows-service__message">Usługa nie została znaleziona na tym komputerze.</p>
                    @elseif(!$healthy)
                        <p class="windows-service__message">Aktualny stan różni się od stanu oczekiwanego.</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>
