@php
    use App\Domain\Drm\Dinheiro;
    use App\Domain\Drm\Grupo;
    use App\Domain\Drm\Lancamento as LancamentoDrm;

    $empresaNome = $empresa->nomeExibicao();
    $nomeBloco = $nomesDosBlocos[$bloco_atual];
    $totalRepetidos = count($repetidos);
    $temErros = $errors->any();
    $ultimoBloco = $bloco_atual === \App\Livewire\Drm\Lancamento::BLOCOS;
    $extras = ['competencia' => $competenciaObj?->rotulo() ?? '', 'empresa' => $empresaNome];
    $detalhes = fn (string $campo): string => (string) $conteudo->rotuloCampo($campo);
@endphp

<div x-data
     x-on:focar-bloco.window="$nextTick(() => document.getElementById('drm-bloco-titulo')?.focus())"
     x-on:focar-campo.window="$nextTick(() => document.getElementById('campo-' + $event.detail.campo)?.focus())">

    <x-page-header :title="($retificando ? 'Corrigir ' : 'Lançar ').($competenciaObj?->rotulo() ?? '')"
                   :subtitle="$empresaNome.' · '.match ($setor) { \App\Domain\Drm\Setor::Industria => 'Indústria', \App\Domain\Drm\Setor::Comercio => 'Comércio', \App\Domain\Drm\Setor::Servicos => 'Serviços' }"/>

    @if ($retomando_rascunho)
        <x-drm.aviso-inline variant="info" dusk="drm-lancamento-retomando">
            Retomando seu rascunho — você parou em {{ $nomeBloco }}.
        </x-drm.aviso-inline>
    @elseif ($rascunho_expirou)
        <x-drm.aviso-inline variant="info" dusk="drm-lancamento-rascunho-expirou">
            Seu rascunho expirou. Vamos começar do início.
        </x-drm.aviso-inline>
    @endif

    <x-drm.progresso-blocos :atual="$bloco_atual" :total="\App\Livewire\Drm\Lancamento::BLOCOS" :nome="$nomeBloco"/>

    <x-card>
        <form wire:submit="{{ $ultimoBloco ? 'fechar' : 'proximo' }}" novalidate class="flex flex-col gap-4">
            <fieldset class="m-0 p-0 border-0 min-w-0" dusk="drm-lancamento-bloco-{{ $bloco_atual }}">
                <legend class="p-0 mb-4">
                    <h2 id="drm-bloco-titulo" tabindex="-1" class="text-lg font-medium m-0 text-[color:var(--color-primary)] focus:outline-none">{{ $nomeBloco }}</h2>
                </legend>

                {{-- ============================ BLOCO 1 ============================ --}}
                @if ($bloco_atual === 1)
                    <div class="flex flex-col gap-4">
                        <div>
                            <div class="flex items-center gap-1.5 mb-1">
                                <x-label for="campo-D00" class="mb-0">{{ $detalhes('D00') }}</x-label>
                                <x-help id="D00" :text="$conteudo->help('D00')" :label="$detalhes('D00')"/>
                            </div>
                            @if ($retificando)
                                {{-- A competência retificada não se troca: é o mês daquela versão vigente. --}}
                                <p id="campo-D00" class="m-0 min-h-[44px] flex items-center font-medium text-[color:var(--color-primary)]" dusk="drm-lancamento-campo-D00">{{ $competenciaObj?->rotulo() }}</p>
                            @else
                                <x-select id="campo-D00" wire:model.live="competencia" dusk="drm-lancamento-campo-D00">
                                    @if ($competenciaObj && ! collect($mesesDisponiveis)->contains(fn ($m) => $m->texto() === $competencia))
                                        <option value="{{ $competencia }}">{{ $competenciaObj->rotulo() }}</option>
                                    @endif
                                    @foreach ($mesesDisponiveis as $mes)
                                        <option value="{{ $mes->texto() }}">{{ $mes->rotulo() }}</option>
                                    @endforeach
                                </x-select>
                            @endif
                        </div>

                        @foreach ($bloqueios as $bloqueio)
                            @if ($bloqueio->codigo === 'B2')
                                @php $m = $mensagens->de($bloqueio, $extras); @endphp
                                <x-drm.aviso-inline variant="bloqueio" :titulo="$m['titulo']" dusk="drm-lancamento-bloqueio-B2">
                                    <p class="m-0">{{ $m['texto'] }}</p>
                                </x-drm.aviso-inline>
                            @endif
                        @endforeach

                        @if ($retificando)
                            {{-- Spec §4.9: aviso permanente; B1 não se aplica; os valores são os daquele mês, sem marca de herança. --}}
                            <x-drm.aviso-inline variant="info" dusk="drm-lancamento-retificacao-aviso">
                                Você está corrigindo um mês já fechado. A versão anterior fica guardada.
                            </x-drm.aviso-inline>
                        @elseif ($existente)
                            @php $m = $mensagens->de(new \App\Domain\Drm\Ocorrencia('B1'), $extras); @endphp
                            <x-drm.aviso-inline variant="bloqueio" dusk="drm-lancamento-bloqueio-B1">
                                <p class="m-0 mb-3">Você já lançou {{ $existente->rotulo() }} para esta empresa.</p>
                                <div class="flex flex-wrap gap-3">
                                    <x-button :href="route('drm.mes', [$empresa, $existente->texto()])" variant="secondary"
                                              dusk="drm-lancamento-b1-abrir">Abrir {{ $existente->rotulo() }}</x-button>
                                    <x-button :href="route('drm.corrigir', [$empresa, $existente->texto()])" variant="secondary"
                                              dusk="drm-lancamento-b1-corrigir">Corrigir {{ $existente->rotulo() }}</x-button>
                                </div>
                            </x-drm.aviso-inline>
                        @elseif ($totalRepetidos > 0 && $origem && ! $oferta_dispensada)
                            <x-drm.aviso-inline variant="info" dusk="drm-lancamento-conferir-tudo">
                                <p class="m-0 mb-3">
                                    {{ ucfirst($origem->rotulo()) }} está inteiro aqui — {{ $totalRepetidos }} valores repetidos.
                                    Se nada mudou, dá para conferir tudo de uma vez e ir direto ao fechamento.
                                </p>
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <x-button type="button" variant="primary" wire:click="conferirTudo">Conferir tudo e ir ao fechamento</x-button>
                                    <x-button type="button" variant="secondary" wire:click="dispensarOferta" dusk="drm-lancamento-dispensar-oferta">Prefiro passar bloco a bloco</x-button>
                                </div>
                            </x-drm.aviso-inline>
                        @elseif (! $origem)
                            <x-drm.aviso-inline variant="info" dusk="drm-lancamento-primeira-vez">
                                Este é o primeiro mês desta empresa. Em cada grupo de despesa você escolhe se informa só o total ou item a item — e a escolha vale para os próximos meses. No mês que vem, a maior parte já vem preenchida.
                            </x-drm.aviso-inline>
                        @endif

                        @foreach ($camposDoBloco as $campo)
                            <x-drm.campo-monetario :campo="$campo" :rotulo="$detalhes($campo)"
                                                   :help="$conteudo->help($campo)"
                                                   :herdado="in_array($campo, $repetidos, true) && $origem ? 'veio de '.$origem->mesPorExtenso() : null"/>
                        @endforeach

                        @foreach ($bloqueios as $bloqueio)
                            @if ($bloqueio->codigo === 'B4')
                                @php $m = $mensagens->de($bloqueio, $extras); @endphp
                                <x-drm.aviso-inline variant="bloqueio" :titulo="$m['titulo']" dusk="drm-lancamento-bloqueio-B4">
                                    <p class="m-0 mb-3">{{ $m['texto'] }}</p>
                                    <x-button type="button" variant="secondary" wire:click="irParaCampo('D02')" dusk="drm-lancamento-b4-corrigir">Corrigir</x-button>
                                </x-drm.aviso-inline>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- ============================ BLOCOS 2, 3 e 4 ============================ --}}
                @if (in_array($bloco_atual, [2, 3, 4], true))
                    @php $grupo = [2 => Grupo::Custo, 3 => Grupo::Vendas, 4 => Grupo::Administrativas][$bloco_atual]; @endphp
                    @if (count($repetidosDoBloco) > 0 && $origem)
                        <x-drm.aviso-inline variant="info" dusk="drm-lancamento-heranca-bloco-{{ $bloco_atual }}">
                            <p class="m-0 mb-3">{{ count($repetidosDoBloco) }} valores vieram de {{ $origem->rotulo() }}. Confira e ajuste o que mudou.</p>
                            <x-button type="button" variant="secondary" wire:click="conferirBloco" dusk="drm-lancamento-conferir-bloco-{{ $bloco_atual }}">Conferi este bloco</x-button>
                        </x-drm.aviso-inline>
                    @endif
                    @include('livewire.drm.partials.grupo', ['grupo' => $grupo])
                @endif

                {{-- ============================ BLOCO 5 ============================ --}}
                @if ($bloco_atual === 5)
                    @if (count($repetidosDoBloco) > 0 && $origem)
                        <x-drm.aviso-inline variant="info" dusk="drm-lancamento-heranca-bloco-5">
                            <p class="m-0 mb-3">{{ count($repetidosDoBloco) }} valores vieram de {{ $origem->rotulo() }}. Confira e ajuste o que mudou.</p>
                            <x-button type="button" variant="secondary" wire:click="conferirBloco" dusk="drm-lancamento-conferir-bloco-5">Conferi este bloco</x-button>
                        </x-drm.aviso-inline>
                    @endif

                    <div class="mb-4">
                        <x-drm.campo-monetario campo="D40" :rotulo="$detalhes('D40')" :help="$conteudo->help('D40')"
                                               :herdado="in_array('D40', $repetidos, true) && $origem ? 'veio de '.$origem->mesPorExtenso() : null"/>
                    </div>

                    @include('livewire.drm.partials.grupo', ['grupo' => Grupo::Financeiras])

                    @php $naoOperacionaisAbertos = collect(['D41', 'D42'])->contains(fn ($c) => trim((string) ($valores[$c] ?? '')) !== '' || $errors->has("valores.{$c}") || in_array($c, $repetidos, true)); @endphp
                    <details class="mt-4" @if ($naoOperacionaisAbertos) open @endif dusk="drm-lancamento-nao-operacionais">
                        <summary class="cursor-pointer min-h-[44px] flex items-center text-sm text-[color:var(--color-secondary)]">
                            Teve algo fora do movimento normal neste mês? (opcional)
                        </summary>
                        <div class="grid grid-cols-1 gap-4 pt-2">
                            @foreach (['D41', 'D42'] as $campo)
                                <x-drm.campo-monetario :campo="$campo" :rotulo="$detalhes($campo)" :help="$conteudo->help($campo)"
                                                       :herdado="in_array($campo, $repetidos, true) && $origem ? 'veio de '.$origem->mesPorExtenso() : null"/>
                            @endforeach
                        </div>
                    </details>
                @endif

                {{-- ============================ BLOCO 6 ============================ --}}
                @if ($ultimoBloco)
                    @if ($temErros || $bloqueios !== [])
                        <x-drm.aviso-inline variant="bloqueio" titulo="Corrija antes de fechar o mês" dusk="drm-lancamento-resumo-erros">
                            <ul class="m-0 pl-5">
                                @foreach ($errors->getMessages() as $chave => $mensagensDoCampo)
                                    @php $campoErro = \Illuminate\Support\Str::after($chave, 'valores.'); @endphp
                                    <li><button type="button" class="underline bg-transparent border-0 p-0 text-left text-[color:var(--color-primary)] cursor-pointer min-h-[44px]"
                                                wire:click="irParaCampo('{{ $campoErro }}')">{{ $mensagensDoCampo[0] }}</button></li>
                                @endforeach
                                @foreach ($bloqueios as $bloqueio)
                                    @if (in_array($bloqueio->codigo, ['B2', 'B4'], true))
                                        <li><button type="button" class="underline bg-transparent border-0 p-0 text-left text-[color:var(--color-primary)] cursor-pointer min-h-[44px]"
                                                    wire:click="irParaCampo('{{ $bloqueio->campo }}')">{{ $mensagens->de($bloqueio, $extras)['texto'] }}</button></li>
                                    @endif
                                @endforeach
                            </ul>
                        </x-drm.aviso-inline>
                    @endif

                    @if ($existente)
                        <x-drm.aviso-inline variant="bloqueio" dusk="drm-lancamento-bloqueio-B1">
                            <p class="m-0 mb-3">Você já lançou {{ $existente->rotulo() }} para esta empresa.</p>
                            <x-button :href="route('drm.mes', [$empresa, $existente->texto()])" variant="secondary">Abrir {{ $existente->rotulo() }}</x-button>
                        </x-drm.aviso-inline>
                    @endif

                    @if ($alertas !== [])
                        <x-drm.aviso-inline variant="alerta" titulo="Confira antes de fechar" dusk="drm-lancamento-alertas">
                            <ul class="m-0 mb-3 pl-5 flex flex-col gap-2">
                                @foreach ($alertas as $alerta)
                                    @php $m = $mensagens->de($alerta, $extras); @endphp
                                    <li dusk="drm-lancamento-alerta-{{ $alerta->codigo }}">
                                        <strong>{{ $m['titulo'] }}.</strong> {{ $m['texto'] }}
                                        @if ($m['consequencia'])<span class="block text-[color:var(--color-secondary)]">{{ $m['consequencia'] }}</span>@endif
                                        @if ($alerta->campo)
                                            <button type="button" class="underline bg-transparent border-0 p-0 cursor-pointer min-h-[44px] text-[color:var(--color-primary)]"
                                                    wire:click="irParaCampo('{{ $alerta->campo }}')">Revisar</button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if ($alertasPendentes !== [])
                                <x-button type="button" variant="secondary" wire:click="aceitarAlertas" dusk="drm-lancamento-aceitar-alertas">Entendi, pode fechar assim</x-button>
                            @else
                                <p class="m-0 text-[color:var(--color-secondary)]" dusk="drm-lancamento-alertas-aceitos">Alertas aceitos.</p>
                            @endif
                        </x-drm.aviso-inline>
                    @endif

                    @if ($conflito)
                        <x-drm.aviso-inline variant="bloqueio" dusk="drm-lancamento-retificacao-conflito">
                            <p class="m-0 mb-3">Este mês foi corrigido em outra sessão enquanto você preenchia. Abra a versão atual para não sobrescrever a correção.</p>
                            <x-button type="button" variant="secondary" wire:click="abrirVersaoAtual" dusk="drm-lancamento-retificacao-conflito-abrir">Abrir a versão atual</x-button>
                        </x-drm.aviso-inline>
                    @endif

                    @if ($sem_mudanca)
                        <x-drm.aviso-inline variant="info" dusk="drm-lancamento-retificacao-noop">
                            <p class="m-0 mb-3">Nenhum valor mudou, então não há o que corrigir.</p>
                            <x-button :href="route('drm.mes', [$empresa, $competencia])" variant="secondary" dusk="drm-lancamento-retificacao-noop-voltar">Voltar à demonstração</x-button>
                        </x-drm.aviso-inline>
                    @endif

                    @if ($erro_geral)
                        <x-drm.aviso-inline variant="bloqueio" dusk="drm-lancamento-erro-geral">{{ $erro_geral }}</x-drm.aviso-inline>
                    @endif

                    {{-- Conferência: o que vai ser gravado (DDR-001 item 6). As marcas de herança sobrevivem até aqui. --}}
                    <section dusk="drm-lancamento-conferencia" aria-labelledby="drm-conferencia-titulo">
                        <h3 id="drm-conferencia-titulo" class="text-base font-medium m-0 mb-3 text-[color:var(--color-primary)]">O que vai ser gravado</h3>

                        @foreach ([1, 2, 3, 4, 5] as $b)
                            @php $camposB = $b === 1 ? ['D01', 'D02'] : []; @endphp
                            <div class="mb-4 border-t border-[color:var(--color-border)] pt-3">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold m-0 text-[color:var(--color-primary)]">{{ $nomesDosBlocos[$b] }}</h4>
                                    <button type="button" class="underline bg-transparent border-0 p-0 cursor-pointer min-h-[44px] text-sm text-[color:var(--color-primary)]"
                                            wire:click="irParaBloco({{ $b }})" dusk="drm-lancamento-editar-bloco-{{ $b }}">editar</button>
                                </div>
                                <dl class="m-0 grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm">
                                    @php
                                        $linhas = match ($b) {
                                            1 => [['D01', null], ['D02', null]],
                                            2 => [[null, Grupo::Custo]],
                                            3 => [[null, Grupo::Vendas]],
                                            4 => [[null, Grupo::Administrativas]],
                                            5 => [['D40', null], [null, Grupo::Financeiras], ['D41', null], ['D42', null]],
                                        };
                                    @endphp
                                    @foreach ($linhas as [$campo, $g])
                                        @if ($g)
                                            @php $modoG = $modos[$g->value] ?? 'detalhado'; @endphp
                                            <dt class="text-[color:var(--color-secondary)]">
                                                {{ $modoG === 'agregado' ? 'informado pelo total' : 'detalhado — '.count($g->itens($setor)).' itens' }}
                                            </dt>
                                            <dd class="m-0 text-right font-medium">{{ Dinheiro::brl($lancamento->total($g)) }}</dd>
                                            @if ($modoG === 'detalhado')
                                                @foreach ($g->itens($setor) as $item)
                                                    <dt class="pl-3">{{ $detalhes($item) }}
                                                        @if (in_array($item, $repetidos, true) && $origem)<span class="block text-xs text-[color:var(--color-secondary)]">veio de {{ $origem->mesPorExtenso() }}</span>@endif
                                                    </dt>
                                                    <dd class="m-0 text-right">{{ Dinheiro::brl($lancamento->valor($item)) }}</dd>
                                                @endforeach
                                            @elseif (in_array($g->totalizador(), $repetidos, true) && $origem)
                                                <dt class="text-xs text-[color:var(--color-secondary)] col-span-2">veio de {{ $origem->mesPorExtenso() }}</dt>
                                            @endif
                                        @elseif (in_array($campo, ['D41', 'D42'], true) && $lancamento->valor($campo) === 0)
                                            {{-- sem movimento fora do normal: não polui a conferência --}}
                                        @else
                                            <dt>{{ $detalhes($campo) }}
                                                @if (in_array($campo, $repetidos, true) && $origem)<span class="block text-xs text-[color:var(--color-secondary)]">veio de {{ $origem->mesPorExtenso() }}</span>@endif
                                            </dt>
                                            <dd class="m-0 text-right font-medium">{{ Dinheiro::brl($lancamento->valor($campo)) }}</dd>
                                        @endif
                                    @endforeach
                                </dl>
                            </div>
                        @endforeach
                    </section>
                @endif
            </fieldset>

            {{-- ============================ Navegação ============================ --}}
            @if ($ultimoBloco && ($alertasPendentes !== [] || $existente))
                <p class="m-0 text-sm text-[color:var(--color-secondary)]" dusk="drm-lancamento-motivo-desabilitado">
                    {{ $existente ? 'Este mês já foi lançado.' : 'Aceite os alertas acima para poder fechar o mês.' }}
                </p>
            @endif

            <div class="flex flex-row justify-between gap-3">
                @if ($bloco_atual > 1)
                    <x-button type="button" variant="secondary" wire:click="voltar" dusk="drm-lancamento-voltar">Voltar</x-button>
                @else
                    <span></span>
                @endif

                @if ($ultimoBloco)
                    <x-button type="submit" variant="primary" :disabled="$alertasPendentes !== [] || (bool) $existente || $conflito"
                              wire:loading.attr="disabled" wire:target="fechar" dusk="drm-lancamento-fechar">
                        <span wire:loading.remove wire:target="fechar">Fechar o mês</span>
                        <span wire:loading wire:target="fechar">Fechando o mês…</span>
                    </x-button>
                @else
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="proximo" dusk="drm-lancamento-proximo">
                        <span wire:loading.remove wire:target="proximo">Próximo</span>
                        <span wire:loading wire:target="proximo">Salvando…</span>
                    </x-button>
                @endif
            </div>
            <p class="m-0 text-sm text-[color:var(--color-secondary)]">Você pode sair e voltar depois — o que já preencheu fica salvo.</p>
        </form>
    </x-card>
</div>
