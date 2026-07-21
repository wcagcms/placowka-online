@php
    $latestHeartbeat = $heartbeats->first();
    $smartInfo = data_get($latestHeartbeat?->payload, 'smart_info', []);
    $smartDisks = collect(data_get($smartInfo, 'disks', []))
        ->filter(fn ($disk) => is_array($disk))
        ->values();

    $formatSmartBytes = static function ($bytes): string {
        if (! is_numeric($bytes)) {
            return 'Brak danych';
        }

        $value = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index >= 3 ? 1 : 0, ',', ' ')
            . ' '
            . $units[$index];
    };

    $smartDiskState = static function (array $disk): array {
        $health = mb_strtolower(trim((string) data_get($disk, 'health_status', '')));
        $predictFailure = (bool) data_get($disk, 'predict_failure', false);
        $supported = (bool) data_get($disk, 'smart_supported', false);
        $temperature = data_get($disk, 'temperature_c');
        $wear = data_get($disk, 'wear_percent_used');

        if (
            $predictFailure
            || (! in_array($health, ['', 'healthy', 'ok', 'unknown'], true))
            || (is_numeric($temperature) && (float) $temperature >= 65)
            || (is_numeric($wear) && (float) $wear >= 95)
        ) {
            return ['critical', 'Krytyczny'];
        }

        if (
            (is_numeric($temperature) && (float) $temperature >= 55)
            || (is_numeric($wear) && (float) $wear >= 80)
        ) {
            return ['warning', 'Ostrzeżenie'];
        }

        if (! $supported) {
            return ['unknown', 'Dane ograniczone'];
        }

        return ['healthy', 'Dobry'];
    };
@endphp

