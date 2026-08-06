@props([
    'cor' => 'nenhum', // verde | amarelo | vermelho | nenhum
])

@php
    $cor = in_array($cor, ['verde', 'amarelo', 'vermelho', 'nenhum'], true) ? $cor : 'nenhum';

    /*
     * **Fundo opaco na cor viva, texto na tinta (STORY-059 CA-3, DDR-006).**
     *
     * Aparência e cores vivem em `.farol--<cor>` no app.css. Duas razões:
     *
     *   1. O fundo precisa ser **opaco** (`color-mix` com `--color-surface`).
     *      Como alpha, ele somava com o tingimento do cabeçalho do card do
     *      Resumo Executivo e derrubava o contraste do rótulo de 4,76 para
     *      4,2:1 — o pill mudava de legibilidade conforme onde era colocado.
     *   2. O truque antigo (`bg-[color:var(--color-farol-*)]/10` em string
     *      literal, para o scanner do Tailwind v4 não fazer tree-shake da var)
     *      deixa de ser necessário quando a regra mora em CSS de componente.
     *
     * O ícone herda `currentColor`, então acompanha a tinta sem elemento
     * próprio — e sobe junto de 2,27–4,13 para ≥4,5:1, folgado em relação ao
     * piso de 3:1 da WCAG 1.4.11 para gráfico significativo.
     */
    $rotulos = [
        'verde' => 'Verde',
        'amarelo' => 'Amarelo',
        'vermelho' => 'Vermelho',
        'nenhum' => 'Sem classificação',
    ];
    $estilos = ['rotulo' => $rotulos[$cor]];
@endphp

<span class="farol farol--{{ $cor }}"
      role="img"
      aria-label="Farol: {{ $estilos['rotulo'] }}"
      data-farol="{{ $cor }}">
    {{-- Ícone (acessibilidade — daltônicos não dependem da cor). --}}
    @switch($cor)
        @case('verde')
            {{-- check-circle --}}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="9 12 11 14 15 10"/>
            </svg>
            @break
        @case('amarelo')
            {{-- exclamation-triangle --}}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <circle cx="12" cy="17" r="0.6" fill="currentColor"/>
            </svg>
            @break
        @case('vermelho')
            {{-- x-circle --}}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
            </svg>
            @break
        @default
            {{-- information-circle (informativo / sem cor) --}}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="11" x2="12" y2="16"/>
                <circle cx="12" cy="8" r="0.6" fill="currentColor"/>
            </svg>
    @endswitch
    <span>{{ $estilos['rotulo'] }}</span>
</span>
