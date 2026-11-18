@props([
    'href' => null,
    'perigo' => false,
])

@php
    $classes = 'block w-full text-left px-4 py-2 text-sm whitespace-nowrap no-underline hover:bg-[color:var(--color-neutral)] focus:bg-[color:var(--color-neutral)] focus:outline-none '
        .($perigo ? 'text-[color:var(--color-destructive)]' : 'text-[color:var(--color-primary)]');
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" tabindex="-1" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="button" role="menuitem" tabindex="-1" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
