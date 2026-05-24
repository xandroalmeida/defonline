@props([
    'title' => null,
])

@php
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
    <x-auth-header/>

    <main class="flex-1 flex items-center justify-center px-4 py-8 sm:py-12" data-testid="auth-main">
        <div class="w-full" style="max-width: var(--container-narrow);">
            {{ $slot }}
        </div>
    </main>

    <x-app-footer variant="auth"/>

    @livewireScripts
</body>
</html>
