@props([
    /*
     * Estrutura do resumo_executivo (§4.7.1 — produzido por App\Domain\Motor\ResumoExecutivo):
     *   - veredito: 'saudavel' | 'precisa_atencao' | 'em_alerta' | 'fallback'
     *   - veredito_texto: string|null (literal §4.7.1)
     *   - destaques_negativos: list<{codigo, texto}> (0..2 itens)
     *   - destaque_positivo: {codigo, texto} | null (texto já vem prefixado com "Por outro lado, ")
     *   - linha_fixa: string|null
     *   - fallback_acionado: bool
     *   - mensagem_fallback: string|null
     *   - mensagem_extra?: string  (Passo 6 — caso "todos verde")
     */
    'resumo',
])

@php
    $resumo = (array) $resumo;
    $veredito = $resumo['veredito'] ?? 'fallback';
    $fallback = ! empty($resumo['fallback_acionado']);

    /*
     * Cor de cabeçalho por veredito. Usa as mesmas vars CSS do farol para
     * consistência visual com a tabela de indicadores.
     */
    $coresPorVeredito = [
        'saudavel' => 'border-[color:var(--color-farol-verde)] bg-[color:var(--color-farol-verde)]/8',
        'precisa_atencao' => 'border-[color:var(--color-farol-amarelo)] bg-[color:var(--color-farol-amarelo)]/10',
        'em_alerta' => 'border-[color:var(--color-farol-vermelho)] bg-[color:var(--color-farol-vermelho)]/8',
        'fallback' => 'border-[color:var(--color-border)] bg-[color:var(--color-neutral)]',
    ];
    $classes = $coresPorVeredito[$veredito] ?? $coresPorVeredito['fallback'];

    /*
     * Mapeia veredito → cor de farol existente (reuso do componente <x-relatorio.farol/>).
     */
    $corFarol = match ($veredito) {
        'saudavel' => 'verde',
        'precisa_atencao' => 'amarelo',
        'em_alerta' => 'vermelho',
        default => 'nenhum',
    };
@endphp

<aside role="region"
       aria-label="Resumo executivo"
       data-testid="resumo-executivo"
       data-veredito="{{ $veredito }}"
       class="mb-6 rounded-[length:var(--radius-md)] border-l-4 {{ $classes }} p-5 sm:p-6 shadow-[var(--shadow-sm)]">

    <header class="flex items-center gap-3 mb-3">
        <x-relatorio.farol :cor="$corFarol"/>
        <h2 class="text-[color:var(--color-primary)] text-sm font-semibold uppercase tracking-wider m-0">Resumo executivo</h2>
    </header>

    @if ($fallback)
        <p class="text-[color:var(--color-primary)] text-base m-0" data-testid="resumo-fallback">
            {{ $resumo['mensagem_fallback'] ?? '' }}
        </p>
    @else
        <p class="text-[color:var(--color-primary)] text-base font-medium m-0 mb-2" data-testid="resumo-veredito">
            {{ $resumo['veredito_texto'] ?? '' }}
        </p>

        @if (! empty($resumo['mensagem_extra']))
            <p class="text-[color:var(--color-primary)] text-sm m-0 mb-2" data-testid="resumo-mensagem-extra">
                {{ $resumo['mensagem_extra'] }}
            </p>
        @endif

        @if (! empty($resumo['destaques_negativos']) || ! empty($resumo['destaque_positivo']))
            <ul class="list-none p-0 m-0 mb-2 space-y-1 text-[color:var(--color-primary)] text-sm" data-testid="resumo-destaques">
                @foreach ($resumo['destaques_negativos'] as $d)
                    <li data-codigo="{{ $d['codigo'] }}" data-tipo="negativo">{{ $d['texto'] }}</li>
                @endforeach
                @if (! empty($resumo['destaque_positivo']))
                    <li data-codigo="{{ $resumo['destaque_positivo']['codigo'] }}" data-tipo="positivo">
                        {{ $resumo['destaque_positivo']['texto'] }}
                    </li>
                @endif
            </ul>
        @endif

        @if (! empty($resumo['linha_fixa']))
            <p class="text-[color:var(--color-secondary)] text-sm italic m-0" data-testid="resumo-linha-fixa">
                {{ $resumo['linha_fixa'] }}
            </p>
        @endif
    @endif
</aside>
