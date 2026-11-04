<div>
    <x-page-header :title="'Recomendações'"
                   :subtitle="'Texto exibido no relatório para cada indicador, segmento e faixa. Vale para os próximos diagnósticos; os já emitidos mantêm o texto da época.'" />

    <x-card class="mb-6">
        <div class="flex flex-col md:flex-row md:items-end gap-4">
            <div class="md:w-48">
                <x-label for="recomendacoes-setor">Segmento</x-label>
                <x-select id="recomendacoes-setor" wire:model.live="setor" dusk="recomendacoes-setor" :disabled="$editandoId !== null">
                    @foreach ($setores as $codigo => $nome)
                        <option value="{{ $codigo }}">{{ $nome }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="md:w-80">
                <x-label for="recomendacoes-indicador">Indicador</x-label>
                <x-select id="recomendacoes-indicador" wire:model.live="indicador" dusk="recomendacoes-indicador" :disabled="$editandoId !== null">
                    @foreach ($indicadores as $codigo => $nome)
                        <option value="{{ $codigo }}">{{ $nome }}</option>
                    @endforeach
                </x-select>
            </div>
            <p class="text-sm text-[color:var(--color-secondary)] m-0 md:ml-auto" dusk="recomendacoes-versao">Matriz vigente: {{ $versao }}</p>
        </div>
        @if ($setor !== 'industria')
            <p class="text-sm text-[color:var(--color-secondary)] mt-4 mb-0" dusk="recomendacoes-aviso-segmento">
                {{ $setores[$setor] }} ainda não gera diagnóstico. Estes textos só passam a aparecer quando o segmento for liberado.
            </p>
        @endif
    </x-card>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach ($recomendacoes as $item)
            <x-card wire:key="recomendacao-{{ $item->id }}" class="flex flex-col gap-3" dusk="recomendacao-{{ $item->farol }}">
                <div class="flex items-center justify-between gap-2">
                    <x-relatorio.farol :cor="$item->farol" />
                    <span class="text-sm text-[color:var(--color-secondary)]" dusk="recomendacao-faixa-{{ $item->farol }}">{{ $item->faixa }}</span>
                </div>

                @if ($editandoId === $item->id)
                    <form wire:submit="salvar" novalidate class="flex flex-col gap-3">
                        <div>
                            <x-label for="recomendacao-texto">Texto</x-label>
                            <textarea id="recomendacao-texto" x-init="$el.focus()" wire:model="texto" rows="12" maxlength="{{ \App\Livewire\Backoffice\Recomendacoes::TEXTO_MAX }}"
                                      class="input min-h-24 w-full" dusk="recomendacao-texto"></textarea>
                            <p class="text-xs text-[color:var(--color-secondary)] mt-1 mb-0"><span x-text="$wire.texto.length"></span>/{{ \App\Livewire\Backoffice\Recomendacoes::TEXTO_MAX }}</p>
                            @error('texto')
                                <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-recomendacao-texto">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex gap-2">
                            <x-button type="submit" variant="primary" size="sm" dusk="recomendacao-salvar">Salvar</x-button>
                            <x-button type="button" variant="secondary" size="sm" wire:click="limpar" dusk="recomendacao-cancelar">Cancelar</x-button>
                        </div>
                    </form>
                @else
                    <p class="text-sm m-0 grow" dusk="recomendacao-texto-{{ $item->farol }}">{{ $item->texto }}</p>
                    <div class="flex items-center gap-2">
                        <x-button type="button" variant="secondary" size="sm" wire:click="editar('{{ $item->id }}')" :disabled="$editandoId !== null" dusk="recomendacao-editar-{{ $item->farol }}">Editar</x-button>
                        @if ($salvoId === $item->id)
                            <span class="text-sm text-[color:var(--color-secondary)]" role="status" dusk="recomendacao-salva">Salvo.</span>
                        @endif
                    </div>
                @endif
            </x-card>
        @endforeach
    </div>
</div>
