@props([
    'cor' => 'nenhum', // verde | amarelo | vermelho | nenhum
])

@php
    $cor = in_array($cor, ['verde', 'amarelo', 'vermelho', 'nenhum'], true) ? $cor : 'nenhum';

    $config = [
        'verde' => [
            'token' => 'var(--color-farol-verde)',
            'bg' => 'rgba(14, 159, 110, 0.12)',
            'rotulo' => 'Verde',
        ],
        'amarelo' => [
            'token' => 'var(--color-farol-amarelo)',
            'bg' => 'rgba(217, 119, 6, 0.14)',
            'rotulo' => 'Amarelo',
        ],
        'vermelho' => [
            'token' => 'var(--color-farol-vermelho)',
            'bg' => 'rgba(220, 38, 38, 0.12)',
            'rotulo' => 'Vermelho',
        ],
        'nenhum' => [
            'token' => 'var(--color-farol-cinza)',
            'bg' => 'rgba(148, 163, 184, 0.14)',
            'rotulo' => 'Sem classificação',
        ],
    ][$cor];
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
      style="color: {{ $config['token'] }}; background-color: {{ $config['bg'] }};"
      role="img"
      aria-label="Farol: {{ $config['rotulo'] }}"
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
    <span>{{ $config['rotulo'] }}</span>
</span>
