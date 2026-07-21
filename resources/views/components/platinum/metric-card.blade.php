@props(['metric'])
@php
    $tone = data_get($metric, 'tone', 'default');
    $trendTone = data_get($metric, 'trend_tone', 'neutral');
    $sparkline = data_get($metric, 'sparkline');
@endphp
<article class="metric-card {{ $tone !== 'default' ? 'is-'.$tone : '' }}">
    <div class="metric-top">
        <div class="metric-icon" aria-hidden="true"><x-platinum.icon :name="data_get($metric, 'icon', 'health')" :size="22" /></div>
        <span class="trend {{ $trendTone === 'good' ? 'good' : ($trendTone === 'bad' ? 'bad' : '') }}">{{ data_get($metric, 'trend', '▬ bez zmian') }}</span>
    </div>
    <p class="metric-name">{{ data_get($metric, 'label') }}</p>
    <div class="metric-value">{{ data_get($metric, 'value') }} @if(data_get($metric, 'suffix'))<small>{{ data_get($metric, 'suffix') }}</small>@endif</div>
    @if($sparkline)
        <svg class="sparkline" viewBox="0 0 180 34" preserveAspectRatio="none" role="img" aria-label="{{ data_get($sparkline, 'label', 'Miniwykres trendu') }}">
            <path class="fill" fill="currentColor" d="{{ data_get($sparkline, 'fill') }}" />
            <path class="line" d="{{ data_get($sparkline, 'line') }}" />
        </svg>
    @else
        <div class="metric-footer"><span class="metric-note">{{ data_get($metric, 'note') }}</span><a class="metric-link" href="{{ data_get($metric, 'url', '#') }}" aria-label="{{ data_get($metric, 'aria_label', 'Otwórz szczegóły') }}">→</a></div>
    @endif
</article>
