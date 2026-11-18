@props([
    'rotulo' => 'Mais ações',
])

{{--
    Menu de ações de linha ("⋯"). Para tabelas em que os botões não cabem lado a lado.

    O painel vai para o <body> (x-teleport) com posição fixa calculada a partir do botão:
    dentro da tabela ele seria cortado pelo `overflow-x-auto` do card. O teleport mantém
    o vínculo com o componente Livewire, então `wire:click` nos itens funciona.

    Itens: <x-menu-acoes.item>. Fecha ao escolher, ao clicar fora, com Esc, ao rolar
    ou redimensionar. Setas ↑/↓ navegam entre os itens.
--}}
<div class="inline-flex" x-data="menuAcoes">
    <button type="button" x-ref="gatilho"
            {{ $attributes->merge(['class' => 'btn btn--secondary btn--sm px-2.5!']) }}
            @click="alternar()"
            :aria-expanded="aberto ? 'true' : 'false'"
            aria-haspopup="menu"
            aria-label="{{ $rotulo }}"
            title="{{ $rotulo }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/>
        </svg>
    </button>

    <template x-teleport="body">
        <div x-show="aberto" x-cloak x-ref="painel"
             role="menu"
             :style="estilo"
             @click.outside="if (! $refs.gatilho.contains($event.target)) fechar()"
             @click="if ($event.target.closest('[role=menuitem]')) fechar()"
             @keydown.escape.prevent.stop="fechar(true)"
             @keydown.arrow-down.prevent="mover(1)"
             @keydown.arrow-up.prevent="mover(-1)"
             @keydown.tab="fechar()"
             class="fixed z-50 min-w-44 py-1 bg-[color:var(--color-surface)] border border-[color:var(--color-border)] rounded-lg shadow-md text-left">
            {{ $slot }}
        </div>
    </template>
</div>
