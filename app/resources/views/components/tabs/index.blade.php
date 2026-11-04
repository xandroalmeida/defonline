{{--
    Abas horizontais. Se não couberem na largura, rolam de lado, sem quebrar linha.
    Setas do teclado movem o foco; Enter ou espaço ativam a aba.
    `acompanhar`: expressão Alpine que muda junto com a aba ativa, para mantê-la à vista.
--}}
@props(['rotulo', 'acompanhar' => null])

<div role="tablist" aria-label="{{ $rotulo }}"
     x-data="{
         mover(passo) {
             const abas = [...$el.querySelectorAll('[role=tab]')];
             const destino = abas[(abas.indexOf(document.activeElement) + passo + abas.length) % abas.length];
             destino.focus();
         },
     }"
     @if ($acompanhar)
         x-effect="{{ $acompanhar }}; $nextTick(() => $el.querySelector('[aria-selected=true]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' }))"
     @endif
     x-on:keydown.arrow-right.prevent="mover(1)"
     x-on:keydown.arrow-left.prevent="mover(-1)"
     {{ $attributes->merge(['class' => 'tabs']) }}>
    {{ $slot }}
</div>
