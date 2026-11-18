@php
    use App\Support\Telefone;
@endphp
<div>
    <x-page-header :title="'Empresas cadastradas'"
                   :subtitle="$empresas->total() . ' ' . ($empresas->total() === 1 ? 'empresa' : 'empresas')" />

    <form wire:submit="buscar" class="flex gap-2 mb-4" role="search">
        <label for="backoffice-busca-empresas" class="sr-only">Buscar por nome, CNPJ/CPF, e-mail ou telefone</label>
        <x-input type="search" id="backoffice-busca-empresas" wire:model="termo"
                 placeholder="Buscar por nome, CNPJ/CPF, e-mail ou telefone"
                 class="flex-1" dusk="backoffice-busca-empresas" />
        <x-button type="submit" variant="primary" dusk="backoffice-buscar-empresas">Buscar</x-button>
    </form>

    @if ($empresas->isEmpty())
        <x-card dusk="backoffice-empresas-vazio">Nenhuma empresa encontrada.</x-card>
    @else
        <div class="card p-0 relative overflow-x-auto">
            <table class="w-full text-sm text-left" dusk="backoffice-empresas">
                <thead class="text-[color:var(--color-secondary)]">
                    <tr>
                        <th scope="col" class="px-2 py-2">Empresa</th>
                        <th scope="col" class="px-2 py-2">CNPJ/CPF</th>
                        <th scope="col" class="px-2 py-2">Contato</th>
                        <th scope="col" class="px-2 py-2">Usuário</th>
                        <th scope="col" class="px-2 py-2 text-right">Diagnósticos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($empresas as $e)
                        <tr class="border-t border-[color:var(--color-border)]" wire:key="e-{{ $e->id }}" dusk="backoffice-empresa-{{ $e->id }}">
                            <td class="px-2 py-2">
                                <a class="link" href="{{ route('backoffice.empresas.ficha', $e->id) }}" dusk="backoffice-empresa-ficha-{{ $e->id }}">{{ $e->nomeExibicao() }}</a>
                                @if ($e->trashed())
                                    <span class="pill">Excluída</span>
                                @endif
                                <div class="text-[color:var(--color-secondary)]">{{ $e->municipio }}/{{ $e->uf }}</div>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap">{{ $e->documentoFormatado() }}</td>
                            <td class="px-2 py-2">
                                {{ Telefone::formatar($e->telefone) ?? '' }}
                                @if ($e->email)
                                    <div class="text-[color:var(--color-secondary)] [overflow-wrap:anywhere]">{{ $e->email }}</div>
                                @endif
                                @if (! $e->telefone && ! $e->email)
                                    —
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                @if ($e->usuario)
                                    <a class="link" href="{{ route('backoffice.usuarios.ficha', $e->usuario->id) }}">{{ $e->usuario->anonimizado() ? 'Anonimizado' : $e->usuario->nome }}</a>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right">{{ $e->diagnosticos_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $empresas->links() }}</div>
    @endif
</div>
