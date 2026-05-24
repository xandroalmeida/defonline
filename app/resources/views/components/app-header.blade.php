@props([
    'usuario' => null,
])

@php
    $env = config('app.env');
    $showEnvPill = $env !== 'production' && $env !== 'testing';
    $envPillLabel = match ($env) {
        'local' => 'local',
        'staging', 'homolog', 'homologation' => 'homol',
        default => $env,
    };
    $usuario = $usuario ?? auth()->user();
    $inicial = $usuario ? mb_strtoupper(mb_substr($usuario->primeiroNome(), 0, 1)) : '?';
@endphp

<header data-testid="app-header"
        class="sticky top-0 z-30 flex items-center justify-between gap-3 h-16 px-4 sm:px-6 bg-[color:var(--color-surface)] border-b border-[color:var(--color-border)]">

    <div class="flex items-center gap-3 min-w-0">
        {{-- Hamburger só aparece em < 1024px (controlado pelo Alpine root do shell). --}}
        <button type="button"
                class="inline-flex items-center justify-center w-11 h-11 lg:hidden rounded-md text-[color:var(--color-primary)] hover:bg-[color:var(--color-neutral)]"
                aria-controls="app-nav-drawer"
                :aria-expanded="navOpen ? 'true' : 'false'"
                @click="navOpen = !navOpen"
                aria-label="Abrir menu"
                dusk="app-header-hamburger">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <line x1="4" y1="7" x2="20" y2="7"/>
                <line x1="4" y1="12" x2="20" y2="12"/>
                <line x1="4" y1="17" x2="20" y2="17"/>
            </svg>
        </button>

        <a href="{{ route('home') }}" class="flex items-center gap-2 text-[color:var(--color-primary)] no-underline"
           dusk="app-header-logo">
            <x-logo :size="32" :wordmark="'mobile'"/>
        </a>

        @if ($showEnvPill)
            <span class="pill" data-testid="env-pill" dusk="env-pill">{{ $envPillLabel }}</span>
        @endif
    </div>

    @if ($usuario)
        <div class="relative" x-data="{ open: false }"
             @keydown.escape.window="open = false"
             @click.outside="open = false">
            <button type="button"
                    class="inline-flex items-center gap-2 h-11 px-3 rounded-md hover:bg-[color:var(--color-neutral)]"
                    @click="open = !open"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-haspopup="menu"
                    dusk="app-header-conta-toggle">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-[color:var(--color-neutral)] border border-[color:var(--color-border)] text-[color:var(--color-primary)] text-sm font-medium"
                      aria-hidden="true">
                    {{ $inicial }}
                </span>
                <span class="hidden min-[640px]:inline text-[color:var(--color-primary)] font-medium text-sm"
                      dusk="app-header-saudacao">
                    Olá, {{ $usuario->primeiroNome() }}
                </span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>

            <div x-show="open"
                 x-cloak
                 x-transition.opacity.duration.150ms
                 role="menu"
                 class="absolute right-0 mt-2 w-56 bg-[color:var(--color-surface)] border border-[color:var(--color-border)] rounded-lg shadow-md py-1 overflow-hidden"
                 dusk="app-header-conta-menu">
                <button type="button"
                        role="menuitem"
                        disabled
                        aria-disabled="true"
                        title="Em breve — Onda 2"
                        class="w-full text-left px-4 py-2.5 text-sm text-[color:var(--color-secondary)] cursor-not-allowed opacity-60"
                        dusk="app-header-conta-editar">
                    Editar perfil
                </button>
                <form method="POST" action="{{ route('logout') }}" class="m-0" dusk="app-header-conta-sair-form">
                    @csrf
                    <button type="submit"
                            role="menuitem"
                            class="w-full text-left px-4 py-2.5 text-sm text-[color:var(--color-primary)] hover:bg-[color:var(--color-neutral)]"
                            dusk="app-header-conta-sair">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    @endif
</header>
