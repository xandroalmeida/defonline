@php
    /*
     * Demonstração do Mês (STORY-049, DDR-004). Tudo aqui é leitura do snapshot já preparado em
     * `DemonstracaoVisao`: o template não conhece código de linha, só `tipo`, `nivel` e o par
     * nominal/percentual. A ausência de um valor é traço visual + texto acessível com o motivo.
     */
    $recuo = [1 => '', 2 => 'pl-[var(--spacing-md)]', 3 => 'pl-[var(--spacing-lg)]'];
    $classeRotulo = fn ($l) => match (true) {
        $l->nivel >= 2 => 'text-[length:var(--text-body-sm)] text-[color:var(--color-secondary)] '.($recuo[$l->nivel] ?? $recuo[3]),
        $l->ehTotal() => 'font-medium text-[color:var(--color-primary)]',
        default => 'font-normal text-[color:var(--color-primary)]',
    };
    $classeNumero = fn ($l) => $l->nivel >= 2
        ? 'text-[length:var(--text-body-sm)] text-[color:var(--color-secondary)]'
        : ($l->ehTotal() ? 'font-medium text-[color:var(--color-primary)]' : 'text-[color:var(--color-primary)]');
    $marcador = fn ($l) => $l->nivel >= 3 ? '· ' : '';
    $alturaTotal = 'bg-[color:var(--color-neutral)] border-t border-[color:var(--color-border)]';
@endphp

