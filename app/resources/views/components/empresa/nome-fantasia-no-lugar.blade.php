{{--
    Nome fantasia editado no próprio lugar, na ficha da empresa (PDR-017). Usado
    na tela da empresa e no cadastro: o componente Livewire informa a propriedade
    do campo e as ações de editar, salvar e cancelar.
--}}
@props([
    'editando',
    'modelo',
    'nome' => null,
    'nomeDaReceita' => null,
    'textoVazio' => '—',
    'rotulo' => 'nome fantasia',
    'rotuloSalvar' => 'Salvar',
    'acaoEditar',
    'acaoSalvar',
    'acaoCancelar',
    'prefixoDusk',
    'salvo' => false,
])

<div>
    @if ($editando)
        <form wire:submit="{{ $acaoSalvar }}" novalidate class="flex flex-col gap-2">
            <x-input type="text" id="nome_fantasia" wire:model="{{ $modelo }}" maxlength="255"
                     aria-label="{{ ucfirst($rotulo) }}"
                     x-data x-init="$el.focus(); $el.select()"
                     wire:keydown.escape="{{ $acaoCancelar }}"
                     dusk="{{ $prefixoDusk }}-nome-fantasia-input"/>
            @error($modelo)
                <p class="text-[color:var(--color-destructive)] text-sm m-0" dusk="erro-nome-fantasia">{{ $message }}</p>
            @enderror
            <div class="flex items-center gap-2">
                <x-button type="submit" variant="primary" size="sm" dusk="{{ $prefixoDusk }}-salvar-nome-fantasia">{{ $rotuloSalvar }}</x-button>
                <x-button type="button" variant="secondary" size="sm" wire:click="{{ $acaoCancelar }}" dusk="{{ $prefixoDusk }}-cancelar-nome-fantasia">Cancelar</x-button>
            </div>
        </form>
    @else
        <div class="flex flex-wrap items-center gap-2">
            <span dusk="{{ $prefixoDusk }}-nome-fantasia-valor">{{ $nome ?: $textoVazio }}</span>
            <button type="button" wire:click="{{ $acaoEditar }}" aria-label="Editar {{ $rotulo }}" title="Editar {{ $rotulo }}"
                    class="inline-flex items-center text-[color:var(--color-tertiary)]"
                    dusk="{{ $prefixoDusk }}-editar-nome-fantasia">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 20h9"/>
                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                </svg>
            </button>
            @if ($salvo)
                <span class="text-sm text-[color:var(--color-secondary)]" role="status" dusk="{{ $prefixoDusk }}-nome-fantasia-salvo">Nome salvo.</span>
            @endif
        </div>
        @if ($nomeDaReceita)
            <p class="text-sm text-[color:var(--color-secondary)] m-0" dusk="{{ $prefixoDusk }}-nome-na-receita">Na Receita: {{ $nomeDaReceita }}</p>
        @endif
    @endif
</div>
