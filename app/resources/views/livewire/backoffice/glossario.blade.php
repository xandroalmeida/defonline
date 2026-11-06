<div x-on:glossario-editando.window="$nextTick(() => {
         const campo = $refs.definicao;
         campo.focus({ preventScroll: true });
         campo.setSelectionRange(campo.value.length, campo.value.length);
         window.scrollTo({ top: 0 });
     })">
    <x-page-header :title="'Glossário'"
                   :subtitle="'Termos exibidos ao final do relatório, na tela. A ordem aqui é a ordem do relatório.'" />

    <x-card class="mb-6">
        <form wire:submit="salvar" novalidate class="flex flex-col gap-4">
            <div>
                <x-label for="glossario-termo">Termo</x-label>
                <x-input type="text" id="glossario-termo" wire:model="termo" maxlength="{{ \App\Livewire\Backoffice\Glossario::TERMO_MAX }}" dusk="glossario-termo" class="w-full"/>
                @error('termo')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-glossario-termo">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <x-label for="glossario-definicao">Definição</x-label>
                <textarea id="glossario-definicao" x-ref="definicao" wire:model="definicao" rows="3" maxlength="{{ \App\Livewire\Backoffice\Glossario::DEFINICAO_MAX }}"
                          class="input min-h-24 w-full" dusk="glossario-definicao"></textarea>
                @error('definicao')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-glossario-definicao">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex gap-2">
                <x-button type="submit" variant="primary" dusk="glossario-salvar">{{ $editandoId ? 'Salvar alteração' : 'Adicionar termo' }}</x-button>
                @if ($editandoId)
                    <x-button type="button" variant="secondary" wire:click="limpar" dusk="glossario-cancelar">Cancelar</x-button>
                @endif
            </div>
        </form>
    </x-card>

    @if ($termos->isEmpty())
        <x-card dusk="glossario-vazio">O glossário está vazio e não aparece no relatório.</x-card>
    @else
        <div class="card p-0 relative overflow-x-auto">
            <table class="w-full text-sm text-left" dusk="glossario-lista">
                <thead class="text-[color:var(--color-secondary)]">
                    <tr>
                        <th scope="col" class="px-3 py-2">Termo</th>
                        <th scope="col" class="px-3 py-2">Definição</th>
                        <th scope="col" class="px-3 py-2 text-right"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($termos as $item)
                        <tr class="border-t border-[color:var(--color-border)]" wire:key="termo-{{ $item->id }}" dusk="glossario-linha-{{ $item->id }}">
                            <td class="px-3 py-2 font-medium align-top">{{ $item->termo }}</td>
                            <td class="px-3 py-2 align-top text-[color:var(--color-secondary)]">{{ $item->definicao }}</td>
                            <td class="px-3 py-2 align-top text-right whitespace-nowrap">
                                <x-button type="button" variant="secondary" size="sm" wire:click="mover('{{ $item->id }}', -1)" :disabled="$loop->first" aria-label="Subir {{ $item->termo }}" dusk="glossario-subir-{{ $item->id }}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg></x-button>
                                <x-button type="button" variant="secondary" size="sm" wire:click="mover('{{ $item->id }}', 1)" :disabled="$loop->last" aria-label="Descer {{ $item->termo }}" dusk="glossario-descer-{{ $item->id }}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12l7 7 7-7"/></svg></x-button>
                                <x-button type="button" variant="secondary" size="sm" wire:click="editar('{{ $item->id }}')" dusk="glossario-editar-{{ $item->id }}">Editar</x-button>
                                <x-botao-confirmar titulo="Excluir termo"
                                                   :mensagem="'Excluir “'.$item->termo.'” do glossário? Ele sairá de todos os relatórios.'"
                                                   acao="excluir('{{ $item->id }}')"
                                                   dusk="glossario-excluir-{{ $item->id }}">Excluir</x-botao-confirmar>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
