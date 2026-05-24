<x-layouts.sistema title="Email confirmado">
    <x-card>
        <div class="mx-auto w-16 h-16 rounded-full bg-[color:var(--color-neutral)] flex items-center justify-center mb-4"
             aria-hidden="true">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="text-[color:var(--color-tertiary)]">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <h1 class="text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)] mb-2"
            dusk="email-confirmado-titulo">
            Email confirmado
        </h1>
        <p class="text-[color:var(--color-secondary)] mb-6 mt-0" dusk="email-confirmado-msg">
            Seu email foi confirmado. Você já pode fazer login.
        </p>
        <x-button :href="route('login')" variant="primary" wire:navigate>
            Ir para o login
        </x-button>
    </x-card>
</x-layouts.sistema>
