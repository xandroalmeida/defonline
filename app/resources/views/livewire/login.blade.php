<div>
    <x-card class="!p-6 sm:!p-8">
        <h1 class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] mb-2"
            dusk="login-titulo">
            Entrar
        </h1>
        <p class="text-[color:var(--color-secondary)] text-sm mb-6 mt-0">
            Acesse sua conta para gerenciar suas empresas.
        </p>

        @if (session('cadastro_sucesso'))
            <div class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-neutral)] text-[color:var(--color-primary)] text-sm px-4 py-3 mb-4"
                 dusk="cadastro-sucesso">
                {{ session('cadastro_sucesso') }}
            </div>
        @endif

        @if (session('logout_sucesso'))
            <div class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-neutral)] text-[color:var(--color-primary)] text-sm px-4 py-3 mb-4"
                 dusk="logout-sucesso">
                {{ session('logout_sucesso') }}
            </div>
        @endif

        <form wire:submit="submit" novalidate class="flex flex-col gap-4">
            <div>
                <x-label for="email">Email</x-label>
                <x-input type="email" id="email" wire:model.defer="email"
                         autocomplete="email" dusk="login-email"/>
                @error('email')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-login">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="senha">Senha</x-label>
                <x-input type="password" id="senha" wire:model.defer="senha"
                         autocomplete="current-password" dusk="login-senha"/>
                @error('senha')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0">{{ $message }}</p>
                @enderror
            </div>

            <x-button type="submit" variant="primary" class="w-full mt-2" dusk="login-submit">
                Entrar
            </x-button>
        </form>

        <div class="mt-6 flex flex-col gap-2 text-sm text-center">
            <a href="{{ route('email.confirmar-erro') }}" wire:navigate class="link"
               dusk="login-reenviar-email">
                Reenviar email de confirmação
            </a>
            <p class="m-0 text-[color:var(--color-secondary)]">
                Ainda não tem conta?
                <a href="{{ route('cadastro') }}" wire:navigate class="link">Criar conta</a>
            </p>
        </div>
    </x-card>
</div>
