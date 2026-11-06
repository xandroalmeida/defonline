<x-layouts.sistema title="Avisos de renovação">
    <x-card>
        @if ($descadastrado)
            <h1 class="text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)] mb-2" dusk="aviso-renovacao-descadastrado">Pronto</h1>
            <p class="text-[color:var(--color-secondary)] m-0">Você não vai mais receber e-mails para renovar o diagnóstico. O alerta continua aparecendo na plataforma. Para voltar a receber, use Minha conta.</p>
        @else
            <h1 class="text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)] mb-2">Parar de receber estes avisos?</h1>
            <p class="text-[color:var(--color-secondary)] mb-6 mt-0">Você deixa de receber os e-mails para renovar o diagnóstico. O alerta continua aparecendo na plataforma.</p>
            <form method="POST" action="{{ $acao }}" class="m-0">
                @csrf
                <x-button type="submit" variant="primary" dusk="aviso-renovacao-descadastrar">Parar de receber</x-button>
            </form>
        @endif
    </x-card>
</x-layouts.sistema>
