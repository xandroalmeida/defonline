@props([
    'for' => null,
])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'label']) }}>
    {{ $slot }}
</label>
