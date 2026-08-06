@props([
    'title' => null,
    'breadcrumb' => [],
])

@php
    /*
     * Layout APP (autenticado): header global + sidebar/drawer + main + footer institucional.
     * H1 e <title> são derivados de $title. Breadcrumb opcional (array de [label, url]).
     */
    $documentTitle = $title ? "{$title} · DEFOnline" : config('app.name', 'DEFOnline');
@endphp

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $documentTitle }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen flex flex-col">
    {{--
        STORY-059 CA-2 — o shell precisa saber se a navegação está off-canvas.

        `navOpen` sozinho não responde à pergunta "o drawer está escondido?":
        em ≥1024px a navegação é sidebar fixa e visível **com `navOpen` false**.
        Era daí que vinha o defeito — o `aria-hidden` era ligado só a `navOpen`,
        então a sidebar do desktop, plenamente visível, saía da árvore de
        acessibilidade; e no celular o drawer fechado continuava com os filhos
        na ordem de tabulação, que é o `aria-hidden-focus` acusado pelo gate.

        `telaLarga` acompanha o mesmo breakpoint do CSS (`lg`) via matchMedia,
        e `navFechado` é a condição real de "escondido": estreito **e** fechado.
    --}}
    <div x-data="{
             navOpen: false,
             telaLarga: window.matchMedia('(min-width: 1024px)').matches,
             init() {
                 window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => {
                     this.telaLarga = e.matches;
                     if (e.matches) this.navOpen = false;
                 });
             },
             get navFechado() { return ! this.telaLarga && ! this.navOpen },
             sincronizarInerte(el) {
                 el.toggleAttribute('inert', this.navFechado);
                 if (this.navFechado) {
                     el.setAttribute('aria-hidden', 'true');
                 } else {
                     el.removeAttribute('aria-hidden');
                 }
             },
         }"
         @keydown.escape.window="navOpen = false"
         class="flex-1 flex flex-col">

        {{-- Header sticky no topo. --}}
        <x-app-header :usuario="auth()->user()"/>

        {{-- Shell em grid: sidebar (lg+) à esquerda, conteúdo + footer à direita. --}}
        <div class="flex-1 flex">
            <x-app-nav/>

            <div class="flex-1 flex flex-col min-w-0">
                <main class="flex-1 px-4 sm:px-6 py-6 sm:py-8" data-testid="app-main">
                    <div class="max-w-[960px] mx-auto w-full">
                        @if (count($breadcrumb) > 0)
                            <x-breadcrumb :items="$breadcrumb"/>
                        @endif
                        {{ $slot }}
                    </div>
                </main>

                <x-app-footer variant="app"/>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
