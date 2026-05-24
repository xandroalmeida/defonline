@props([
    'cta' => null,
])

@php
    /*
     * Header simples para rotas públicas (cadastro/login antes de logar).
     * Mostra logo + link contextual (Entrar quando em /cadastro, Criar conta quando em /login).
     * Quando $cta vier como ['label' => '...', 'href' => '...'], usa esse.
     */
    if (! $cta) {
        if (request()->routeIs('cadastro')) {
            $cta = ['label' => 'Entrar', 'href' => route('login')];
        } elseif (request()->routeIs('login')) {
            $cta = ['label' => 'Criar conta', 'href' => route('cadastro')];
        }
    }

    $env = config('app.env');
    $showEnvPill = $env !== 'production' && $env !== 'testing';
    $envPillLabel = match ($env) {
        'local' => 'local',
        'staging', 'homolog', 'homologation' => 'homol',
        default => $env,
    };
@endphp

<header data-testid="auth-header"
        class="flex items-center justify-between gap-3 h-16 px-4 sm:px-6 bg-[color:var(--color-surface)] border-b border-[color:var(--color-border)]">
    <div class="flex items-center gap-3">
        <a href="{{ url('/') }}" class="flex items-center gap-2 text-[color:var(--color-primary)] no-underline"
           dusk="auth-header-logo">
            <x-logo :size="32" :wordmark="'mobile'"/>
        </a>
        @if ($showEnvPill)
            <span class="pill" data-testid="env-pill" dusk="env-pill">{{ $envPillLabel }}</span>
        @endif
    </div>

    @if ($cta)
        <a href="{{ $cta['href'] }}" class="btn btn--ghost btn--sm"
           dusk="auth-header-cta">
            {{ $cta['label'] }}
        </a>
    @endif
</header>
