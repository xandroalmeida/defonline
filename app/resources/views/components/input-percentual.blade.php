{{--
    <x-input-percentual> — campo de percentual, com máscara (RNF-UI-01).
    Todo percentual digitado usa este componente. `decimais`: casas após a vírgula (padrão 2; 0 para
    inteiro). Os demais atributos (wire:model, dusk, aria-*) vão para o <input>.
--}}
@props(['id', 'name' => null, 'decimais' => 2])
@php($decimais = (int) $decimais)
<label class="input-affix" for="{{ $id }}">
    <input type="text" id="{{ $id }}" @if ($name) name="{{ $name }}" @endif
           inputmode="{{ $decimais === 0 ? 'numeric' : 'decimal' }}" autocomplete="off"
           placeholder="{{ $decimais === 0 ? '0' : '0,'.str_repeat('0', $decimais) }}"
           x-data x-mascara:percentual="{{ $decimais }}"
           {{ $attributes->merge(['class' => 'input-affix__input']) }}>
    <span class="input-affix__symbol" aria-hidden="true">%</span>
</label>
