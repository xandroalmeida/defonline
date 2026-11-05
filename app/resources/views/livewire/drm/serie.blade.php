@php
    /*
     * Série histórica (STORY-051, spec STORY-046-serie-historica). Tudo aqui é leitura do que `SerieVisao`
     * já preparou. Mês não lançado é uma linha de AUSÊNCIA (borda tracejada, sem número, sem comparativo,
     * com ação) — nunca um mês zerado (DDR-005): a diferença é estrutural e textual, não só de cor.
     */
    $classeCartao = fn ($l) => 'rounded-[var(--radius-md)] bg-[color:var(--color-surface)] border '
        .($l->estado === 'nao_lancado' ? 'border-dashed border-[color:var(--color-border)]' : 'border-solid border-[color:var(--color-border)]');
    $textoSecundario = 'text-[color:var(--color-secondary)]';
@endphp

<div dusk="drm-serie">
    <x-page-header :title="config('drm.serie.titulo')" :subtitle="$empresa->nomeExibicao().' · '.$setorRotulo">
        @if ($visao->ctaUrl !== null && ! $visao->vazio)
            <x-slot:actions>
                <x-button :href="$visao->ctaUrl" variant="primary" dusk="drm-serie-lancar">{{ $visao->ctaRotulo }}</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($visao->vazio)
        {{-- Primeira vez (spec §4.3): sem ilustração; a segunda frase é a promessa que sustenta a métrica do épico. --}}
        <x-card dusk="drm-serie-vazio">
            <p class="m-0 mb-2 text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)]">{{ config('drm.serie.vazio_titulo') }}</p>
            <p class="m-0 mb-4 {{ $textoSecundario }}">{{ config('drm.serie.vazio_instrucao') }}</p>
            <x-button :href="$visao->ctaUrl" variant="primary" dusk="drm-serie-lancar">{{ $visao->ctaRotulo }}</x-button>
        </x-card>
    @else
        @if ($visao->avisoLacunas !== null)
            <x-drm.aviso-inline variant="info" dusk="drm-serie-lacunas">{{ $visao->avisoLacunas }}</x-drm.aviso-inline>
        @endif

        @foreach ($visao->anos as $grupo)
            <section class="mb-8" aria-labelledby="drm-serie-ano-{{ $grupo['ano'] }}" dusk="drm-serie-ano-{{ $grupo['ano'] }}">
                <h2 id="drm-serie-ano-{{ $grupo['ano'] }}" class="m-0 mb-3 text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)]">{{ $grupo['ano'] }}</h2>

                {{-- Mobile e tablet: uma lista de cartões. --}}
                <ul class="m-0 p-0 list-none flex flex-col gap-3 lg:hidden" dusk="drm-serie-lista">
                    @foreach ($grupo['linhas'] as $l)
                        <li class="p-4 {{ $classeCartao($l) }}" dusk="drm-serie-competencia-{{ $l->competencia }}">
                            <div class="flex items-baseline justify-between gap-3">
                                <h3 class="m-0 text-[length:var(--text-label)] font-semibold uppercase tracking-wide text-[color:var(--color-primary)]">
                                    @if ($l->mesUrl)
                                        <a href="{{ $l->mesUrl }}" class="inline-flex items-center min-h-[44px] text-[color:var(--color-primary)]" aria-label="Abrir {{ $l->nomeAcessivel }}">{{ $l->rotulo }}</a>
                                    @else
                                        {{ $l->rotulo }}
                                    @endif
                                </h3>
                                <span class="flex flex-wrap items-center justify-end gap-2 text-[length:var(--text-body-sm)] {{ $textoSecundario }}">
                                    <span dusk="drm-serie-estado-{{ $l->competencia }}">{{ $l->estadoTexto }}</span>
                                    @if ($l->retificado)<span class="pill" dusk="drm-serie-retificado-{{ $l->competencia }}">{{ config('drm.serie.pills.retificado') }}</span>@endif
                                    @if ($l->estado === 'rascunho')<span class="pill" dusk="drm-serie-rascunho-{{ $l->competencia }}">{{ config('drm.serie.pills.rascunho') }}</span>@endif
                                    @if ($l->atipico)<span class="pill" dusk="drm-serie-atipico-{{ $l->competencia }}">{{ config('drm.serie.pills.atipico') }}</span>@endif
                                </span>
                            </div>

                            @if ($l->lancado())
                                <p class="m-0 mt-2 text-[length:var(--text-body-sm)] {{ $textoSecundario }}">{{ config('drm.serie.rotulo_valor') }}</p>
                                <div class="flex items-baseline justify-between gap-3">
                                    <p class="m-0 text-[length:var(--text-h2)] font-medium tabular-nums text-[color:var(--color-primary)]" dusk="drm-serie-valor-{{ $l->competencia }}">{{ $l->valor }}</p>
                                    <p class="m-0 tabular-nums {{ $textoSecundario }}">
                                        @if ($l->percentual !== null){{ $l->percentual }}@else<span class="sr-only">{{ $l->percentualAusente }}</span>@endif
                                    </p>
                                </div>
                                @if ($l->prejuizo)<p class="m-0 mt-1"><span class="pill">{{ config('drm.serie.pills.prejuizo') }}</span></p>@endif
                                <p class="m-0 mt-2 text-[length:var(--text-body-sm)] {{ $textoSecundario }}" dusk="drm-serie-vs-anterior-{{ $l->competencia }}">{{ $l->vsAnterior }}</p>
                                <p class="m-0 text-[length:var(--text-body-sm)] {{ $textoSecundario }}" dusk="drm-serie-vs-media-{{ $l->competencia }}">{{ $l->vsMedia }}</p>
                                @if ($l->atipico)
                                    <p class="m-0 mt-2 flex items-start gap-2 text-[length:var(--text-body-sm)] text-[color:var(--color-primary)]" dusk="drm-serie-atipico-explicacao-{{ $l->competencia }}">
                                        <span>{{ $l->explicacao }}</span>
                                        <x-help id="drm-serie-atipico-{{ $l->competencia }}" :text="config('drm.serie.atipico_ajuda')" label="Por que este mês é atípico"/>
                                    </p>
                                @endif
                            @else
                                <p class="m-0 mt-2 {{ $textoSecundario }}" dusk="drm-serie-ausencia-{{ $l->competencia }}">{{ $l->rascunhoTexto }}</p>
                            @endif

                            <div class="mt-3 flex justify-end">
                                <x-button :href="$l->acaoUrl" variant="secondary" aria-label="{{ $l->acaoAria }}" dusk="drm-serie-acao-{{ $l->competencia }}">{{ $l->acaoRotulo }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Desktop: uma tabela, para a coluna de comparativos poder ser varrida de cima a baixo. --}}
                <table class="hidden lg:table w-full table-fixed border-collapse bg-[color:var(--color-surface)] border border-[color:var(--color-border)]" dusk="drm-serie-tabela">
                    <caption class="sr-only">Meses de {{ $grupo['ano'] }}: o lucro de cada mês e a comparação com o mês anterior e com a média.</caption>
                    <thead>
                        <tr class="text-left text-[length:var(--text-label)] uppercase tracking-wide {{ $textoSecundario }} bg-[color:var(--color-neutral)]">
                            <th scope="col" class="px-4 py-2 font-semibold w-[17%]">{{ config('drm.serie.cabecalhos.mes') }}</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold w-[14%]">{{ config('drm.serie.cabecalhos.vendeu') }}</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold w-[16%]">{{ config('drm.serie.cabecalhos.lucro') }}</th>
                            <th scope="col" class="px-4 py-2 font-semibold w-[20%]">{{ config('drm.serie.cabecalhos.anterior') }}</th>
                            <th scope="col" class="px-4 py-2 font-semibold w-[20%]">{{ config('drm.serie.cabecalhos.media') }}</th>
                            <th scope="col" class="px-4 py-2 font-semibold w-[13%]"><span class="sr-only">{{ config('drm.serie.cabecalhos.acao') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grupo['linhas'] as $l)
                            <tr class="align-top border-t border-[color:var(--color-border)] {{ $l->estado === 'nao_lancado' ? 'border-dashed' : '' }}" dusk="drm-serie-tabela-linha-{{ $l->competencia }}">
                                <th scope="row" class="px-4 py-3 text-left font-normal">
                                    <span class="font-semibold uppercase tracking-wide text-[color:var(--color-primary)]">
                                        @if ($l->mesUrl)
                                            <a href="{{ $l->mesUrl }}" class="text-[color:var(--color-primary)]" aria-label="Abrir {{ $l->nomeAcessivel }}">{{ $l->rotulo }}</a>
                                        @else
                                            {{ $l->rotulo }}
                                        @endif
                                    </span>
                                    <span class="block text-[length:var(--text-body-sm)] {{ $textoSecundario }}">{{ $l->estadoTexto }}</span>
                                    @if ($l->retificado)
                                        <span class="pill mt-1">{{ config('drm.serie.pills.retificado') }}</span>
                                        <span class="block text-[length:var(--text-body-sm)] {{ $textoSecundario }}">{{ $l->corrigidoTexto }}</span>
                                    @endif
                                    @if ($l->estado === 'rascunho')<span class="pill mt-1">{{ config('drm.serie.pills.rascunho') }}</span>@endif
                                    @if ($l->atipico)<span class="pill mt-1">{{ config('drm.serie.pills.atipico') }}</span>@endif
                                </th>
                                @if ($l->lancado())
                                    <td class="px-4 py-3 text-right tabular-nums {{ $textoSecundario }}">{{ $l->vendeu }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-[color:var(--color-primary)]">
                                        <span class="block font-medium" dusk="drm-serie-tabela-valor-{{ $l->competencia }}">{{ $l->valorSemMoeda }}</span>
                                        <span class="block text-[length:var(--text-body-sm)] {{ $textoSecundario }}">
                                            @if ($l->percentual !== null){{ $l->percentual }}@else<span class="sr-only">{{ $l->percentualAusente }}</span>@endif
                                        </span>
                                        @if ($l->prejuizo)<span class="pill">{{ config('drm.serie.pills.prejuizo') }}</span>@endif
                                    </td>
                                    <td class="px-4 py-3 text-[length:var(--text-body-sm)] {{ $textoSecundario }}" dusk="drm-serie-tabela-vs-anterior-{{ $l->competencia }}">{{ $l->vsAnterior }}</td>
                                    <td class="px-4 py-3 text-[length:var(--text-body-sm)] {{ $textoSecundario }}" dusk="drm-serie-tabela-vs-media-{{ $l->competencia }}">{{ $l->vsMedia }}</td>
                                @else
                                    {{-- A ausência atravessa as colunas numéricas: uma célula só, não quatro vazias (spec §7). --}}
                                    <td colspan="4" class="px-4 py-3 {{ $textoSecundario }}" dusk="drm-serie-tabela-ausencia-{{ $l->competencia }}">{{ $l->rascunhoTexto }}</td>
                                @endif
                                <td class="px-4 py-3 text-right">
                                    <x-button :href="$l->acaoUrl" variant="secondary" aria-label="{{ $l->acaoAria }}" dusk="drm-serie-tabela-acao-{{ $l->competencia }}">{{ $l->acaoRotulo }}</x-button>
                                </td>
                            </tr>
                            @if ($l->atipico)
                                <tr class="border-0" dusk="drm-serie-tabela-atipico-{{ $l->competencia }}">
                                    <td></td>
                                    <td colspan="5" class="px-4 pb-3 text-[length:var(--text-body-sm)] text-[color:var(--color-primary)]">
                                        <span class="inline-flex items-start gap-2"><span>{{ $l->explicacao }}</span>
                                            <x-help id="drm-serie-tabela-atipico-{{ $l->competencia }}" :text="config('drm.serie.atipico_ajuda')" label="Por que este mês é atípico"/>
                                        </span>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    @endif

    <div class="mt-6 flex flex-wrap gap-3">
        <x-button :href="route('empresas.show', $empresa)" variant="secondary" dusk="drm-serie-voltar">Voltar para a empresa</x-button>
    </div>
</div>
