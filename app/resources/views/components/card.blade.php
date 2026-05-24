@props([
    'as' => 'div',
])

<{{ $as }} {{ $attributes->merge(['class' => 'card']) }}>
    {{ $slot }}
</{{ $as }}>
