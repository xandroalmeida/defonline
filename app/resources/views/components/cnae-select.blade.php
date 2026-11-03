{{--
    Combobox de CNAE com busca por código ou nome (lista do IBGE carregada sob
    demanda de /cnaes). Uso: <x-cnae-select id="cnae" wire:model="cnae" dusk="empresa-cnae"/>
    O valor sincronizado com o Livewire é o código de 7 dígitos (ou '').
--}}
@props([
    'id' => 'cnae',
    'valorInicial' => '',
])

@php
    $modelo = $attributes->wire('model')->value();
    $descricao = $valorInicial !== '' ? \App\Domain\Cnae::descricao($valorInicial) : null;
    $rotuloInicial = $descricao !== null ? \App\Domain\Cnae::formatar($valorInicial).' — '.$descricao : '';
    $dusk = $attributes->get('dusk', 'cnae');
@endphp

<div wire:ignore
     x-data="cnaeSelect({ valor: $wire.entangle(@js($modelo)), url: @js(route('cnaes.index')), rotuloInicial: @js($rotuloInicial) })"
     x-on:click.outside="fechar()"
     x-on:keydown.escape.prevent.stop="fechar()"
     class="relative">
    <div class="relative">
        <input type="text" id="{{ $id }}" x-ref="campo"
               class="input pr-10"
               role="combobox" autocomplete="off"
               aria-autocomplete="list" aria-controls="{{ $id }}-lista"
               x-bind:aria-expanded="aberto"
               placeholder="Busque pelo código ou pela atividade"
               x-bind:value="textoCampo"
               x-on:focus="abrir()"
               x-on:click="abrir()"
               x-on:input="filtrar($event.target.value)"
               x-on:keydown.arrow-down.prevent="mover(1)"
               x-on:keydown.arrow-up.prevent="mover(-1)"
               x-on:keydown.enter.prevent="confirmarAtivo()"
               x-on:keydown.tab="fechar()"
               dusk="{{ $dusk }}">
        <button type="button" x-show="valor && !aberto" x-cloak x-on:click="limpar()"
                class="absolute inset-y-0 right-0 px-3 text-[color:var(--color-secondary)] hover:text-[color:var(--color-primary)]"
                aria-label="Limpar CNAE" dusk="{{ $dusk }}-limpar">&times;</button>
    </div>

    <div x-show="aberto" x-cloak
         class="absolute z-20 mt-1 w-full rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-surface)] shadow-lg">
        <p x-show="carregando" class="m-0 px-3 py-2 text-sm text-[color:var(--color-secondary)]">Carregando lista de CNAEs…</p>
        <p x-show="erro" class="m-0 px-3 py-2 text-sm text-[color:var(--color-destructive)]">
            Não foi possível carregar a lista.
            <button type="button" class="underline font-semibold" x-on:click="aberto = false; abrir()">Tentar de novo</button>
        </p>
        <p x-show="itens && filtrados.length === 0" class="m-0 px-3 py-2 text-sm text-[color:var(--color-secondary)]"
           dusk="{{ $dusk }}-vazio">Nenhum CNAE encontrado.</p>

        <ul id="{{ $id }}-lista" role="listbox" x-ref="lista" x-show="itens && filtrados.length > 0"
            x-on:scroll.passive="maisAoRolar($event)"
            class="m-0 p-0 list-none max-h-72 overflow-y-auto">
            <template x-for="(item, indice) in visiveis" :key="item.codigo">
                <li role="option" x-bind:data-indice="indice" x-bind:aria-selected="item.codigo === valor"
                    x-bind:dusk="@js($dusk.'-opcao-').concat(item.codigo)"
                    x-on:mousedown.prevent="escolher(item)"
                    x-on:mouseenter="ativo = indice"
                    x-bind:class="indice === ativo ? 'bg-[color:var(--color-neutral)]' : ''"
                    class="px-3 py-2 text-sm cursor-pointer border-b border-[color:var(--color-border)] last:border-b-0">
                    <span class="font-mono font-semibold text-[color:var(--color-primary)]" x-text="item.formatado"></span>
                    <span class="text-[color:var(--color-secondary)]" x-text="item.descricao"></span>
                </li>
            </template>
        </ul>
        <p x-show="itens && filtrados.length > visiveis.length" class="m-0 px-3 py-1 text-xs text-[color:var(--color-secondary)] border-t border-[color:var(--color-border)]"
           x-text="`Mostrando ${visiveis.length} de ${filtrados.length} — role ou refine a busca`"></p>
    </div>
</div>
