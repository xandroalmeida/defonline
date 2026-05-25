@php
    use App\Support\Relatorio\IndicadorFormatter;

    /** @var \App\Models\Diagnostico $diagnostico */
    $empresa = $diagnostico->empresa;
    $nomeEmpresa = $empresa->nome_fantasia ?: $empresa->razao_social;
    $dataDiagnostico = $diagnostico->gerado_em->format('d/m/Y');
    $tituloPagina = 'Diagnóstico de '.$dataDiagnostico;

    // Snapshot da execução do motor — fonte da verdade (IDR-010 §sub-decisão 4).
    $snapshot = (array) $diagnostico->indicadores_calculados;

    $ncgAbsoluto = $snapshot['ncg_absoluto'] ?? null;

    /*
     * Glossário inline — textos editoriais derivados do Anexo I (espec V2).
     * Mudanças no Anexo I propagam por edição manual aqui — Anexo I não tem motor_version.
     */
    $glossario = [
        ['Margem Bruta (MB) / Lucro Bruto (LB)', 'Diferença entre o faturamento e o custo (dos produtos/mercadorias/serviços vendidos), antes das despesas.'],
        ['Margem Operacional Líquida (MOL)', 'A parcela operacional de lucro obtida sobre as vendas, considerando despesas financeiras, depreciação e tributos.'],
        ['Relação Dívida Líquida / EBITDA', 'Demonstra o número de períodos que uma empresa levaria para pagar sua dívida líquida.'],
        ['NCG / Vendas', 'Representa a relação entre a necessidade de capital de giro e as vendas realizadas. Expressa, em proporção, quanto da receita precisa ser financiada para sustentar o ciclo operacional.'],
        ['PMR — Prazo Médio de Recebimento', 'Tempo médio, em dias, que a empresa leva para receber dos clientes após a venda.'],
        ['PMC — Prazo Médio de pagamento a fornecedores', 'Tempo médio, em dias, que a empresa leva para pagar seus fornecedores após a compra.'],
        ['Ciclo Financeiro (CF)', 'Indica a necessidade de financiamento complementar do capital de giro, em número de dias, em média (se positivo). Fórmula: PME + PMR − PMC.'],
        ['Necessidade de Capital de Giro (NCG)', 'Instrumento de aferição da saúde financeira da empresa, apurada pela diferença entre Ativo e Passivo circulante cíclico. Se positiva, indica quanto de financiamento complementar a empresa necessita.'],
        ['EBITDA / Margem Operacional', 'Resultado do negócio: faturamento menos custos e despesas operacionais, exceto depreciação (e antes das despesas financeiras e tributos). Mede a eficiência em produzir lucros por meio das vendas.'],
        ['Farol / Semáforo', 'Classificação visual (verde / amarelo / vermelho) atribuída automaticamente a cada indicador a partir das faixas da matriz de recomendações.'],
        ['Indisponível', 'Rótulo exibido quando o indicador não pode ser calculado (ex.: denominador igual a zero ou dado faltante). Não recebe farol nem texto de recomendação.'],
        ['Balanço Adaptado / DRE Adaptada', 'Versão simplificada construída a partir dos 23 campos do quiz; suficiente para os indicadores da v1, mas não equivalente a um balanço/DRE contábil formal.'],
    ];
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
            <span class="pill" dusk="diagnostico-mvp-badge"
                  style="background-color: rgba(99, 91, 255, 0.10); color: var(--color-tertiary); border: 1px solid rgba(99, 91, 255, 0.3); padding: 4px 10px; border-radius: 9999px; font-size: 0.72rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;">
                Versão MVP
            </span>
        </x-slot:actions>
    </x-page-header>

    {{-- 7 indicadores essenciais. ----------------------------------------------
         Renderiza dois layouts: tabela densa em ≥ md, stack de cards em < md.
         IDR-008 (tokens) + briefing §6 mobile-first.
    --}}
    <section aria-labelledby="indicadores-titulo" class="mb-8">
        <h2 id="indicadores-titulo" class="sr-only">Indicadores essenciais</h2>

        {{-- Desktop: tabela densa. --}}
        <div class="hidden md:block rounded-[length:var(--radius-md)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] overflow-hidden shadow-[var(--shadow-sm)]">
            <table class="w-full text-sm" data-testid="indicadores-tabela">
                <caption class="sr-only">7 indicadores essenciais com farol e mensagem.</caption>
                <thead class="bg-[color:var(--color-neutral)]">
                    <tr class="text-left">
                        <th scope="col" class="px-4 py-3 text-[color:var(--color-secondary)] text-xs uppercase tracking-wider font-semibold">Indicador</th>
                        <th scope="col" class="px-4 py-3 text-[color:var(--color-secondary)] text-xs uppercase tracking-wider font-semibold">Valor</th>
                        <th scope="col" class="px-4 py-3 text-[color:var(--color-secondary)] text-xs uppercase tracking-wider font-semibold">Farol</th>
                        <th scope="col" class="px-4 py-3 text-[color:var(--color-secondary)] text-xs uppercase tracking-wider font-semibold">Mensagem</th>
                    </tr>
                </thead>
                <tbody class="[&_th]:px-4 [&_td]:px-4">
                    @foreach (IndicadorFormatter::ORDEM_ESSENCIAIS as $codigo)
                        @if (isset($snapshot[$codigo]))
                            <x-relatorio.linha-indicador :codigo="$codigo" :indicador="$snapshot[$codigo]"/>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile: stack de cards. --}}
        <div class="md:hidden flex flex-col gap-3" data-testid="indicadores-cards">
            @foreach (IndicadorFormatter::ORDEM_ESSENCIAIS as $codigo)
                @if (isset($snapshot[$codigo]))
                    <x-relatorio.card-indicador :codigo="$codigo" :indicador="$snapshot[$codigo]"/>
                @endif
            @endforeach
        </div>
    </section>

    {{-- NCG absoluto — informativo, sem farol. ------------------------------- --}}
    @if ($ncgAbsoluto !== null)
        <div class="mb-8">
            <x-relatorio.card-ncg :indicador="$ncgAbsoluto"/>
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
                <details class="group">
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
        <x-button :href="route('empresas.show', $empresa)" variant="secondary" dusk="diagnostico-voltar-empresa">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Voltar para a empresa
        </x-button>
    </div>
</x-layouts.app>
