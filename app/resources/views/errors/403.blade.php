<x-layouts.sistema title="Acesso negado">
    <x-card>
        <p class="text-xs font-semibold uppercase tracking-wider text-[color:var(--color-secondary)] mb-2">
            Erro 403
        </p>
        <h1 class="text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)] mb-2"
            dusk="erro-403-titulo">
            Acesso negado
        </h1>
        <p class="text-[color:var(--color-secondary)] mb-6 mt-0">
            Você não tem permissão para acessar este recurso.
        </p>
        @auth
            <x-button :href="route('home')" variant="primary" wire:navigate>
                Voltar para Minhas Empresas
            </x-button>
        @else
            <x-button :href="route('login')" variant="primary">
                Entrar
            </x-button>
        @endauth
    </x-card>
</x-layouts.sistema>
