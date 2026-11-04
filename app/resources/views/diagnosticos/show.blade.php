@php
    use App\Support\Relatorio\Glossario;
    use App\Support\Relatorio\IndicadorFormatter;
    use App\Support\Relatorio\ValoresNominais;

    /** @var \App\Models\Diagnostico $diagnostico */
    $empresa = $diagnostico->empresa;
    $nomeEmpresa = $empresa->nome_fantasia ?: $empresa->razao_social;
    $dataDiagnostico = $diagnostico->gerado_em->format('d/m/Y');
    $tituloPagina = 'Diagnóstico de '.$dataDiagnostico;

    // Snapshot da execução do motor — fonte da verdade (IDR-010 §sub-decisão 4).
    $snapshot = (array) $diagnostico->indicadores_calculados;

    $ncgAbsoluto = $snapshot['ncg_absoluto'] ?? null;
    $cicloOperacional = $snapshot['ciclo_operacional'] ?? null;

    /*
     * Valores nominais (STORY-044) — derivados da OUTRA coluna do mesmo snapshot,
     * o `quiz_payload` persistido e imutável. Leitura pura: o motor não é
     * invocado, nada é gravado, `payload_hash` e `motor_version` não mudam
     * (CA-2 + IDR-013). Instanciado uma vez e consultado por indicador.
     */
    $nominais = ValoresNominais::doSnapshot((array) $diagnostico->quiz_payload);

    // Resumo Executivo §4.7.1 — coluna NOT NULL desde STORY-028 (placeholder até a 030,
    // populada de verdade pela 031). Snapshots em motor_version < 1.2.0 (placeholder
    // `{pendente_story, fallback_acionado}`) NÃO contêm `veredito` — o componente trata
    // qualquer payload inválido como fallback visual.
    $resumoExecutivo = (array) ($diagnostico->resumo_executivo ?? []);
    $resumoEhValido = isset($resumoExecutivo['veredito']);

    /*
     * Destino do botão "Voltar" — segue o Referer (de onde Roberto veio):
     * Minhas Empresas, Selecionar Empresa, ou redirect do submit do quiz.
     * Fallback /home quando não há referer (URL bookmarkada ou refresh) ou
     * quando o referer é externo ao app (defesa contra open-redirect).
     */
    $voltarUrl = url()->previous();
    $hostApp = request()->getSchemeAndHttpHost();
    $urlAtual = request()->fullUrl();
    if (! str_starts_with($voltarUrl, $hostApp) || $voltarUrl === $urlAtual) {
        $voltarUrl = route('home');
    }

    // Glossário — compartilhado com o PDF (App\Support\Relatorio\Glossario).
    $glossario = Glossario::ITENS;
@endphp

