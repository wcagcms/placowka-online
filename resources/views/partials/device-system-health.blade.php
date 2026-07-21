@php
    $latestHeartbeat = $heartbeats->first();
    $systemInfo = data_get($latestHeartbeat?->payload, 'system_info', []);
    $cpu = is_array(data_get($systemInfo, 'cpu')) ? data_get($systemInfo, 'cpu') : [];
    $memory = is_array(data_get($systemInfo, 'memory')) ? data_get($systemInfo, 'memory') : [];
    $disks = collect(data_get($systemInfo, 'disks', []))
        ->filter(fn ($disk) => is_array($disk))
        ->values();

    $formatSystemBytes = static function ($bytes): string {
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

        $precision = $index >= 3 ? 1 : 0;

        return number_format($value, $precision, ',', ' ') . ' ' . $units[$index];
    };

    $formatUptime = static function ($seconds): string {
        if (! is_numeric($seconds)) {
            return 'Brak danych';
        }

        $seconds = max(0, (int) $seconds);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];

        if ($days > 0) {
            $parts[] = $days . ' dni';
        }

        if ($hours > 0 || $days > 0) {
            $parts[] = $hours . ' godz.';
        }

        $parts[] = $minutes . ' min';

        return implode(' ', $parts);
    };

    $healthClass = static function ($percent, float $warning, float $critical): string {
        if (! is_numeric($percent)) {
            return 'unknown';
        }

        $value = (float) $percent;

        if ($value >= $critical) {
            return 'critical';
        }

        if ($value >= $warning) {
            return 'warning';
        }

        return 'healthy';
    };

    $cpuClass = $healthClass(data_get($cpu, 'usage_percent'), 75, 90);
    $memoryClass = $healthClass(data_get($memory, 'usage_percent'), 80, 92);
@endphp

