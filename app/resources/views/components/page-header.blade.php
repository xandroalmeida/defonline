@props([
    'title' => null,
    'detalhe' => null,
    'subtitle' => null,
    'tituloDusk' => null,
    // Página fora do layout app (sem header global): o título fica no conteúdo, como antes.
    'naPagina' => false,
])

@php
    /*
     * Título da página. Vive no header global (`#app-titulo`, em x-app-header), não no
     * conteúdo: o <h1> é levado para lá com `x-teleport`, que mantém o vínculo com o
     * componente Livewire — o título continua reativo e `$refs.titulo` continua
     * resolvendo para quem foca o título ao trocar de estado.
     *
     * Título por prop (`title`, escapado) ou pelo slot padrão (quando tem marcação).
     *
     * Detalhe e subtítulo: no header a partir de `lg`, numa linha só (truncada, texto
     * inteiro no `title`); abaixo de `lg` o header só comporta o título, então eles
     * ficam no topo do conteúdo. Ações ficam sempre no conteúdo.
     */
    $temTitulo = filled($title) || $slot->isNotEmpty();
    $tituloTexto = filled($title) ? (string) $title : trim(preg_replace('/\s+/', ' ', strip_tags((string) $slot)));
    $linhaApoio = collect([$detalhe, $subtitle])->filter(fn ($t) => filled($t))->implode(' · ');
    $temAcoes = isset($actions);
    $temApoio = $linhaApoio !== '';
@endphp

@if ($naPagina)
    <header {{ $attributes->merge(['class' => 'flex flex-col gap-2 mb-6 sm:flex-row sm:items-end sm:justify-between']) }}>
        <div class="flex flex-col gap-1">
            @if ($temTitulo)
                <h1 x-ref="titulo" tabindex="-1" @if ($tituloDusk) dusk="{{ $tituloDusk }}" @endif
                    class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">
                    @if (filled($title)){{ $title }}@else{{ $slot }}@endif
                    @isset($badge)
                        <span class="align-middle text-[length:var(--text-body)] font-normal ml-2">{{ $badge }}</span>
                    @endisset
                </h1>
            @endif
            @if (filled($detalhe))
                <p class="text-[color:var(--color-secondary)] text-sm m-0" data-testid="page-header-detalhe">{{ $detalhe }}</p>
            @endif
            @if (filled($subtitle))
                <p class="text-[color:var(--color-secondary)] text-sm m-0">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($temAcoes)
            <div class="flex gap-3 flex-shrink-0">{{ $actions }}</div>
        @endif
    </header>
@else

@if ($temTitulo)
    <template x-teleport="#app-titulo">
        <div class="flex flex-col justify-center gap-0.5 min-w-0" data-testid="page-header">
            <h1 x-ref="titulo" tabindex="-1" @if ($tituloDusk) dusk="{{ $tituloDusk }}" @endif
                title="{{ $tituloTexto }}"
                class="m-0 truncate text-base lg:text-xl font-medium leading-tight tracking-tight text-[color:var(--color-primary)] focus:outline-none">
                @if (filled($title)){{ $title }}@else{{ $slot }}@endif
                @isset($badge)
                    <span class="align-middle text-sm font-normal ml-2">{{ $badge }}</span>
                @endisset
            </h1>
            @if ($temApoio)
                <p class="hidden lg:block m-0 truncate text-[0.8125rem] leading-snug text-[color:var(--color-secondary)]"
                   title="{{ $linhaApoio }}">
                    @if (filled($detalhe))
                        <span data-testid="page-header-detalhe">{{ $detalhe }}</span>@if (filled($subtitle)) · @endif
                    @endif
                    @if (filled($subtitle))
                        <span>{{ $subtitle }}</span>
                    @endif
                </p>
            @endif
        </div>
    </template>
@endif

@if ($temApoio || $temAcoes)
    <div {{ $attributes->class([
        'flex flex-col gap-2 mb-6 sm:flex-row sm:items-end sm:justify-between',
        'lg:hidden' => ! $temAcoes,
    ]) }}>
        <div class="flex flex-col gap-1 min-w-0 lg:hidden">
            @if (filled($detalhe))
                <p class="text-[color:var(--color-secondary)] text-sm m-0">{{ $detalhe }}</p>
            @endif
            @if (filled($subtitle))
                <p class="text-[color:var(--color-secondary)] text-sm m-0">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($temAcoes)
            <div class="flex flex-wrap gap-3 flex-shrink-0 sm:ml-auto">
                {{ $actions }}
            </div>
        @endif
    </div>
@endif
@endif
