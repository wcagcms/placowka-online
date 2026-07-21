@props(['name', 'size' => 20])

@switch($name)
    @case('home')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 18V9.5L12 4l8 5.5V18a2 2 0 0 1-2 2h-3v-6H9v6H6a2 2 0 0 1-2-2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8.5 10.5h7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        @break
    @case('dashboard')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/></svg>
        @break
    @case('device')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.9"/><path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
        @break
    @case('alarm')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 2.8 19h18.4L12 3Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><path d="M12 9v4M12 16.5v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        @break
    @case('history')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12h4l2-6 4 12 2-6h6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('settings')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.9"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z" stroke="currentColor" stroke-width="1.45" stroke-linejoin="round"/></svg>
        @break
    @case('users')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 20v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.9"/></svg>
        @break
    @case('health')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M17 3h4v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('response')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="m12 12 4-3M7 16h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        @break
    @case('internet')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5a10 10 0 0 1 14 0M8 15.5a6 6 0 0 1 8 0M11 18.5a2 2 0 0 1 2 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="20" r="1" fill="currentColor"/></svg>
        @break
    @case('smart')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><ellipse cx="12" cy="6" rx="8" ry="3" stroke="currentColor" stroke-width="2"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" stroke="currentColor" stroke-width="2"/></svg>
        @break
    @case('windows')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5.5 10 4v7H3V5.5ZM12 3.7 21 2v9h-9V3.7ZM3 13h7v7l-7-1.2V13ZM12 13h9v9l-9-1.7V13Z" fill="currentColor"/></svg>
        @break
    @case('service')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3v5M17 3v5M5 8h14v4a7 7 0 0 1-14 0V8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 19v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        @break
    @case('bell')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><path d="M10 19h4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
        @break
    @case('menu')
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        @break
    @default
        <svg {{ $attributes->class([]) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/></svg>
@endswitch
