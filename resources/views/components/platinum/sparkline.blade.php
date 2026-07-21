@props([
    'line' => 'M0 16 L180 16',
    'fill' => 'M0 16 L180 16 L180 32 L0 32Z',
    'label' => 'Trend ostatnich pomiarów',
    'state' => 'neutral',
])

<svg {{ $attributes->class(['pdo-sparkline', 'pdo-sparkline--'.$state]) }}
     viewBox="0 0 180 32"
     role="img"
     aria-label="{{ $label }}"
     preserveAspectRatio="none">
    <path class="pdo-sparkline__fill" d="{{ $fill }}"></path>
    <path class="pdo-sparkline__line" d="{{ $line }}"></path>
</svg>
