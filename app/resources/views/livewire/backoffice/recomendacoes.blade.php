@php
    $codigos = array_keys($indicadores);
    $posicao = array_search($indicador, $codigos, true);
    $anterior = $codigos[$posicao - 1] ?? null;
    $proximo = $codigos[$posicao + 1] ?? null;
    $max = \App\Livewire\Backoffice\Recomendacoes::TEXTO_MAX;
    $avisoSaida = 'Há alterações não salvas. Sair e perdê-las?';
    $ncg = $indicador === \App\Livewire\Backoffice\Recomendacoes::NCG_ABSOLUTO;
@endphp

<div x-data
     data-pendentes="{{ $alterados === [] ? '0' : '1' }}"
     x-on:beforeunload.window="if ($root.dataset.pendentes === '1') $event.preventDefault()"
     x-on:livewire:navigate.document="if ($root.dataset.pendentes === '1' && ! confirm(@js($avisoSaida))) $event.preventDefault()"
     x-on:keydown.window.ctrl.s.prevent="document.activeElement?.blur(); $wire.salvar()"
     x-on:keydown.window.cmd.s.prevent="document.activeElement?.blur(); $wire.salvar()">
    <x-page-header :title="'Recomendações'"
                   :subtitle="'Texto do relatório por indicador, faixa e segmento. Vale para os próximos diagnósticos; os já emitidos mantêm o texto da época.'" />

    <x-tabs rotulo="Indicadores" acompanhar="$wire.indicador" class="mb-6" dusk="recomendacoes-indicadores">
        @foreach ($indicadores as $codigo => $nome)
            <x-tabs.item :ativa="$codigo === $indicador"
                         :marcada="in_array($codigo, $indicadoresPendentes, true)"
                         marca-rotulo="com alterações não salvas"
                         id="aba-{{ $codigo }}"
                         title="{{ $nome }}"
                         wire:click="irPara('{{ $codigo }}')"
                         wire:key="aba-{{ $codigo }}"
                         dusk="recomendacoes-ir-{{ $codigo }}">{{ $rotulosAbas[$codigo] }}</x-tabs.item>
        @endforeach
    </x-tabs>

    <div role="tabpanel" aria-labelledby="aba-{{ $indicador }}">
    <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-4" dusk="recomendacoes-titulo">{{ $indicadores[$indicador] }}</h2>

    {{-- Um card por segmento. O subgrid mantém cada faixa na mesma linha entre os cards. --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-5">
        @foreach ($setores as $setor => $nomeSetor)
            <x-card as="section" aria-label="{{ $nomeSetor }}" wire:key="setor-{{ $setor }}"
                    class="grid gap-y-5 md:[grid-template-rows:subgrid] {{ $ncg ? 'md:[grid-row:span_3]' : 'md:[grid-row:span_4]' }}"
                    dusk="recomendacoes-setor-{{ $setor }}">
                <header class="border-b border-[color:var(--color-border)] pb-3">
                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="text-base font-medium text-[color:var(--color-primary)] m-0">{{ $nomeSetor }}</h3>
                        <span class="text-xs text-[color:var(--color-secondary)]" dusk="recomendacoes-versao-{{ $setor }}">Matriz {{ $versoes[$setor] }}</span>
                    </div>
                </header>

                @foreach ($situacoes as $farol)
                    @php
                        $chave = "{$setor}.{$indicador}.{$farol}";
                        $alterado = in_array($chave, $alterados, true);
                        $campo = "recomendacao-{$setor}-{$farol}";
                    @endphp
                    <div wire:key="celula-{{ $chave }}" class="flex flex-col">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            @if ($ncg)
                                <span class="text-sm font-medium" dusk="{{ $campo }}-situacao">{{ \App\Livewire\Backoffice\Recomendacoes::SITUACOES_NCG[$farol] }}</span>
                            @else
                                <x-relatorio.farol :cor="$farol" />
                            @endif
                            <span class="text-sm text-[color:var(--color-secondary)]" dusk="{{ $campo }}-faixa">{{ $faixas["{$setor}.{$farol}"] ?? '' }}</span>
                        </div>
                        <textarea id="{{ $campo }}" wire:model.live.blur="textos.{{ $chave }}" rows="4" maxlength="{{ $max }}"
                                  aria-label="{{ $nomeSetor }}, {{ $ncg ? 'NCG '.\App\Livewire\Backoffice\Recomendacoes::SITUACOES_NCG[$farol] : "farol {$farol}" }}, faixa {{ $faixas["{$setor}.{$farol}"] ?? '' }}"
                                  @class(['input w-full grow text-sm [field-sizing:content]', '!border-[color:var(--color-tertiary)] !border-2' => $alterado])
                                  dusk="{{ $campo }}"></textarea>
                        <div class="flex items-center justify-between gap-2 mt-1 text-xs text-[color:var(--color-secondary)]">
                            <span><span x-text="$wire.textos['{{ $setor }}']['{{ $indicador }}']['{{ $farol }}']?.length ?? 0"></span>/{{ $max }}</span>
                            @if ($alterado)
                                <span>Alterado · <button type="button" class="link text-xs" wire:click="desfazer('{{ $setor }}', '{{ $indicador }}', '{{ $farol }}')" dusk="{{ $campo }}-desfazer">Desfazer</button></span>
                            @endif
                        </div>
                        @error("textos.{$chave}")
                            <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="{{ $campo }}-erro">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </x-card>
        @endforeach
    </div>

    <div class="flex justify-between gap-2 mt-4">
        <div>
            @if ($anterior)
                <x-button type="button" variant="secondary" size="sm" wire:click="irPara('{{ $anterior }}')" dusk="recomendacoes-anterior"><svg class="inline-block" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> {{ $indicadores[$anterior] }}</x-button>
            @endif
        </div>
        <div>
            @if ($proximo)
                <x-button type="button" variant="secondary" size="sm" wire:click="irPara('{{ $proximo }}')" dusk="recomendacoes-proximo">{{ $indicadores[$proximo] }} <svg class="inline-block" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></x-button>
            @endif
        </div>
    </div>

    </div>

    @if ($alterados !== [])
        <div class="card sticky bottom-4 mt-6 flex flex-wrap items-center justify-between gap-3 !py-3" dusk="recomendacoes-barra">
            <span class="text-sm font-medium" role="status" dusk="recomendacoes-pendentes">
                {{ count($alterados) === 1 ? '1 alteração não salva' : count($alterados).' alterações não salvas' }}
            </span>
            <div class="flex gap-2">
                <x-botao-confirmar titulo="Descartar alterações"
                                   mensagem="Descartar todas as alterações não salvas?"
                                   acao="descartar()"
                                   dusk="recomendacoes-descartar">Descartar</x-botao-confirmar>
                <x-button type="button" size="sm" wire:click="salvar" dusk="recomendacoes-salvar">Salvar alterações</x-button>
            </div>
        </div>
    @elseif ($mensagem)
        <p class="text-sm mt-4 mb-0" role="status" dusk="recomendacoes-mensagem">{{ $mensagem }}</p>
    @endif
</div>