<section class="card device-section smart-section" aria-labelledby="smart-title">
    <header class="card-header">
        <div>
            <p class="section-kicker">Kondycja sprzętu</p>
            <h2 id="smart-title">SMART dysków</h2>
            <p class="muted">
                Stan fizycznych dysków, temperatura i dostępne liczniki niezawodności.
            </p>
        </div>

        @if($latestHeartbeat)
            <span class="muted">
                Pomiar:
                {{ ($latestHeartbeat->checked_at ?: $latestHeartbeat->created_at)
                    ->timezone('Europe/Warsaw')
                    ->format('Y-m-d H:i:s') }}
            </span>
        @endif
    </header>

    @if(empty($smartInfo))
        <div class="empty">
            Brak danych SMART. Zainstaluj agenta
            <strong>exe-1.6.0</strong> i zaczekaj na kolejny heartbeat.
        </div>
    @elseif($smartDisks->isEmpty())
        <div class="empty">
            Windows nie wykrył fizycznych dysków dostępnych dla modułu SMART.
        </div>
    @else
        <div class="smart-summary" aria-label="Podsumowanie SMART">
            <div>
                <strong>{{ $smartDisks->count() }}</strong>
                <span>wykrytych dysków</span>
            </div>
            <div>
                <strong>
                    {{ $smartDisks->filter(function ($disk) use ($smartDiskState) {
                        return $smartDiskState($disk)[0] === 'healthy';
                    })->count() }}
                </strong>
                <span>w dobrym stanie</span>
            </div>
            <div>
                <strong>
                    {{ $smartDisks->filter(function ($disk) use ($smartDiskState) {
                        return in_array(
                            $smartDiskState($disk)[0],
                            ['warning', 'critical'],
                            true
                        );
                    })->count() }}
                </strong>
                <span>wymagających uwagi</span>
            </div>
        </div>

        <div class="smart-disks-grid">
            @foreach($smartDisks as $disk)
                @php
                    [$stateClass, $stateLabel] = $smartDiskState($disk);
                    $temperature = data_get($disk, 'temperature_c');
                    $wearUsed = data_get($disk, 'wear_percent_used');
                    $remainingLife = is_numeric($wearUsed)
                        ? max(0, min(100, 100 - (float) $wearUsed))
                        : null;
                    $operational = collect(data_get($disk, 'operational_status', []))
                        ->filter()
                        ->implode(', ');
                    $name = data_get($disk, 'friendly_name')
                        ?: data_get($disk, 'model')
                        ?: 'Dysk fizyczny';
                @endphp

                <article class="smart-disk-card smart-disk-card--{{ $stateClass }}">
                    <header class="smart-disk-card__header">
                        <div>
                            <h3>{{ $name }}</h3>
                            @if(data_get($disk, 'model') && data_get($disk, 'model') !== $name)
                                <p>{{ data_get($disk, 'model') }}</p>
                            @endif
                        </div>

                        <span class="badge {{ $stateClass === 'healthy' ? 'online' : ($stateClass === 'critical' ? 'offline' : 'problem') }}">
                            {{ $stateLabel }}
                        </span>
                    </header>

                    <div class="smart-metrics">
                        <div>
                            <span>Temperatura</span>
                            <strong>
                                {{ is_numeric($temperature)
                                    ? number_format((float) $temperature, 1, ',', ' ') . '°C'
                                    : 'Brak danych' }}
                            </strong>
                        </div>

                        <div>
                            <span>Żywotność SSD</span>
                            <strong>
                                {{ $remainingLife !== null
                                    ? number_format($remainingLife, 1, ',', ' ') . '%'
                                    : 'Brak danych' }}
                            </strong>
                        </div>

                        <div>
                            <span>Czas pracy</span>
                            <strong>
                                {{ is_numeric(data_get($disk, 'power_on_hours'))
                                    ? number_format((float) data_get($disk, 'power_on_hours'), 0, ',', ' ') . ' godz.'
                                    : 'Brak danych' }}
                            </strong>
                        </div>
                    </div>

                    <dl class="smart-details">
                        <div>
                            <dt>Stan SMART</dt>
                            <dd>{{ data_get($disk, 'health_status', 'Brak danych') ?: 'Brak danych' }}</dd>
                        </div>
                        <div>
                            <dt>Stan operacyjny</dt>
                            <dd>{{ $operational !== '' ? $operational : 'Brak danych' }}</dd>
                        </div>
                        <div>
                            <dt>Typ nośnika</dt>
                            <dd>{{ data_get($disk, 'media_type', 'Brak danych') ?: 'Brak danych' }}</dd>
                        </div>
                        <div>
                            <dt>Magistrala</dt>
                            <dd>{{ data_get($disk, 'bus_type', 'Brak danych') ?: 'Brak danych' }}</dd>
                        </div>
                        <div>
                            <dt>Pojemność</dt>
                            <dd>{{ $formatSmartBytes(data_get($disk, 'size_bytes')) }}</dd>
                        </div>
                        <div>
                            <dt>Odczytane błędy</dt>
                            <dd>{{ is_numeric(data_get($disk, 'read_errors_total'))
                                ? number_format((float) data_get($disk, 'read_errors_total'), 0, ',', ' ')
                                : 'Brak danych' }}</dd>
                        </div>
                        <div>
                            <dt>Błędy zapisu</dt>
                            <dd>{{ is_numeric(data_get($disk, 'write_errors_total'))
                                ? number_format((float) data_get($disk, 'write_errors_total'), 0, ',', ' ')
                                : 'Brak danych' }}</dd>
                        </div>
                        <div>
                            <dt>Obsługa liczników</dt>
                            <dd>{{ data_get($disk, 'smart_supported', false)
                                ? 'Dostępna'
                                : 'Ograniczona lub niedostępna' }}</dd>
                        </div>
                    </dl>

                    <p class="smart-status-message">
                        {{ data_get($disk, 'status_message', 'Brak dodatkowych informacji.') }}
                    </p>
                </article>
            @endforeach
        </div>

        <p class="smart-disclaimer">
            Brak temperatury, żywotności lub liczników błędów może wynikać ze
            sterownika, kontrolera RAID, połączenia USB albo ograniczeń sprzętu.
            Sam brak tych danych nie oznacza awarii.
        </p>
    @endif
</section>
