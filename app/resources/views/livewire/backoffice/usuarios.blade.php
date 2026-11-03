@php
    $data = fn ($valor) => $valor ? \Illuminate\Support\Carbon::parse($valor)->timezone('America/Sao_Paulo')->format('d/m/Y H:i') : '—';
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
            <table class="w-full text-sm text-left whitespace-nowrap" dusk="backoffice-usuarios">
                <thead class="text-[color:var(--color-secondary)]">
                    <tr>
                        <th scope="col" class="px-3 py-2">Nome</th>
                        <th scope="col" class="px-3 py-2">E-mail</th>
                        <th scope="col" class="px-3 py-2">CPF</th>
                        <th scope="col" class="px-3 py-2">Telefone</th>
                        <th scope="col" class="px-3 py-2">E-mail confirmado</th>
                        <th scope="col" class="px-3 py-2">Cadastro</th>
                        <th scope="col" class="px-3 py-2">Último login</th>
                        <th scope="col" class="px-3 py-2 text-right">Empresas</th>
                        <th scope="col" class="px-3 py-2 text-right">Diagnósticos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($usuarios as $u)
                        <tr class="border-t border-[color:var(--color-border)]" dusk="backoffice-usuario-{{ $u->id }}" wire:key="u-{{ $u->id }}">
                            @if ($u->anonimizado())
                                <td class="px-3 py-2" colspan="4"><span class="pill">Anonimizado</span></td>
                            @else
                                <td class="px-3 py-2">
                                    {{ $u->nome }}
                                    @if ($u->trashed())
                                        <span class="pill">Excluído</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $u->email }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $u->cpfMascarado() }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $telefone($u->telefone) }}</td>
                            @endif
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ $u->email_confirmed_at ? 'Sim · ' . $u->email_confirmed_at->format('d/m/Y') : 'Não' }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $data($u->created_at) }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $data($u->ultimo_login_at) }}</td>
                            <td class="px-3 py-2 text-right">{{ (int) $u->empresas_count }}</td>
                            <td class="px-3 py-2 text-right">{{ (int) $u->diagnosticos_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $usuarios->links() }}</div>
    @endif
</div>
