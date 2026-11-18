@php
    use App\Domain\Cpf;
    use App\Support\DataExibicao;
    use App\Support\Telefone;

    $whatsapp = Telefone::whatsapp($usuario->telefone);
@endphp
<div>
    <x-page-header :title="$usuario->anonimizado() ? 'Usuário anonimizado' : $usuario->nome"
                   :subtitle="'Ficha do usuário · cadastro em ' . DataExibicao::data($usuario->created_at)">
        <x-slot:actions>
            <x-button :href="route('backoffice.usuarios')" variant="secondary" size="sm" dusk="ficha-voltar">Usuários</x-button>
            @if ($usuario->podeSerPersonificadoPor($admin))
                <form method="POST" action="{{ route('backoffice.usuarios.logar-como', $usuario) }}" class="m-0">
                    @csrf
                    <x-button type="submit" variant="primary" size="sm" dusk="ficha-logar-como">Logar como</x-button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-2 mb-6">
        <x-card dusk="ficha-usuario-dados">
            <h2 class="text-sm font-semibold m-0 mb-3">Dados</h2>
            @if ($usuario->anonimizado())
                <p class="m-0 text-sm text-[color:var(--color-secondary)]">Conta anonimizada a pedido do titular (LGPD). Os dados pessoais foram apagados.</p>
            @else
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm m-0">
                    <dt class="text-[color:var(--color-secondary)]">E-mail</dt>
                    <dd class="m-0 [overflow-wrap:anywhere]"><a class="link" href="mailto:{{ $usuario->email }}">{{ $usuario->email }}</a></dd>
                    <dt class="text-[color:var(--color-secondary)]">CPF</dt>
                    <dd class="m-0" dusk="ficha-usuario-cpf">{{ $usuario->cpf ? Cpf::formatar($usuario->cpf) : '—' }}</dd>
                    <dt class="text-[color:var(--color-secondary)]">WhatsApp</dt>
                    <dd class="m-0">
                        @if ($whatsapp)
                            <a class="link" href="{{ $whatsapp }}" target="_blank" rel="noopener" dusk="ficha-usuario-whatsapp">{{ Telefone::formatar($usuario->telefone) }}</a>
                        @else
                            {{ Telefone::formatar($usuario->telefone) ?? '—' }}
                        @endif
                    </dd>
                    <dt class="text-[color:var(--color-secondary)]">E-mail confirmado</dt>
                    <dd class="m-0">{{ $usuario->email_confirmed_at ? 'Sim, em ' . DataExibicao::data($usuario->email_confirmed_at) : 'Não' }}</dd>
                    @if ($usuario->trashed())
                        <dt class="text-[color:var(--color-secondary)]">Situação</dt>
                        <dd class="m-0"><span class="pill">Excluído</span></dd>
                    @endif
                </dl>
            @endif
        </x-card>

        <x-card dusk="ficha-usuario-plano">
            <h2 class="text-sm font-semibold m-0 mb-3">Plano e créditos</h2>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm m-0">
                <dt class="text-[color:var(--color-secondary)]">Plano</dt>
                <dd class="m-0">
                    @if ($assinatura === null)
                        Nenhum
                    @elseif ($assinatura->trial)
                        Teste grátis
                    @else
                        {{ $assinatura->plano?->nome ?? '—' }}{{ $assinatura->ciclo ? ' · ' . $assinatura->ciclo : '' }}
                    @endif
                </dd>
                @if ($assinatura)
                    <dt class="text-[color:var(--color-secondary)]">Situação</dt>
                    <dd class="m-0">{{ $assinatura->status->rotulo() }}</dd>
                    <dt class="text-[color:var(--color-secondary)]">Análises no ciclo</dt>
                    <dd class="m-0">{{ $assinatura->cota_restante_ciclo }} de {{ $assinatura->cota_snapshot }}</dd>
                    <dt class="text-[color:var(--color-secondary)]">Próximo vencimento</dt>
                    <dd class="m-0">{{ DataExibicao::data($assinatura->proximo_vencimento) }}</dd>
                @endif
                <dt class="text-[color:var(--color-secondary)]">Créditos avulsos</dt>
                <dd class="m-0" dusk="ficha-usuario-creditos">{{ $creditos }}</dd>
            </dl>
        </x-card>
    </div>

    <h2 class="text-base font-semibold mb-2">Empresas ({{ $empresas->count() }})</h2>
    @if ($empresas->isEmpty())
        <x-card class="mb-6">Nenhuma empresa cadastrada.</x-card>
    @else
        <div class="card p-0 relative overflow-x-auto mb-6">
            <table class="w-full text-sm text-left" dusk="ficha-usuario-empresas">
                <thead class="text-[color:var(--color-secondary)]">
                    <tr>
                        <th scope="col" class="px-2 py-2">Empresa</th>
                        <th scope="col" class="px-2 py-2">CNPJ/CPF</th>
                        <th scope="col" class="px-2 py-2">Cidade</th>
                        <th scope="col" class="px-2 py-2 text-right">Diagnósticos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($empresas as $e)
                        <tr class="border-t border-[color:var(--color-border)]" wire:key="e-{{ $e->id }}">
                            <td class="px-2 py-2">
                                <a class="link" href="{{ route('backoffice.empresas.ficha', $e->id) }}" dusk="ficha-usuario-empresa-{{ $e->id }}">{{ $e->nomeExibicao() }}</a>
                                @if ($e->trashed())
                                    <span class="pill">Excluída</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap">{{ $e->documentoFormatado() }}</td>
                            <td class="px-2 py-2">{{ $e->municipio }}/{{ $e->uf }}</td>
                            <td class="px-2 py-2 text-right">{{ $e->diagnosticos_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="text-base font-semibold mb-2">Diagnósticos ({{ $diagnosticos->count() }})</h2>
    @include('livewire.backoffice.partials.lista-diagnosticos', ['diagnosticos' => $diagnosticos, 'demandas' => $demandas, 'mostrarEmpresa' => true])
</div>
