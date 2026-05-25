@props([
    'cor' => 'nenhum', // verde | amarelo | vermelho | nenhum
])

@php
    $cor = in_array($cor, ['verde', 'amarelo', 'vermelho', 'nenhum'], true) ? $cor : 'nenhum';

    /*
     * Mapeia a cor para um par fixo de classes utility (text + bg). Inline
     * `style="color: var(--color-farol-*)"` não funciona porque o Tailwind v4
     * faz tree-shake dos tokens do @theme quando eles não aparecem dentro de
     * `class=` — a variável fica undefined e a cor herda do <body>. Já o
     * arbitrary value `text-[color:var(--color-farol-*)]` é reconhecido pelo
     * scanner do Tailwind e força a emissão da var no bundle.
     *
     * As classes precisam estar em string literal completa (sem interpolação)
     * para o scanner do Tailwind v4 detectá-las.
     */
    $estilos = [
        'verde' => [
            'cor' => 'text-[color:var(--color-farol-verde)] bg-[color:var(--color-farol-verde)]/10',
            'rotulo' => 'Verde',
        ],
        'amarelo' => [
            'cor' => 'text-[color:var(--color-farol-amarelo)] bg-[color:var(--color-farol-amarelo)]/12',
            'rotulo' => 'Amarelo',
        ],
        'vermelho' => [
            'cor' => 'text-[color:var(--color-farol-vermelho)] bg-[color:var(--color-farol-vermelho)]/10',
            'rotulo' => 'Vermelho',
        ],
        'nenhum' => [
            'cor' => 'text-[color:var(--color-farol-cinza)] bg-[color:var(--color-farol-cinza)]/15',
            'rotulo' => 'Sem classificação',
        ],
    ][$cor];
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $estilos['cor'] }}"
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
