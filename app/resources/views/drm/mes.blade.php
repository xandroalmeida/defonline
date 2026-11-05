<x-layouts.app :title="ucfirst($competencia->rotulo())"
               :breadcrumb="[
                   ['label' => 'Minhas Empresas', 'url' => route('home')],
                   ['label' => $empresa->nomeExibicao(), 'url' => route('empresas.show', $empresa)],
                   ['label' => ucfirst($competencia->rotulo()), 'url' => '#'],
               ]">
    <x-page-header :title="ucfirst($competencia->rotulo())" :subtitle="$empresa->nomeExibicao()"/>

    <x-card>
        <p class="m-0 text-[color:var(--color-primary)]" dusk="drm-mes-fechado">
            Mês fechado em {{ $lancamento->gerado_em->format('d/m/Y') }}.
        </p>
        <p class="mt-2 mb-0 text-sm text-[color:var(--color-secondary)]">
            A demonstração deste mês aparece aqui em breve.
        </p>
    </x-card>

    <div class="mt-6">
        <x-button :href="route('empresas.show', $empresa)" variant="secondary">Voltar para a empresa</x-button>
    </div>
</x-layouts.app>
