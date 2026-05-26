{{--
    <x-help> — box de explicação por campo do quiz (STORY-033, espec §6.8).

    Ícone "?" ao lado do label; click abre o texto. Mesma interação em mobile e
    desktop (sem hover): bottom-sheet em < 1024px, popover ancorado ao ícone em
    ≥ 1024px (flip de layout no CSS `.help__painel`, não em JS).

    Acessibilidade: trigger é <button> nativo (Tab + Enter/Space), Esc fecha,
    aria-expanded reflete o estado e aria-describedby liga o ícone ao texto.

    Tokens v1 (cores/raio/sombra) vivem em `.help*` no app.css — nada hard-coded.

    Props:
      - id    : identificador do campo (Q01..Q23) — compõe ids/dusk únicos.
      - text  : texto do tooltip; suporta **negrito** (vem de config confiável).
      - label : nome humano do campo, usado no aria-label do ícone.
--}}
@props([
    'id',
    'text',
    'label' => '',
])

@php
    $texto = trim((string) $text);
    $painelId = 'help-painel-'.$id;
    $aria = $label !== '' ? 'O que informar em: '.$label : 'Mais informações sobre este campo';
    // Texto vem de config confiável; escapamos tudo e promovemos só **negrito**.
    $conteudo = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e($texto));
@endphp

@if ($texto !== '')
<span class="help"
      x-data="{ open: false }"
      x-on:keydown.escape.window="open = false"
      x-on:click.outside="open = false">
    <button type="button"
            class="help__trigger"
            x-on:click="open = ! open"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            aria-describedby="{{ $painelId }}"
            aria-label="{{ $aria }}"
            dusk="help-trigger-{{ $id }}">
        <span aria-hidden="true">?</span>
    </button>

    {{-- Backdrop: só no bottom-sheet (mobile). --}}
    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
         class="help__backdrop lg:hidden"
         x-on:click="open = false"
         aria-hidden="true"></div>

    {{-- Painel: bottom-sheet em < 1024px, popover em ≥ 1024px. --}}
    <div x-show="open" x-cloak
         x-transition.opacity.duration.150ms
         id="{{ $painelId }}"
         role="tooltip"
         class="help__painel"
         dusk="help-painel-{{ $id }}">
        @if ($label !== '')
            <p class="help__titulo lg:hidden">{{ $label }}</p>
        @endif
        <p class="help__texto">{!! $conteudo !!}</p>
        <button type="button"
                class="help__fechar lg:hidden"
                x-on:click="open = false">
            Fechar
        </button>
    </div>
</span>
@endif
