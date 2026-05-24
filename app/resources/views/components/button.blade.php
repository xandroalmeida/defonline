@props([
    'variant' => 'primary',
    'size' => 'md',
    'as' => 'button',
    'type' => 'submit',
    'href' => null,
    'disabled' => false,
])

@php
    $tag = $href ? 'a' : $as;
    $classes = 'btn btn--' . $variant;
    if ($size === 'sm') {
        $classes .= ' btn--sm';
    }
@endphp

@if ($tag === 'a')
    <a href="{{ $disabled ? '#' : $href }}"
       @if ($disabled) aria-disabled="true" tabindex="-1" @endif
       {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}"
            @disabled($disabled)
            @if ($disabled) aria-disabled="true" @endif
            {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
