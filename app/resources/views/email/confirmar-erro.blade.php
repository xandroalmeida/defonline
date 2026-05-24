<x-layouts.sistema title="Não foi possível confirmar">
    <x-card class="text-left">
        <h1 class="text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)] mb-3"
            dusk="email-erro-titulo">
            Não foi possível confirmar
        </h1>

        @php($motivo = session('email_confirmar_erro_motivo', 'invalido'))

        <p class="text-[color:var(--color-secondary)] mb-4 mt-0" dusk="email-erro-msg">
            @switch($motivo)
                @case('ja_confirmado')
                    Este email já foi confirmado anteriormente. Faça login normalmente.
                    @break
                @case('expirado')
                    O link de confirmação expirou. Solicite um novo abaixo.
                    @break
                @default
                    O link de confirmação é inválido ou expirou. Solicite um novo abaixo.
            @endswitch
        </p>

        @if (session('email_reenvio_aviso'))
            <div class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-neutral)] text-[color:var(--color-primary)] text-sm px-4 py-3 mb-4"
                 dusk="email-reenvio-aviso">
                {{ session('email_reenvio_aviso') }}
            </div>
        @endif

        <form method="POST" action="{{ route('email.reenviar') }}" novalidate class="flex flex-col gap-3">
            @csrf
            <div>
                <x-label for="email">Email cadastrado</x-label>
                <x-input type="email" id="email" name="email" autocomplete="email" required
                         dusk="email-reenvio-email"/>
            </div>
            <x-button type="submit" variant="primary" dusk="email-reenvio-submit">
                Reenviar email
            </x-button>
        </form>

        <p class="mt-6 text-center text-sm m-0">
            <a href="{{ route('login') }}" wire:navigate class="link">Voltar ao login</a>
        </p>
    </x-card>
</x-layouts.sistema>