<x-layouts.app :title="$tituloPagina"
               :breadcrumb="[
                   ['label' => 'Minhas Empresas', 'url' => route('home')],
                   ['label' => $nomeEmpresa, 'url' => route('empresas.show', $empresa)],
                   ['label' => 'Diagnóstico de ' . $dataDiagnostico, 'url' => route('diagnosticos.show', $diagnostico)],
               ]">

    <x-page-header :title="$nomeEmpresa"
                   :subtitle="'Diagnóstico de ' . $dataDiagnostico">
        <x-slot:actions>
            <span class="pill pill--versao self-center" dusk="diagnostico-mvp-badge">
                Versão MVP
            </span>
            {{-- PDF do mesmo snapshot (capa + resumo + indicadores + glossário). --}}
            <x-button :href="route('diagnosticos.pdf', $diagnostico)" variant="secondary" size="sm"
                      dusk="diagnostico-baixar-pdf" data-testid="diagnostico-baixar-pdf">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Baixar PDF
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-personificacao-marca :registro="$diagnostico" class="mb-4"/>

    {{-- Resumo executivo §4.7.1 (STORY-031). Diagnósticos antigos em motor < 1.2.0
         carregam placeholder e não renderizam o bloco (mantém compatibilidade). --}}
    @if ($resumoEhValido)
        <x-relatorio.resumo-executivo :resumo="$resumoExecutivo"/>
    @endif

    {{-- 13 indicadores essenciais com farol (motor V2 = 1.1.0, Anexo D §4.5).
         Renderiza dois layouts: tabela densa em ≥ md, stack de cards em < md.
         IDR-008 (tokens) + briefing §6 mobile-first.
    --}}
    <section id="indicadores-detalhados" aria-labelledby="indicadores-titulo" class="mb-8">
        <h2 id="indicadores-titulo" class="sr-only">Indicadores essenciais</h2>

        {{-- Desktop: tabela densa. --}}
        <div class="hidden md:block rounded-[length:var(--radius-md)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] overflow-hidden shadow-[var(--shadow-sm)]">
            <table class="w-full text-sm" data-testid="indicadores-tabela">
                <caption class="sr-only">Indicadores essenciais com farol e mensagem.</caption>
                <thead class="bg-[color:var(--color-neutral)]">
                    <tr class="text-left">
                        <th scope="col" class="px-4 py-3 text-[color:var(--color-secondary)] text-xs uppercase tracking-wider font-semibold">Indicador</th>
                        {{-- Largura reservada para o valor + os termos nominais (STORY-044).
                             Sem ela a coluna encolhe contra a Mensagem e cada par
                             "rótulo: R$ x" quebra no meio, ficando ilegível. --}}
                        <th scope="col" class="px-4 py-3 w-64 text-[color:var(--color-secondary)] text-xs uppercase tracking-wider font-semibold">Valor</th>
                        <th scope="col" class="px-4 py-3 text-[color:var(--color-secondary)] text-xs uppercase tracking-wider font-semibold">Farol</th>
                        <th scope="col" class="px-4 py-3 text-[color:var(--color-secondary)] text-xs uppercase tracking-wider font-semibold">Mensagem</th>
                    </tr>
                </thead>
                <tbody class="[&_th]:px-4 [&_td]:px-4">
                    @foreach (IndicadorFormatter::ORDEM_ESSENCIAIS as $codigo)
                        @if (isset($snapshot[$codigo]))
                            <x-relatorio.linha-indicador :codigo="$codigo"
                                                         :indicador="$snapshot[$codigo]"
                                                         :nominais="$nominais->paraIndicador($codigo)"/>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile: stack de cards. --}}
        <div class="md:hidden flex flex-col gap-3" data-testid="indicadores-cards">
            @foreach (IndicadorFormatter::ORDEM_ESSENCIAIS as $codigo)
                @if (isset($snapshot[$codigo]))
                    <x-relatorio.card-indicador :codigo="$codigo"
                                                :indicador="$snapshot[$codigo]"
                                                :nominais="$nominais->paraIndicador($codigo)"/>
                @endif
            @endforeach
        </div>
    </section>

    {{-- Indicadores informativos (sem farol) — NCG absoluto + Ciclo Operacional. --}}
    @if ($ncgAbsoluto !== null || $cicloOperacional !== null)
        <div class="mb-8 grid gap-4 md:grid-cols-2">
            @if ($ncgAbsoluto !== null)
                <x-relatorio.card-informativo codigo="ncg_absoluto"
                                             :indicador="$ncgAbsoluto"
                                             :nominais="$nominais->paraIndicador('ncg_absoluto')"/>
            @endif
            @if ($cicloOperacional !== null)
                <x-relatorio.card-informativo codigo="ciclo_operacional" :indicador="$cicloOperacional"/>
            @endif
        </div>
    @endif

    {{-- Glossário inline. -------------------------------------------------- --}}
    <section aria-labelledby="glossario-titulo" class="mb-8" data-testid="glossario">
        <h2 id="glossario-titulo" class="text-[color:var(--color-primary)] text-lg font-medium mb-3">Glossário</h2>
        <p class="text-[color:var(--color-secondary)] text-sm mb-4">
            Definições rápidas dos termos usados neste relatório. Toque para expandir.
        </p>
        <div class="rounded-[length:var(--radius-md)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] divide-y divide-[color:var(--color-border)]">
            @foreach ($glossario as [$termo, $definicao])
                <details class="group break-inside-avoid">
                    <summary class="cursor-pointer px-4 py-3 text-[color:var(--color-primary)] font-medium text-sm flex items-center justify-between gap-3 hover:bg-[color:var(--color-neutral)] focus:bg-[color:var(--color-neutral)] focus:outline-2 focus:outline-offset-[-2px] focus:outline-[color:var(--color-ring)]">
                        <span>{{ $termo }}</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="transition-transform group-open:rotate-180 text-[color:var(--color-secondary)] flex-shrink-0"
                             aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </summary>
                    <div class="px-4 pb-3 text-[color:var(--color-secondary)] text-sm">{{ $definicao }}</div>
                </details>
            @endforeach
        </div>
    </section>

    {{-- Rodapé do relatório — versões do snapshot (NÃO do config atual). ------ --}}
    <footer class="border-t border-[color:var(--color-border)] pt-4 text-[color:var(--color-secondary)] text-xs"
            data-testid="relatorio-rodape">
        <p class="m-0 mb-1">
            <span dusk="relatorio-versoes">
                motor v{{ $diagnostico->motor_version }} · matriz {{ $diagnostico->matrix_version }} · gerado em {{ $diagnostico->gerado_em->format('d/m/Y H:i') }}
            </span>
        </p>
        <p class="m-0 italic">
            Este diagnóstico é uma ferramenta de apoio à gestão. Decisões financeiras devem considerar análise integral do negócio.
        </p>
    </footer>

    <div class="mt-6 flex">
        <x-button :href="$voltarUrl" variant="secondary" dusk="diagnostico-voltar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Voltar
        </x-button>
    </div>
</x-layouts.app>
