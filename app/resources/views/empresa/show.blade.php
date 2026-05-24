@php
    $nomeExibicao = $empresa->nome_fantasia ?: $empresa->razao_social;
    $titulo = $empresa->razao_social;
@endphp

<x-layouts.app :title="$titulo"
               :breadcrumb="[
                   ['label' => 'Minhas Empresas', 'url' => route('home')],
                   ['label' => $nomeExibicao, 'url' => route('empresas.show', $empresa)],
               ]">

    <x-page-header :title="$nomeExibicao">
        <x-slot:actions>
            <x-button variant="primary" type="button"
                      :disabled="true"
                      title="Em breve — Onda 2"
                      dusk="empresa-show-iniciar-diagnostico">
                Iniciar diagnóstico
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- H1 oficial mostra nome fantasia/razao social. Mantemos um marcador adicional
         para Dusk compatível com testes anteriores (dusk="empresa-show-titulo"). --}}
    <p class="sr-only" dusk="empresa-show-titulo">{{ $empresa->razao_social }}</p>

    <x-card>
        <div class="mb-4">
            <span class="pill" dusk="empresa-show-fonte">
                Fonte: {{ $empresa->fonte_enriquecimento->rotulo() }}
            </span>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-[max-content_1fr] gap-x-6 gap-y-2 m-0">
            <dt class="text-xs uppercase tracking-wider font-semibold text-[color:var(--color-secondary)] sm:pt-0.5">Tipo do documento</dt>
            <dd class="m-0 text-[color:var(--color-primary)]" dusk="empresa-show-tipo">{{ strtoupper($empresa->tipo_documento->value) }}</dd>

            <dt class="text-xs uppercase tracking-wider font-semibold text-[color:var(--color-secondary)] sm:pt-0.5">{{ strtoupper($empresa->tipo_documento->value) }}</dt>
            <dd class="m-0 text-[color:var(--color-primary)]" dusk="empresa-show-documento">{{ $empresa->documentoFormatado() }}</dd>

            <dt class="text-xs uppercase tracking-wider font-semibold text-[color:var(--color-secondary)] sm:pt-0.5">Razão social</dt>
            <dd class="m-0 text-[color:var(--color-primary)]" dusk="empresa-show-razao-social">{{ $empresa->razao_social }}</dd>

            <dt class="text-xs uppercase tracking-wider font-semibold text-[color:var(--color-secondary)] sm:pt-0.5">Nome fantasia</dt>
            <dd class="m-0 text-[color:var(--color-primary)]" dusk="empresa-show-nome-fantasia">{{ $empresa->nome_fantasia ?: '—' }}</dd>

            <dt class="text-xs uppercase tracking-wider font-semibold text-[color:var(--color-secondary)] sm:pt-0.5">CNAE</dt>
            <dd class="m-0 text-[color:var(--color-primary)]" dusk="empresa-show-cnae">{{ $empresa->cnae ?: '—' }}</dd>

            <dt class="text-xs uppercase tracking-wider font-semibold text-[color:var(--color-secondary)] sm:pt-0.5">Município / UF</dt>
            <dd class="m-0 text-[color:var(--color-primary)]" dusk="empresa-show-localizacao">{{ $empresa->municipio }} / {{ $empresa->uf }}</dd>

            <dt class="text-xs uppercase tracking-wider font-semibold text-[color:var(--color-secondary)] sm:pt-0.5">Situação cadastral</dt>
            <dd class="m-0 text-[color:var(--color-primary)]" dusk="empresa-show-situacao">{{ $empresa->situacao_cadastral->rotulo() }}</dd>

            <dt class="text-xs uppercase tracking-wider font-semibold text-[color:var(--color-secondary)] sm:pt-0.5">Data de fundação</dt>
            <dd class="m-0 text-[color:var(--color-primary)]" dusk="empresa-show-data-fundacao">{{ $empresa->data_fundacao?->format('d/m/Y') ?: '—' }}</dd>
        </dl>
    </x-card>

    {{-- CA-18: Voltar para Minhas Empresas (secondary com ícone seta-esquerda). --}}
    <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3">
        <x-button :href="route('home')" variant="secondary" dusk="empresa-show-voltar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Voltar para Minhas Empresas
        </x-button>
    </div>
</x-layouts.app>
