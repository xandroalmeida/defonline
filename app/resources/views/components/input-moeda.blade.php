{{--
    <x-input-moeda> — campo de dinheiro em reais, com máscara (RNF-UI-01).
    Todo valor monetário digitado usa este componente. Os demais atributos (wire:model, dusk,
    aria-*) vão para o <input>. O servidor recebe "1.500,00".
--}}
@props(['id', 'name' => null])
<label class="input-affix" for="{{ $id }}">
    <span class="input-affix__symbol" aria-hidden="true">R$</span>
    <input type="text" id="{{ $id }}" @if ($name) name="{{ $name }}" @endif
           inputmode="decimal" autocomplete="off" placeholder="0,00"
           x-data x-mascara:moeda
           {{ $attributes->merge(['class' => 'input-affix__input']) }}>
</label>
