@props([
    'value' => null,
    'label' => 'Wskaźnik',
    'state' => 'unknown',
    'suffix' => '%',
    'size' => 'normal',
])

@php
    $numericValue = is_numeric($value) ? max(0, min(100, (float) $value)) : null;
    $circumference = 289.03;
    $offset = $numericValue !== null
        ? $circumference - (($numericValue / 100) * $circumference)
        : $circumference;
    $displayValue = $numericValue !== null
        ? number_format($numericValue, $numericValue == floor($numericValue) ? 0 : 1, ',', ' ')
        : '—';
@endphp

<div {{ $attributes->class([
        'pdo-gauge',
        'pdo-gauge--'.$state,
        'pdo-gauge--compact' => $size === 'compact',
    ]) }}
     role="img"
     aria-label="{{ $label }}: {{ $numericValue !== null ? $displayValue.$suffix : 'brak danych' }}">
    <svg class="pdo-gauge__svg" viewBox="0 0 112 112" aria-hidden="true" focusable="false">
        <circle class="pdo-gauge__track" cx="56" cy="56" r="46"></circle>
        <circle class="pdo-gauge__value"
                cx="56"
                cy="56"
                r="46"
                stroke-dasharray="{{ number_format($circumference, 2, '.', '') }}"
                stroke-dashoffset="{{ number_format($offset, 2, '.', '') }}"></circle>
    </svg>
    <span class="pdo-gauge__content">
        <strong>{{ $displayValue }}</strong>
        @if($numericValue !== null)
            <small>{{ $suffix }}</small>
        @endif
    </span>
</div>
