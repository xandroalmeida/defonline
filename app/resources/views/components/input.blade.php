@props([
    'type' => 'text',
    'name' => null,
    'id' => null,
])

@php
    $inputId = $id ?? $name;
@endphp

<input type="{{ $type }}"
       @if ($name) name="{{ $name }}" @endif
       @if ($inputId) id="{{ $inputId }}" @endif
       {{ $attributes->merge(['class' => 'input']) }}>
