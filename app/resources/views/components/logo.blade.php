@props([
    'size' => 32,
    'variant' => 'default',
    'wordmark' => false,
])

@php
    $isDark = $variant === 'dark';
    // Tokens via CSS vars — `on-primary` é o branco do design-system (usado sobre Tertiary).
    $primaryFill = $isDark ? 'var(--color-on-primary)' : 'var(--color-primary)';
    $accentFill = 'var(--color-tertiary)';
    $textColor = $isDark ? 'text-[color:var(--color-on-primary)]' : 'text-[color:var(--color-primary)]';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"
         width="{{ $size }}" height="{{ $size }}"
         role="img" aria-label="DEFOnline">
        <title>DEFOnline</title>
        <path fill="{{ $primaryFill }}" fill-rule="evenodd"
              d="M4 4 H15 A13 13 0 0 1 15 28 H4 Z M9 9 V23 H15 A9 9 0 0 0 15 9 Z"/>
        <circle cx="26" cy="25" r="2" fill="{{ $accentFill }}"/>
    </svg>
    @if ($wordmark)
        <span class="{{ $textColor }} font-medium text-base tracking-tight {{ $wordmark === 'mobile' ? 'hidden min-[480px]:inline' : '' }}">
            DEFOnline
        </span>
    @endif
</span>
