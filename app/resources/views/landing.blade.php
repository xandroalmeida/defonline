@php
    /*
     * STORY-024 — Landing pública simples.
     *
     * Substitui a página de debug `/` da STORY-007 (welcome + livewire/hello-world).
     * View standalone porque o header da landing exibe dois CTAs (Entrar ghost +
     * Criar conta primary) — caso único na rota pública; o `<x-auth-header>` foi
     * mantido como está e só serve cadastro/login.
     *
     * Reusa do design-system v1 (STORY-019): <x-logo>, <x-button>, <x-footer-version>.
     * Copy normativa fixada por PO em STORY-024 §CA-4.
     */
    $env = config('app.env');
    $showEnvPill = $env !== 'production' && $env !== 'testing';
    $envPillLabel = match ($env) {
        'local' => 'local',
        'staging', 'homolog', 'homologation' => 'homol',
        default => $env,
    };
@endphp

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEFOnline — diagnóstico estratégico para sua empresa</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col">

    <header data-testid="landing-header"
            class="flex items-center justify-between gap-3 h-16 px-4 sm:px-6 bg-[color:var(--color-surface)] border-b border-[color:var(--color-border)]">
        <div class="flex items-center gap-3">
            <a href="{{ route('landing') }}"
               class="flex items-center gap-2 text-[color:var(--color-primary)] no-underline"
               dusk="landing-header-logo">
                <x-logo :size="32" :wordmark="'mobile'"/>
            </a>
            @if ($showEnvPill)
                <span class="pill" data-testid="env-pill" dusk="env-pill">{{ $envPillLabel }}</span>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('login') }}"
               class="btn btn--ghost btn--sm hidden min-[480px]:inline-flex"
               dusk="landing-header-entrar">Entrar</a>
            <a href="{{ route('cadastro') }}"
               class="btn btn--primary btn--sm"
               dusk="landing-header-cadastro">Criar conta</a>
        </div>
    </header>

    <main id="conteudo" data-testid="landing-main"
          class="flex-1 px-4 sm:px-6 pt-16 sm:pt-24 pb-12">
        <div class="mx-auto w-full" style="max-width: 720px;">
            <h1 class="font-light leading-[1.05] tracking-tight text-[color:var(--color-primary)] text-[length:var(--text-h1)] sm:text-[length:var(--text-display)]">
                Diagnóstico estratégico para sua empresa.
            </h1>
            <p class="mt-6 text-[color:var(--color-secondary)] text-[length:var(--text-body)] max-w-[60ch]">
                Respostas claras sobre como sua indústria está em 14 indicadores essenciais — em minutos, sem consultor caro, sem planilha.
            </p>
            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <x-button variant="secondary" :href="route('login')" dusk="landing-cta-login">
                    Já tenho conta
                </x-button>
                <x-button variant="primary" :href="route('cadastro')" dusk="landing-cta-cadastro">
                    Criar conta grátis
                </x-button>
            </div>
        </div>
    </main>

    <footer data-testid="landing-footer"
            class="border-t border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-4 sm:px-6 py-5">
        <div class="max-w-[960px] mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm text-[color:var(--color-secondary)]">
            <span>© DEFOnline {{ now()->year }}</span>
            <x-footer-version/>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
