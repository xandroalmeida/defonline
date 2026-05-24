<x-layouts.sistema title="Algo deu errado">
    <x-card>
        <p class="text-xs font-semibold uppercase tracking-wider text-[color:var(--color-secondary)] mb-2">
            Erro 500
        </p>
        <h1 class="text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)] mb-2"
            dusk="erro-500-titulo">
            Algo deu errado
        </h1>
        <p class="text-[color:var(--color-secondary)] mb-2 mt-0">
            Tivemos um problema inesperado ao processar sua solicitação.
        </p>
        @if (request()->header('X-Request-Id'))
            <p class="text-xs text-[color:var(--color-secondary)] mb-6 font-mono">
                ID da solicitação:
                <span dusk="erro-500-request-id">{{ request()->header('X-Request-Id') }}</span>
            </p>
        @else
            <div class="mb-6"></div>
        @endif
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
