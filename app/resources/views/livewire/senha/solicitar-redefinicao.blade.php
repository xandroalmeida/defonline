<div>
    <x-card class="!p-6 sm:!p-8">
        <h1 class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] mb-2" dusk="senha-esqueci-titulo">Esqueci minha senha</h1>

        @if ($enviado)
            <p class="text-[color:var(--color-primary)] text-sm mb-6 mt-0" role="status" dusk="senha-esqueci-enviado">
                Se houver uma conta com este e-mail, enviamos um link para redefinir a senha. Ele vale por {{ config('auth.passwords.users.expire') }} minutos.
            </p>
        @else
            <p class="text-[color:var(--color-secondary)] text-sm mb-6 mt-0">Informe o e-mail da sua conta e enviaremos um link para criar uma nova senha.</p>

            <form wire:submit="enviar" class="flex flex-col gap-4" novalidate>
                <div>
                    <x-label for="email">E-mail</x-label>
                    <x-input type="email" id="email" wire:model="email" autocomplete="email" aria-describedby="email-erro" dusk="senha-esqueci-email"/>
                    @error('email')<p id="email-erro" class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="senha-esqueci-erro">{{ $message }}</p>@enderror
                </div>
                <x-button type="submit" variant="primary" class="w-full mt-2" wire:loading.attr="disabled" wire:target="enviar" dusk="senha-esqueci-enviar">Enviar link</x-button>
            </form>
        @endif

        <div class="mt-6 text-sm text-center">
            <a href="{{ route('login') }}" wire:navigate class="link" dusk="senha-voltar-login">Voltar para o login</a>
        </div>
    </x-card>
</div>
