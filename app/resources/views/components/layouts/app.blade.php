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
    <div x-data="{ navOpen: false }"
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