<section class="card device-section system-health-section" aria-labelledby="system-health-title">
    <header class="card-header">
        <div>
            <p class="section-kicker">Stan komputera</p>
            <h2 id="system-health-title">CPU, pamięć RAM i dyski</h2>
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

    @if(empty($systemInfo))
        <div class="empty">
            Brak danych o stanie komputera. Zainstaluj agenta
            <strong>exe-1.5.0</strong> i zaczekaj na kolejny heartbeat.
        </div>
    @else
        <div class="system-health-overview">
            <article class="system-health-card system-health-card--{{ $cpuClass }}">
                <div class="system-health-card__header">
                    <h3>Procesor</h3>
                    <span class="system-health-value">
                        {{ is_numeric(data_get($cpu, 'usage_percent'))
                            ? number_format((float) data_get($cpu, 'usage_percent'), 1, ',', ' ') . '%'
                            : 'Brak danych' }}
                    </span>
                </div>

                @if(is_numeric(data_get($cpu, 'usage_percent')))
                    <div class="system-health-meter"
                         role="meter"
                         aria-label="Użycie procesora"
                         aria-valuemin="0"
                         aria-valuemax="100"
                         aria-valuenow="{{ (float) data_get($cpu, 'usage_percent') }}">
                        <span style="width: {{ min(100, max(0, (float) data_get($cpu, 'usage_percent'))) }}%"></span>
                    </div>
                @endif

                <dl class="system-health-details">
                    <div>
                        <dt>Model</dt>
                        <dd>{{ data_get($cpu, 'model', 'Brak danych') ?: 'Brak danych' }}</dd>
                    </div>
                    <div>
                        <dt>Rdzenie</dt>
                        <dd>{{ data_get($cpu, 'cores', 'Brak danych') }}</dd>
                    </div>
                    <div>
                        <dt>Procesory logiczne</dt>
                        <dd>{{ data_get($cpu, 'logical_processors', 'Brak danych') }}</dd>
                    </div>
                </dl>
            </article>

            <article class="system-health-card system-health-card--{{ $memoryClass }}">
                <div class="system-health-card__header">
                    <h3>Pamięć RAM</h3>
                    <span class="system-health-value">
                        {{ is_numeric(data_get($memory, 'usage_percent'))
                            ? number_format((float) data_get($memory, 'usage_percent'), 1, ',', ' ') . '%'
                            : 'Brak danych' }}
                    </span>
                </div>

                @if(is_numeric(data_get($memory, 'usage_percent')))
                    <div class="system-health-meter"
                         role="meter"
                         aria-label="Użycie pamięci RAM"
                         aria-valuemin="0"
                         aria-valuemax="100"
                         aria-valuenow="{{ (float) data_get($memory, 'usage_percent') }}">
                        <span style="width: {{ min(100, max(0, (float) data_get($memory, 'usage_percent'))) }}%"></span>
                    </div>
                @endif

                <dl class="system-health-details">
                    <div>
                        <dt>Wykorzystano</dt>
                        <dd>{{ $formatSystemBytes(data_get($memory, 'used_bytes')) }}</dd>
                    </div>
                    <div>
                        <dt>Wolne</dt>
                        <dd>{{ $formatSystemBytes(data_get($memory, 'free_bytes')) }}</dd>
                    </div>
                    <div>
                        <dt>Łącznie</dt>
                        <dd>{{ $formatSystemBytes(data_get($memory, 'total_bytes')) }}</dd>
                    </div>
                </dl>
            </article>

            <article class="system-health-card system-health-card--neutral">
                <div class="system-health-card__header">
                    <h3>System</h3>
                    <span class="system-health-value system-health-value--small">
                        {{ data_get($systemInfo, 'computer_name', 'Brak danych') ?: 'Brak danych' }}
                    </span>
                </div>

                <dl class="system-health-details">
                    <div>
                        <dt>System Windows</dt>
                        <dd>{{ data_get($systemInfo, 'os_caption', 'Brak danych') ?: 'Brak danych' }}</dd>
                    </div>
                    <div>
                        <dt>Wersja</dt>
                        <dd>{{ data_get($systemInfo, 'os_version', 'Brak danych') ?: 'Brak danych' }}</dd>
                    </div>
                    <div>
                        <dt>Czas działania</dt>
                        <dd>{{ $formatUptime(data_get($systemInfo, 'uptime_seconds')) }}</dd>
                    </div>
                </dl>
            </article>
        </div>

        <div class="system-disks" aria-labelledby="system-disks-title">
            <div class="system-disks__heading">
                <h3 id="system-disks-title">Dyski lokalne</h3>
                <span class="muted">{{ $disks->count() }} wykrytych</span>
            </div>

            @if($disks->isEmpty())
                <div class="empty">Nie wykryto lokalnych dysków lub brak danych.</div>
            @else
                <div class="system-disks-grid">
                    @foreach($disks as $disk)
                        @php
                            $diskUsage = data_get($disk, 'usage_percent');
                            $diskClass = $healthClass($diskUsage, 80, 92);
                            $diskName = data_get($disk, 'name', 'Dysk');
                            $diskLabel = data_get($disk, 'label');
                        @endphp

                        <article class="system-disk-card system-disk-card--{{ $diskClass }}">
                            <div class="system-disk-card__header">
                                <div>
                                    <h4>{{ $diskName }}</h4>
                                    @if($diskLabel)
                                        <p>{{ $diskLabel }}</p>
                                    @endif
                                </div>

                                <strong>
                                    {{ is_numeric($diskUsage)
                                        ? number_format((float) $diskUsage, 1, ',', ' ') . '%'
                                        : 'Brak danych' }}
                                </strong>
                            </div>

                            @if(is_numeric($diskUsage))
                                <div class="system-health-meter"
                                     role="meter"
                                     aria-label="Zajęcie dysku {{ $diskName }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100"
                                     aria-valuenow="{{ (float) $diskUsage }}">
                                    <span style="width: {{ min(100, max(0, (float) $diskUsage)) }}%"></span>
                                </div>
                            @endif

                            <dl class="system-health-details">
                                <div>
                                    <dt>Wolne</dt>
                                    <dd>{{ $formatSystemBytes(data_get($disk, 'free_bytes')) }}</dd>
                                </div>
                                <div>
                                    <dt>Wykorzystano</dt>
                                    <dd>{{ $formatSystemBytes(data_get($disk, 'used_bytes')) }}</dd>
                                </div>
                                <div>
                                    <dt>Pojemność</dt>
                                    <dd>{{ $formatSystemBytes(data_get($disk, 'total_bytes')) }}</dd>
                                </div>
                                <div>
                                    <dt>System plików</dt>
                                    <dd>{{ data_get($disk, 'filesystem', 'Brak danych') ?: 'Brak danych' }}</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</section>
