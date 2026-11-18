<x-layouts.auth title="Entrar sem senha (local)" :largo="true">
    <x-card>
        <h1 class="m-0 mb-1 text-xl font-semibold text-[color:var(--color-primary)]">Entrar sem senha</h1>
        <p class="m-0 mb-4 text-sm text-[color:var(--color-secondary)]">
            Só no ambiente local. Clique numa conta para entrar nela.
            @if ($atual)
                Você está como <strong>{{ $atual->email }}</strong>.
            @endif
        </p>

        <form method="GET" action="{{ route('dev.entrar') }}" class="flex gap-2 mb-4" role="search">
            <label for="dev-busca" class="sr-only">Buscar por nome ou e-mail</label>
            <x-input type="search" id="dev-busca" name="q" value="{{ $busca }}" placeholder="Buscar por nome ou e-mail" class="flex-1" dusk="dev-busca"/>
            <x-button type="submit" variant="secondary">Buscar</x-button>
        </form>

        @if ($usuarios->isEmpty())
            <p class="m-0 text-sm" dusk="dev-vazio">Nenhuma conta encontrada.</p>
        @else
            <ul class="list-none m-0 p-0 flex flex-col" dusk="dev-usuarios">
                @foreach ($usuarios as $usuario)
                    <li class="border-t border-[color:var(--color-border)] first:border-t-0">
                        <form method="POST" action="{{ route('dev.entrar.como', $usuario) }}" class="m-0">
                            @csrf
                            <button type="submit" dusk="dev-entrar-{{ $usuario->id }}"
                                    class="w-full text-left py-2.5 px-1 flex flex-wrap gap-x-3 items-baseline cursor-pointer bg-transparent border-0 hover:bg-[color:var(--color-neutral)]">
                                <span class="font-medium text-[color:var(--color-primary)]">{{ $usuario->nome }}</span>
                                <span class="text-sm text-[color:var(--color-secondary)]">{{ $usuario->email }}</span>
                                @if ($usuario->ehEquipeBackoffice())
                                    <span class="pill text-xs">admin</span>
                                @endif
                                @if ($atual?->id === $usuario->id)
                                    <span class="text-xs text-[color:var(--color-secondary)]">(atual)</span>
                                @endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-layouts.auth>
