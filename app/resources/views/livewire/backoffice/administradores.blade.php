<div>
    <x-page-header :title="'Administradores'"
                   :subtitle="'Quem acessa a área de administração e o que pode fazer.'" />

    <x-card class="mb-6">
        <form wire:submit="salvar" novalidate class="flex flex-col gap-4">
            <div>
                <x-label for="admin-email">E-mail da conta</x-label>
                <x-input type="email" id="admin-email" wire:model="email" dusk="admin-email" class="w-full"/>
                <p class="text-sm text-[color:var(--color-secondary)] mt-1 mb-0">A pessoa precisa ter cadastro com e-mail confirmado.</p>
                @error('email')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-admin-email">{{ $message }}</p>
                @enderror
            </div>

            <fieldset class="m-0 p-0 border-0">
                <legend class="text-sm font-medium mb-2">Permissões</legend>
                <div class="flex flex-col gap-2">
                    @foreach ($opcoes as $opcao)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="permissoes" value="{{ $opcao->value }}" dusk="admin-permissao-{{ $opcao->value }}">
                            {{ $opcao->rotulo() }}
                        </label>
                    @endforeach
                </div>
                @error('permissoes')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-admin-permissoes">{{ $message }}</p>
                @enderror
            </fieldset>

            <div class="flex gap-2">
                <x-button type="submit" variant="primary" dusk="admin-salvar">Salvar</x-button>
                <x-button type="button" variant="secondary" wire:click="limpar" dusk="admin-limpar">Limpar</x-button>
            </div>
        </form>
    </x-card>

    <div class="card p-0 overflow-x-auto">
        <table class="w-full text-sm text-left" dusk="admin-lista">
            <thead class="text-[color:var(--color-secondary)]">
                <tr>
                    <th scope="col" class="px-3 py-2">Nome e e-mail</th>
                    <th scope="col" class="px-3 py-2">Permissões</th>
                    <th scope="col" class="px-3 py-2"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($admins as $admin)
                    <tr class="border-t border-[color:var(--color-border)]" wire:key="admin-{{ $admin->id }}" dusk="admin-linha-{{ $admin->id }}">
                        <td class="px-3 py-2">
                            {{ $admin->nome }}
                            <div class="text-[color:var(--color-secondary)] [overflow-wrap:anywhere]">{{ $admin->email }}</div>
                        </td>
                        <td class="px-3 py-2">
                            @if ($admin->ehAdminPorConfiguracao())
                                Todas <span class="pill">Configuração do servidor</span>
                            @else
                                {{ collect($opcoes)->filter(fn ($p) => $admin->temPermissao($p))->map(fn ($p) => $p->rotulo())->join(', ') }}
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @unless ($admin->ehAdminPorConfiguracao() || $admin->id === $eu)
                                <x-button type="button" variant="secondary" size="sm" wire:click="editar('{{ $admin->id }}')" dusk="admin-editar-{{ $admin->id }}">Editar</x-button>
                                <x-button type="button" variant="secondary" size="sm" wire:click="remover('{{ $admin->id }}')" dusk="admin-remover-{{ $admin->id }}">Remover</x-button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
