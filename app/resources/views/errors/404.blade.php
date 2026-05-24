<x-layouts.sistema title="Página não encontrada">
    <x-card>
        <p class="text-xs font-semibold uppercase tracking-wider text-[color:var(--color-secondary)] mb-2">
            Erro 404
        </p>
        <h1 class="text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)] mb-2"
            dusk="erro-404-titulo">
            Página não encontrada
        </h1>
        <p class="text-[color:var(--color-secondary)] mb-6 mt-0">
            O endereço que você abriu não existe ou foi removido.
        </p>
        @auth
            <x-button :href="route('home')" variant="primary" wire:navigate>
                Voltar para Minhas Empresas
            </x-button>
        @else
            <x-button :href="url('/')" variant="primary">
                Voltar para o início
            </x-button>
        @endauth
    </x-card>
</x-layouts.sistema>
