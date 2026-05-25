@php
    /*
     * Itens do menu principal. Decisões PO consolidadas em ux-specs §1 e §6:
     *   - Minhas Empresas, Adicionar Empresa: ativos.
     *   - Diagnósticos, Histórico, Conta: disabled, tooltip "Em breve — Onda 2".
     *   - /empresas/{id}/show mantém "Minhas Empresas" ativo (mesmo ramo do menu).
     */
    $items = [
        [
            'label' => 'Minhas Empresas',
            'icon' => 'building',
            'href' => route('home'),
            'active' => request()->routeIs('home') || request()->routeIs('empresas.show'),
            'disabled' => false,
            'dusk' => 'app-nav-minhas-empresas',
        ],
        [
            'label' => 'Adicionar Empresa',
            'icon' => 'plus',
            'href' => route('empresas.nova'),
            'active' => request()->routeIs('empresas.nova'),
            'disabled' => false,
            'dusk' => 'app-nav-adicionar-empresa',
        ],
        [
            'label' => 'Diagnósticos',
            'icon' => 'chart',
            'href' => route('diagnosticos.selecionar'),
            'active' => request()->routeIs('diagnosticos.*'),
            'disabled' => false,
            'dusk' => 'app-nav-diagnosticos',
        ],
        [
            'label' => 'Histórico',
            'icon' => 'clock',
            'href' => '#',
            'active' => false,
            'disabled' => true,
            'tooltip' => 'Em breve — Onda 2',
            'dusk' => 'app-nav-historico',
        ],
        [
            'label' => 'Conta',
            'icon' => 'user',
            'href' => '#',
            'active' => false,
            'disabled' => true,
            'tooltip' => 'Em breve — Onda 2',
            'dusk' => 'app-nav-conta',
        ],
    ];

    $icons = [
        'building' => '<path d="M4 22V4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v18M4 22h16M9 6h2M13 6h2M9 10h2M13 10h2M9 14h2M13 14h2M9 18h2M13 18h2"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'chart' => '<path d="M3 3v18h18M7 14l4-4 4 4 6-6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/>',
    ];
@endphp

{{-- Drawer overlay (mobile, < 1024px). Quando navOpen do x-data do shell estiver true, escurece e bloqueia interação. --}}
<div x-show="navOpen"
     x-cloak
     x-transition.opacity.duration.200ms
     class="fixed inset-0 z-40 bg-[color:var(--color-primary)]/40 lg:hidden"
     @click="navOpen = false"
     aria-hidden="true">
</div>

{{-- Sidebar fixa em ≥ 1024px; drawer off-canvas em < 1024px. Mesmo markup, mesmo conteúdo. --}}
<aside id="app-nav-drawer"
       data-testid="app-nav"
       class="fixed top-0 left-0 z-50 h-full w-[280px] max-w-[80vw] bg-[color:var(--color-surface)] border-r border-[color:var(--color-border)] transform transition-transform duration-200 ease-out lg:translate-x-0 lg:sticky lg:top-0 lg:z-auto lg:w-60 lg:h-screen lg:max-w-none lg:transform-none"
       :class="navOpen ? 'translate-x-0' : '-translate-x-full'"
       :aria-hidden="navOpen ? 'false' : 'true'"
       aria-label="Navegação principal">

    {{-- Cabeçalho do drawer (mobile só): logo + botão fechar. Desktop não precisa. --}}
    <div class="flex items-center justify-between h-16 px-4 border-b border-[color:var(--color-border)] lg:hidden">
        <x-logo :size="28" :wordmark="true"/>
        <button type="button"
                class="inline-flex items-center justify-center w-11 h-11 rounded-md text-[color:var(--color-primary)] hover:bg-[color:var(--color-neutral)]"
                @click="navOpen = false"
                aria-label="Fechar menu"
                dusk="app-nav-fechar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <line x1="6" y1="6" x2="18" y2="18"/>
                <line x1="18" y1="6" x2="6" y2="18"/>
            </svg>
        </button>
    </div>

    <nav class="flex flex-col gap-1 p-4" aria-label="Navegação principal">
        @foreach ($items as $item)
            @if ($item['disabled'])
                <span class="nav-item is-disabled"
                      aria-disabled="true"
                      tabindex="-1"
                      title="{{ $item['tooltip'] }}"
                      data-tooltip="{{ $item['tooltip'] }}"
                      dusk="{{ $item['dusk'] }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        {!! $icons[$item['icon']] !!}
                    </svg>
                    <span>{{ $item['label'] }}</span>
                </span>
            @else
                <a href="{{ $item['href'] }}"
                   class="nav-item @if ($item['active']) is-active @endif"
                   @if ($item['active']) aria-current="page" @endif
                   dusk="{{ $item['dusk'] }}"
                   @click="navOpen = false">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        {!! $icons[$item['icon']] !!}
                    </svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </nav>
</aside>
