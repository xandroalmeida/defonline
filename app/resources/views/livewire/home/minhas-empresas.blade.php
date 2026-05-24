<div>
    <x-page-header :title="'Minhas Empresas'"
                   :subtitle="$empresas->isEmpty()
                       ? 'Você ainda não cadastrou nenhuma empresa.'
                       : ($empresas->count() === 1 ? 'Você tem 1 empresa cadastrada.' : 'Você tem ' . $empresas->count() . ' empresas cadastradas.')">
        <x-slot:actions>
            {{-- CA-14: botão "Adicionar empresa" sempre visível, em todos os estados. --}}
            <x-button :href="route('empresas.nova')" variant="primary"
                      dusk="minhas-empresas-cta-adicionar">
                <span aria-hidden="true" class="text-base leading-none">+</span>
                Adicionar empresa
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Saudação preservada para compatibilidade com testes Dusk existentes. --}}
    <p class="sr-only" dusk="saudacao">Olá, {{ $usuario->primeiroNome() }}</p>

    @if ($empresas->isEmpty())
        <x-card class="text-center" dusk="minhas-empresas-vazio">
            <div class="mx-auto w-16 h-16 rounded-full bg-[color:var(--color-neutral)] flex items-center justify-center mb-4"
                 aria-hidden="true">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5" class="text-[color:var(--color-secondary)]">
                    <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/>
                </svg>
            </div>
            <h2 class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] mb-2">
                Nenhuma empresa cadastrada ainda
            </h2>
            <p class="text-[color:var(--color-secondary)] mb-6 mt-0">
                Você precisa cadastrar pelo menos uma empresa para usar o diagnóstico.
            </p>
            <x-button :href="route('empresas.nova')" variant="primary"
                      dusk="minhas-empresas-cta-cadastrar">
                Cadastrar primeira empresa
            </x-button>
        </x-card>
    @else
        <ul class="grid grid-cols-1 md:grid-cols-2 gap-4 list-none p-0 m-0"
            dusk="minhas-empresas-lista">
            @foreach ($empresas as $empresa)
                <li dusk="minhas-empresas-item-{{ $empresa->id }}">
                    <x-card as="article" class="h-full flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <h3 class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0"
                                dusk="minhas-empresas-nome-{{ $empresa->id }}">
                                {{ $empresa->nome_fantasia ?: $empresa->razao_social }}
                            </h3>
                            <span class="pill" dusk="minhas-empresas-fonte-{{ $empresa->id }}">
                                {{ $empresa->fonteBadge() }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-1 text-sm text-[color:var(--color-secondary)]">
                            <span dusk="minhas-empresas-doc-{{ $empresa->id }}">
                                {{ $empresa->documentoMascarado() }}
                            </span>
                            <span dusk="minhas-empresas-local-{{ $empresa->id }}">
                                {{ $empresa->municipio }} / {{ $empresa->uf }}
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-auto pt-2">
                            <x-button :href="route('empresas.show', $empresa)" variant="secondary" size="sm"
                                      dusk="minhas-empresas-ver-{{ $empresa->id }}">
                                Ver detalhes
                            </x-button>
                            <x-button variant="ghost" size="sm" type="button"
                                      :disabled="true"
                                      title="Em breve — Onda 2"
                                      dusk="minhas-empresas-diagnostico-{{ $empresa->id }}">
                                Iniciar diagnóstico
                            </x-button>
                        </div>
                    </x-card>
                </li>
            @endforeach
        </ul>
    @endif
</div>