<div dusk="drm-demonstracao">
    <x-page-header :title="$titulo" :subtitle="$empresa->nomeExibicao().' · '.$setorRotulo"/>

    {{-- Os três números, antes da conta (CA-4). Sem destaque no snapshot, a seção não existe. --}}
    @if ($visao->destaques !== [])
        <section aria-labelledby="drm-destaques-titulo" class="mb-6" dusk="drm-destaques">
            <h2 id="drm-destaques-titulo" class="sr-only">Os números principais do mês</h2>
            <div class="grid gap-3 lg:grid-cols-[repeat(auto-fit,minmax(0,1fr))]">
                @foreach ($visao->destaques as $d)
                    @php $l = $d['linha']; @endphp
                    <x-card dusk="drm-destaque-{{ $l->codigo }}">
                        <p class="m-0 text-[length:var(--text-label)] font-semibold uppercase tracking-wide text-[color:var(--color-secondary)]">
                            {{ $d['rotulo'] }}
                        </p>
                        <div class="mt-1 flex items-baseline justify-between gap-3">
                            <p class="m-0 text-[length:var(--text-h2)] font-medium leading-tight tabular-nums text-[color:var(--color-primary)]" dusk="drm-destaque-valor">
                                @if ($l->nominal !== null)
                                    {{ $l->nominal }}
                                @else
                                    <span aria-hidden="true">—</span><span class="sr-only">{{ $l->nominalAusente }}</span>
                                @endif
                            </p>
                            <p class="m-0 text-[length:var(--text-body)] tabular-nums text-[color:var(--color-secondary)]" dusk="drm-destaque-percentual">
                                @if ($l->percentual !== null)
                                    {{ $l->percentual }}
                                @else
                                    <span aria-hidden="true">—</span><span class="sr-only">{{ $l->percentualAusente }}</span>
                                @endif
                            </p>
                        </div>
                        @if ($l->prejuizo)
                            <p class="m-0 mt-2"><span class="pill" dusk="drm-pill-prejuizo">{{ config('drm.tela.pill_prejuizo') }}</span></p>
                        @endif
                    </x-card>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Modo do pró-labore (CA-3b): a frase declara o estado, o botão oferece o outro. Muda estado, não navega. --}}
    @if ($modo !== null)
        <div class="mb-4" aria-live="polite" dusk="drm-modo">
            <p class="m-0 text-[length:var(--text-body)] text-[color:var(--color-secondary)]" dusk="drm-modo-frase">{{ $modo['frase'] }}</p>
            <p class="sr-only">{{ count($visao->linhas) }} linhas na demonstração.</p>
            <div class="mt-1 flex items-center gap-2">
                <x-button type="button" variant="link" wire:click="trocarPosicao" dusk="drm-trocar-modo">{{ $modo['trocar'] }}</x-button>
                <x-help id="drm-modo" :text="config('drm.tela.modo_ajuda')" label="Retirada fora ou dentro do resultado operacional"/>
            </div>
        </div>
    @endif

    {{-- Ressalvas aceitas no lançamento (CA-7): contexto do número, recolhidas, sem ação. --}}
    @if ($ressalvas !== [])
        <details class="mb-6 text-[length:var(--text-body-sm)] text-[color:var(--color-secondary)]" wire:ignore dusk="drm-ressalvas">
            <summary class="cursor-pointer text-[color:var(--color-primary)]">Ressalvas registradas no lançamento ({{ count($ressalvas) }})</summary>
            <ul class="mt-2 mb-0 pl-5">
                @foreach ($ressalvas as $texto)
                    <li>{{ $texto }}</li>
                @endforeach
            </ul>
        </details>
    @endif

    {{-- Sem faturamento (CA-5): o motivo é dito uma vez; é um mês legítimo, não um erro. --}}
    @if ($visao->semFaturamento !== null)
        <x-drm.aviso-inline variant="info" dusk="drm-sem-faturamento">
            {{ $visao->semFaturamento }} {{ config('drm.tela.sem_faturamento_complemento') }}
        </x-drm.aviso-inline>
    @endif

    {{-- Desktop (CA-9): uma tabela de verdade, com cabeçalhos associados (CA-10). --}}
    <div class="hidden lg:block" dusk="drm-tabela">
        <table class="w-full border-collapse overflow-hidden rounded-[var(--radius-md)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)]">
            <caption class="sr-only">Demonstração de resultado de {{ $titulo }}: cada linha com o valor em reais e o percentual sobre a receita.</caption>
            <thead>
                <tr class="text-left text-[length:var(--text-label)] uppercase tracking-wide text-[color:var(--color-secondary)]">
                    <th scope="col" class="px-4 py-2 font-semibold">Linha</th>
                    <th scope="col" class="px-4 py-2 text-right font-semibold">Valor <span class="sr-only">em reais</span></th>
                    <th scope="col" class="px-4 py-2 text-right font-semibold">% da receita</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($visao->linhas as $l)
                    <tr class="{{ $l->ehTotal() ? $alturaTotal : '' }}" dusk="drm-linha-{{ $l->codigo }}">
                        <th scope="row" class="px-4 py-2 text-left {{ $classeRotulo($l) }}">
                            {{ $marcador($l) }}{{ $l->prefixo }}{{ $l->rotulo }}
                            @if ($l->prejuizo)
                                <span class="pill ml-2">{{ config('drm.tela.pill_prejuizo') }}</span>
                            @endif
                        </th>
                        <td class="px-4 py-2 text-right tabular-nums {{ $classeNumero($l) }}">
                            @if ($l->nominalSemMoeda !== null)
                                {{ $l->nominalSemMoeda }}
                            @else
                                <span aria-hidden="true">—</span><span class="sr-only">{{ $l->nominalAusente }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums text-[color:var(--color-secondary)]">
                            @if ($l->percentual !== null)
                                {{ $l->percentual }}
                            @else
                                <span aria-hidden="true">—</span><span class="sr-only">{{ $l->percentualAusente }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Mobile e tablet (CA-9): um card por trecho da conta, que fecha em cada totalizador (DDR-004). --}}
    <div class="flex flex-col gap-3 lg:hidden" dusk="drm-cards">
        @foreach ($visao->trechos as $trecho)
            <x-card as="section" class="!p-0 overflow-hidden" dusk="drm-trecho">
                <h3 class="m-0 px-4 pt-4 pb-2 text-[length:var(--text-body)] font-semibold text-[color:var(--color-primary)]">{{ $trecho['titulo'] }}</h3>
                <dl class="m-0">
                    @foreach ($trecho['linhas'] as $l)
                        <div class="px-4 py-2 {{ $l->ehTotal() ? $alturaTotal : '' }}" dusk="drm-cartao-linha-{{ $l->codigo }}">
                            <dt class="{{ $classeRotulo($l) }}">
                                {{ $marcador($l) }}{{ $l->prefixo }}{{ $l->rotulo }}
                                @if ($l->prejuizo)
                                    <span class="pill ml-2">{{ config('drm.tela.pill_prejuizo') }}</span>
                                @endif
                            </dt>
                            <dd class="m-0 flex items-baseline justify-between gap-3 tabular-nums {{ $classeNumero($l) }} {{ $recuo[$l->nivel] ?? $recuo[3] }}">
                                <span>
                                    @if ($l->nominal !== null)
                                        {{ $l->nominal }}
                                    @else
                                        <span aria-hidden="true">—</span><span class="sr-only">{{ $l->nominalAusente }}</span>
                                    @endif
                                </span>
                                <span class="text-[color:var(--color-secondary)]">
                                    @if ($l->percentual !== null)
                                        {{ $l->percentual }}
                                    @else
                                        <span aria-hidden="true">—</span><span class="sr-only">{{ $l->percentualAusente }}</span>
                                    @endif
                                </span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
        @endforeach
    </div>

    {{-- Rodapé do snapshot (CA-8): versão do motor e data DO SNAPSHOT, nunca da configuração de hoje. --}}
    @php
        $rodape = implode(' · ', array_filter([
            $visao->motorVersion === null ? null : 'motor v'.$visao->motorVersion,
            $visao->geradoEm === null ? null : 'gerado em '.$visao->geradoEm->setTimezone(config('app.timezone'))->format('d/m/Y H:i'),
        ]));
    @endphp
    @if ($rodape !== '')
        <p class="mt-4 mb-0 text-[length:var(--text-body-sm)] text-[color:var(--color-secondary)]" dusk="drm-rodape">{{ $rodape }}</p>
    @endif

    <div class="mt-6 flex flex-wrap gap-3">
        <x-button :href="route('empresas.show', $empresa)" variant="secondary" dusk="drm-voltar-empresa">Voltar para a empresa</x-button>
        <x-button :href="route('drm.lancar', $empresa)" variant="secondary" dusk="drm-lancar-outro">Lançar outro mês</x-button>
        {{-- Retificação é a STORY-050; o botão fica no lugar e desligado até lá, como no lançamento. --}}
        <x-button type="button" variant="secondary" :disabled="true" title="Em breve" dusk="drm-corrigir">Corrigir este mês</x-button>
    </div>
</div>
