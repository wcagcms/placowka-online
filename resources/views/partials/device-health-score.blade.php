@php
    $health = $healthScore;
    $healthStatusLabels = [
        'excellent' => 'Bardzo dobry',
        'good' => 'Dobry',
        'warning' => 'Wymaga uwagi',
        'critical' => 'Krytyczny',
        'unknown' => 'Za mało danych',
    ];
    $factorStateLabels = [
        'healthy' => 'Prawidłowo',
        'warning' => 'Ostrzeżenie',
        'critical' => 'Problem',
        'unknown' => 'Brak danych',
    ];
@endphp

<section class="card device-health-score device-health-score--{{ $health['status'] }}"
         aria-labelledby="device-health-score-title">
    <div class="device-health-score__hero">
        <div class="device-health-score__ring"
             role="img"
             aria-label="Ocena zdrowia urządzenia: {{ $health['score'] }} na 100">
            <span>{{ $health['score'] }}</span>
            <small>/ 100</small>
        </div>

        <div class="device-health-score__intro">
            <p class="section-kicker">Zdrowie urządzenia</p>
            <h2 id="device-health-score-title">
                {{ $healthStatusLabels[$health['status']] ?? $health['label'] }}
            </h2>
            <p>{{ $health['summary'] }}</p>

            <div class="device-health-confidence">
                <span>Wiarygodność danych</span>
                <strong>{{ $health['confidence'] }}%</strong>
            </div>

            <div class="device-health-confidence__bar"
                 role="meter"
                 aria-label="Wiarygodność danych"
                 aria-valuemin="0"
                 aria-valuemax="100"
                 aria-valuenow="{{ $health['confidence'] }}">
                <span style="width: {{ $health['confidence'] }}%"></span>
            </div>
        </div>
    </div>

    <div class="device-health-factors" aria-label="Składniki oceny">
        @foreach($health['factors'] as $factor)
            <article class="device-health-factor device-health-factor--{{ $factor['state'] }}">
                <div class="device-health-factor__header">
                    <div>
                        <h3>{{ $factor['label'] }}</h3>
                        <p>{{ $factor['value'] }}</p>
                    </div>

                    <span class="device-health-factor__score">
                        @if($factor['available'])
                            {{ $factor['score'] }}/{{ $factor['max'] }}
                        @else
                            —
                        @endif
                    </span>
                </div>

                <div class="device-health-factor__bar"
                     @if($factor['available'])
                         role="meter"
                         aria-label="{{ $factor['label'] }}"
                         aria-valuemin="0"
                         aria-valuemax="{{ $factor['max'] }}"
                         aria-valuenow="{{ $factor['score'] }}"
                     @endif>
                    <span style="width: {{ $factor['available'] && $factor['max'] > 0
                        ? round(($factor['score'] / $factor['max']) * 100)
                        : 0 }}%"></span>
                </div>

                <div class="device-health-factor__footer">
                    <span class="badge {{ match($factor['state']) {
                        'healthy' => 'online',
                        'warning' => 'problem',
                        'critical' => 'offline',
                        default => 'muted',
                    } }}">
                        {{ $factorStateLabels[$factor['state']] ?? $factor['state'] }}
                    </span>
                    <span>{{ $factor['description'] }}</span>
                </div>
            </article>
        @endforeach
    </div>

    @if($health['recommendations'] !== [])
        <div class="device-health-recommendations">
            <h3>Zalecane działania</h3>
            <ol>
                @foreach($health['recommendations'] as $recommendation)
                    <li>{{ $recommendation }}</li>
                @endforeach
            </ol>
        </div>
    @endif

    <p class="device-health-note">
        Wynik jest wskaźnikiem pomocniczym. Nie zastępuje diagnozy administratora,
        testów producenta sprzętu ani kopii bezpieczeństwa.
    </p>
</section>
