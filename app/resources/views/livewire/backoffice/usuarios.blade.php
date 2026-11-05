@php
    $data = fn ($valor) => $valor ? \App\Support\DataExibicao::dataHora($valor) : '—';
    $telefone = function (?string $t): string {
        $d = preg_replace('/\D+/', '', (string) $t) ?? '';
        return match (strlen($d)) {
            11 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)),
            10 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)),
            default => $d === '' ? '—' : $d,
        };
    };
@endphp
<div>
    <x-page-header :title="'Usuários cadastrados'"
                   :subtitle="$total . ' ' . ($total === 1 ? 'usuário' : 'usuários') . ' · ' . $confirmados . ' com e-mail confirmado'" />

    <form wire:submit="buscar" class="flex gap-2 mb-4" role="search">
        <label for="backoffice-busca" class="sr-only">Buscar por nome ou e-mail</label>
        <x-input type="search" id="backoffice-busca" wire:model="termo"
                 placeholder="Buscar por nome ou e-mail"
                 class="flex-1" dusk="backoffice-busca" />
        <x-button type="submit" variant="primary" dusk="backoffice-buscar">Buscar</x-button>
    </form>

    @if ($usuarios->isEmpty())
        <x-card dusk="backoffice-vazio">Nenhum usuário encontrado.</x-card>
    @else
        <div class="card p-0 overflow-x-auto">
            <table class="w-full text-sm text-left" dusk="backoffice-usuarios">
                <thead class="text-[color:var(--color-secondary)]">
                    <tr>
                        <th scope="col" class="px-2 py-2">Nome e e-mail</th>
                        <th scope="col" class="px-2 py-2">CPF</th>
                        <th scope="col" class="px-2 py-2">Telefone</th>
                        <th scope="col" class="px-2 py-2">E-mail confirmado</th>
                        <th scope="col" class="px-2 py-2">Cadastro</th>
                        <th scope="col" class="px-2 py-2">Último login</th>
                        <th scope="col" class="px-2 py-2 text-right">Empresas</th>
                        <th scope="col" class="px-2 py-2 text-right">Diagnósticos</th>
                        <th scope="col" class="sticky right-0 bg-[color:var(--color-surface)] px-2 py-2"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($usuarios as $u)
                        <tr class="border-t border-[color:var(--color-border)]" dusk="backoffice-usuario-{{ $u->id }}" wire:key="u-{{ $u->id }}">
                            @if ($u->anonimizado())
                                <td class="px-2 py-2" colspan="3"><span class="pill">Anonimizado</span></td>
                            @else
                                <td class="px-2 py-2">
                                    {{ $u->nome }}
                                    @if ($u->trashed())
                                        <span class="pill">Excluído</span>
                                    @endif
                                    <div class="text-[color:var(--color-secondary)] [overflow-wrap:anywhere]">{{ $u->email }}</div>
                                </td>
                                <td class="px-2 py-2 whitespace-nowrap">{{ $u->cpfMascarado() }}</td>
                                <td class="px-2 py-2 whitespace-nowrap">{{ $telefone($u->telefone) }}</td>
                            @endif
                            <td class="px-2 py-2">
                                {{ $u->email_confirmed_at ? 'Sim · ' . \App\Support\DataExibicao::data($u->email_confirmed_at) : 'Não' }}
                            </td>
                            <td class="px-2 py-2">{{ $data($u->created_at) }}</td>
                            <td class="px-2 py-2">{{ $data($u->ultimo_login_at) }}</td>
                            <td class="px-2 py-2 text-right">{{ (int) $u->empresas_count }}
                                @if ((int) $u->empresas_via_admin_count > 0)
                                    <div class="text-xs text-[color:var(--color-secondary)] whitespace-nowrap">{{ (int) $u->empresas_via_admin_count }} via admin</div>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right">{{ (int) $u->diagnosticos_count }}
                                @if ((int) $u->diagnosticos_via_admin_count > 0)
                                    <div class="text-xs text-[color:var(--color-secondary)] whitespace-nowrap">{{ (int) $u->diagnosticos_via_admin_count }} via admin</div>
                                @endif
                            </td>
                            <td class="sticky right-0 bg-[color:var(--color-surface)] px-2 py-2 text-right">
                                @if ($u->podeSerPersonificadoPor($admin))
                                    <form method="POST" action="{{ route('backoffice.usuarios.logar-como', $u) }}" class="m-0">
                                        @csrf
                                        <x-button type="submit" variant="secondary" size="sm" class="whitespace-nowrap" dusk="backoffice-logar-como-{{ $u->id }}">Logar como</x-button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $usuarios->links() }}</div>
    @endif
</div>
