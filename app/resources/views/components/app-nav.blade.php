@php
    /*
     * Itens do menu principal. Decisões PO consolidadas em ux-specs §1 e §6:
     *   - Minhas Empresas, Adicionar Empresa, Diagnósticos, Conta: ativos.
     *   - Histórico: disabled, tooltip "Em breve — Onda 2".
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
            'href' => route('conta'),
            'active' => request()->routeIs('conta'),
            'disabled' => false,
            'dusk' => 'app-nav-conta',
        ],
    ];

    // STORY-075 — "Planos" só existe com a cobrança ligada (STORY-073 CA-1).
    if (\Illuminate\Support\Facades\Route::has('planos') && \App\Models\CobrancaConfig::ligada()) {
        array_splice($items, 3, 0, [[
            'label' => 'Planos',
            'icon' => 'tag',
            'href' => route('planos'),
            'active' => request()->routeIs('planos'),
            'disabled' => false,
            'dusk' => 'app-nav-planos',
        ]]);
    }

    // STORY-081 — "Meu plano", logo depois de "Planos", também só com a cobrança ligada.
    if (\Illuminate\Support\Facades\Route::has('meu-plano') && \App\Models\CobrancaConfig::ligada()) {
        $posicao = collect($items)->search(fn (array $i): bool => $i['dusk'] === 'app-nav-planos');
        array_splice($items, $posicao === false ? 3 : $posicao + 1, 0, [[
            'label' => 'Meu plano',
            'icon' => 'user',
            'href' => route('meu-plano'),
            'active' => request()->routeIs('meu-plano'),
            'disabled' => false,
            'dusk' => 'app-nav-meu-plano',
        ]]);
    }

    /*
     * STORY-065 / STORY-067 — cada item da área de administração só aparece para
     * quem tem a permissão dele. Sem nenhum, a seção some.
     */
    $usuarioNav = auth()->user();
    $itensAdmin = [];
    if ($usuarioNav instanceof \App\Models\Usuario) {
        $itensAdmin = array_values(array_filter([
            [
                'permissao' => \App\Support\PermissaoAdmin::VerUsuarios,
                'label' => 'Usuários',
                'icon' => 'users',
                'href' => route('backoffice.usuarios'),
                'active' => request()->routeIs('backoffice.usuarios'),
                'disabled' => false,
                'dusk' => 'app-nav-backoffice-usuarios',
            ],
            [
                'permissao' => \App\Support\PermissaoAdmin::GerenciarAdmins,
                'label' => 'Administradores',
                'icon' => 'user',
                'href' => route('backoffice.administradores'),
                'active' => request()->routeIs('backoffice.administradores'),
                'disabled' => false,
                'dusk' => 'app-nav-backoffice-administradores',
            ],
            [
                'permissao' => \App\Support\PermissaoAdmin::EditarGlossario,
                'label' => 'Glossário',
                'icon' => 'book',
                'href' => route('backoffice.glossario'),
                'active' => request()->routeIs('backoffice.glossario'),
                'disabled' => false,
                'dusk' => 'app-nav-backoffice-glossario',
            ],
            [
                'permissao' => \App\Support\PermissaoAdmin::EditarRecomendacoes,
                'label' => 'Recomendações',
                'icon' => 'message',
                'href' => route('backoffice.recomendacoes'),
                'active' => request()->routeIs('backoffice.recomendacoes'),
                'disabled' => false,
                'dusk' => 'app-nav-backoffice-recomendacoes',
            ],
            [
                'permissao' => \App\Support\PermissaoAdmin::GerenciarPlanos,
                'label' => 'Planos e pacotes',
                'icon' => 'tag',
                'href' => route('backoffice.planos'),
                'active' => request()->routeIs('backoffice.planos'),
                'disabled' => false,
                'dusk' => 'app-nav-backoffice-planos',
            ],
            [
                'permissao' => \App\Support\PermissaoAdmin::GerenciarCupons,
                'label' => 'Cupons',
                'icon' => 'tag',
                'href' => route('backoffice.cupons'),
                'active' => request()->routeIs('backoffice.cupons'),
                'disabled' => false,
                'dusk' => 'app-nav-backoffice-cupons',
            ],
            [
                'permissao' => \App\Support\PermissaoAdmin::GerenciarFinanceiro,
                'label' => 'Financeiro',
                'icon' => 'tag',
                'href' => route('backoffice.financeiro'),
                'active' => request()->routeIs('backoffice.financeiro'),
                'disabled' => false,
                'dusk' => 'app-nav-backoffice-financeiro',
            ],
        ], fn (array $item): bool => $usuarioNav->podeUsarBackoffice($item['permissao'])));
    }

    $grupos = array_filter([
        ['titulo' => null, 'aria' => 'Navegação principal', 'dusk' => null, 'itens' => $items],
        ['titulo' => 'Administração', 'aria' => 'Administração', 'dusk' => 'app-nav-admin', 'itens' => $itensAdmin],
    ], fn ($g) => $g['itens'] !== []);

    $icons = [
        'building' => '<path d="M4 22V4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v18M4 22h16M9 6h2M13 6h2M9 10h2M13 10h2M9 14h2M13 14h2M9 18h2M13 18h2"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'chart' => '<path d="M3 3v18h18M7 14l4-4 4 4 6-6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
        'tag' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.5"/>',
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-6 6.5-6s6.5 2.5 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2.2.6 3.5 2.8 3.5 6"/>',
    ];
