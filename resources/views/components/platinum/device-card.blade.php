@props(['device'])
<article class="device-row">
    <div class="device-main"><div class="device-icon" aria-hidden="true"><x-platinum.icon name="device" :size="20" /></div><div class="device-title"><strong>{{ data_get($device, 'name', 'Nieznane urządzenie') }}</strong><span>{{ data_get($device, 'details', 'Brak danych') }}</span></div></div>
    <div class="device-cell"><strong class="state {{ data_get($device, 'tone', 'warning') }}">{{ data_get($device, 'status', 'Brak danych') }}</strong><small>{{ data_get($device, 'message', 'Brak opisu') }}</small></div>
    <div class="device-cell"><strong>{{ data_get($device, 'health_score', 0) }}%</strong><small>Health Score</small></div>
    <div class="device-cell"><strong>{{ data_get($device, 'last_seen', '—') }}</strong><small>ostatni sygnał</small></div>
    <a class="metric-link" href="{{ data_get($device, 'url', '#') }}" aria-label="Otwórz urządzenie {{ data_get($device, 'name', '') }}">→</a>
</article>
