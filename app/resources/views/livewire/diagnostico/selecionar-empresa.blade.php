<div>
    <x-page-header :title="'Selecione uma empresa'"
                   :subtitle="'Escolha a empresa que receberá o diagnóstico.'"/>

    @if ($empresas->isEmpty())
        <x-card class="text-center" dusk="selecionar-empresa-vazio">
            <h2 class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] mb-2">
                Você ainda não cadastrou nenhuma empresa.
            </h2>
            <p class="text-[color:var(--color-secondary)] mb-6 mt-0">
                Cadastre uma empresa primeiro para fazer o diagnóstico.
            </p>
            <x-button :href="route('empresas.nova')" variant="primary"
                      dusk="selecionar-empresa-cta-cadastrar">
                Cadastrar primeira empresa
            </x-button>
        </x-card>
    @else
        <ul class="grid grid-cols-1 md:grid-cols-2 gap-4 list-none p-0 m-0"
            dusk="selecionar-empresa-lista">
            @foreach ($empresas as $empresa)
                <li dusk="selecionar-empresa-item-{{ $empresa->id }}">
                    <x-card as="article" class="h-full flex flex-col gap-3">
                        <h3 class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0">
                            {{ $empresa->nome_fantasia ?: $empresa->razao_social }}
                        </h3>
                        <div class="flex flex-col gap-1 text-sm text-[color:var(--color-secondary)]">
                            <span>{{ $empresa->documentoMascarado() }}</span>
                            <span>{{ $empresa->municipio }} / {{ $empresa->uf }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-auto pt-2">
                            @if ($empresa->ultimo_diagnostico_id)
                                <x-button :href="route('diagnosticos.show', $empresa->ultimo_diagnostico_id)"
                                          variant="secondary" size="sm"
                                          dusk="selecionar-empresa-ver-diagnostico-{{ $empresa->id }}">
                                    Ver último diagnóstico
                                </x-button>
                            @endif
                            <x-button :href="route('diagnosticos.novo', $empresa)" variant="primary" size="sm"
                                      dusk="selecionar-empresa-cta-{{ $empresa->id }}">
                                {{ $empresa->ultimo_diagnostico_id ? 'Refazer diagnóstico' : 'Fazer diagnóstico' }}
                            </x-button>
                        </div>
                    </x-card>
                </li>
            @endforeach
        </ul>
    @endif
</div>
