@props(['incident'])
<article class="incident {{ data_get($incident, 'tone') === 'warning' ? 'warning' : '' }}">
    <div class="incident-icon" aria-hidden="true">{{ data_get($incident, 'symbol', '!') }}</div>
    <div><strong>{{ data_get($incident, 'title', 'Incydent') }}</strong><p>{{ data_get($incident, 'description', 'Brak dodatkowych informacji.') }}</p><div class="incident-meta"><span>Priorytet: {{ data_get($incident, 'priority', 'średni') }}</span><span>{{ data_get($incident, 'duration', '') }}</span></div></div>
</article>