@endphp

{{-- Drawer overlay (mobile, < 1024px). Quando navOpen do x-data do shell estiver true, escurece e bloqueia interação. --}}
<div x-show="navOpen"
     x-cloak
     x-transition.opacity.duration.200ms
     class="print:hidden fixed inset-0 z-40 bg-[color:var(--color-primary)]/40 lg:hidden"
     @click="navOpen = false"
     aria-hidden="true">
</div>

{{-- Sidebar fixa em ≥ 1024px; drawer off-canvas em < 1024px. Mesmo markup, mesmo conteúdo. --}}
<aside id="app-nav-drawer"
       data-testid="app-nav"
       class="print:hidden fixed top-0 left-0 z-50 h-full w-[280px] max-w-[80vw] bg-[color:var(--color-surface)] border-r border-[color:var(--color-border)] transform transition-transform duration-200 ease-out lg:translate-x-0 lg:sticky lg:top-0 lg:z-auto lg:w-60 lg:h-screen lg:max-w-none lg:transform-none"
       :class="navOpen ? 'translate-x-0' : '-translate-x-full'"
       {{--
           STORY-059 CA-2 — drawer fechado sai da tabulação E da árvore de
           acessibilidade; sidebar visível (≥1024px) fica em ambas.

           `inert` é o que tira os filhos do foco — `aria-hidden` sozinho os
           esconde do leitor de tela mas deixa o Tab entrar, que é exatamente a
           combinação que o gate acusa. Os dois andam juntos e são aplicados
           por `sincronizarInerte()` (definido no x-data do shell), porque a
           condição depende do breakpoint, não só de `navOpen`.
       --}}
       x-effect="sincronizarInerte($el)"
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

    @foreach ($grupos as $grupo)
        <nav class="flex flex-col gap-1 p-4 @if ($grupo['titulo']) border-t border-[color:var(--color-border)] @endif"
             aria-label="{{ $grupo['aria'] }}"
             @if ($grupo['dusk']) dusk="{{ $grupo['dusk'] }}" @endif>
            @if ($grupo['titulo'])
                <p class="px-3 mb-1 mt-0 text-xs font-medium uppercase tracking-wide text-[color:var(--color-secondary)]">
                    {{ $grupo['titulo'] }}
                </p>
            @endif
            @foreach ($grupo['itens'] as $item)
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
    @endforeach
</aside>
