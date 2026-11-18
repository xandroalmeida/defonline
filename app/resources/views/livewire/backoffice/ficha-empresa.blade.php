@php
    use App\Support\DataExibicao;
    use App\Support\Telefone;

    $whatsapp = Telefone::whatsapp($empresa->telefone);
    $dono = $empresa->usuario;
@endphp
<div>
    <x-page-header :title="$empresa->nomeExibicao()"
                   :detalhe="$empresa->razaoSocialComplementar()"
                   :subtitle="'Ficha da empresa · ' . $empresa->documentoFormatado()">
        <x-slot:actions>
            <x-button :href="route('backoffice.empresas')" variant="secondary" size="sm" dusk="ficha-empresa-voltar">Empresas</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-2 mb-6">
        <x-card dusk="ficha-empresa-dados">
            <h2 class="text-sm font-semibold m-0 mb-3">Empresa</h2>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm m-0">
                <dt class="text-[color:var(--color-secondary)]">{{ $empresa->tipo_documento->value === 'cnpj' ? 'CNPJ' : 'CPF' }}</dt>
                <dd class="m-0" dusk="ficha-empresa-documento">{{ $empresa->documentoFormatado() }}</dd>
                <dt class="text-[color:var(--color-secondary)]">Razão social</dt>
                <dd class="m-0">{{ $empresa->razao_social }}</dd>
                <dt class="text-[color:var(--color-secondary)]">Cidade</dt>
                <dd class="m-0">{{ $empresa->municipio }}/{{ $empresa->uf }}</dd>
                <dt class="text-[color:var(--color-secondary)]">Situação na Receita</dt>
                <dd class="m-0">{{ $empresa->situacao_cadastral->rotulo() }}</dd>
                <dt class="text-[color:var(--color-secondary)]">Cadastrada em</dt>
                <dd class="m-0">{{ DataExibicao::data($empresa->created_at) }}@if ($empresa->trashed()) · <span class="pill">Excluída</span>@endif</dd>
            </dl>
        </x-card>

        <x-card dusk="ficha-empresa-contato">
            <h2 class="text-sm font-semibold m-0 mb-3">Contato</h2>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm m-0">
                <dt class="text-[color:var(--color-secondary)]">WhatsApp</dt>
                <dd class="m-0">
                    @if ($whatsapp)
                        <a class="link" href="{{ $whatsapp }}" target="_blank" rel="noopener" dusk="ficha-empresa-whatsapp">{{ Telefone::formatar($empresa->telefone) }}</a>
                    @else
                        {{ Telefone::formatar($empresa->telefone) ?? '—' }}
                    @endif
                </dd>
                <dt class="text-[color:var(--color-secondary)]">E-mail</dt>
                <dd class="m-0 [overflow-wrap:anywhere]">@if ($empresa->email)<a class="link" href="mailto:{{ $empresa->email }}">{{ $empresa->email }}</a>@else — @endif</dd>
                <dt class="text-[color:var(--color-secondary)]">Usuário</dt>
                <dd class="m-0">
                    @if ($dono)
                        <a class="link" href="{{ route('backoffice.usuarios.ficha', $dono->id) }}" dusk="ficha-empresa-usuario">{{ $dono->anonimizado() ? 'Anonimizado' : $dono->nome }}</a>
                        @unless ($dono->anonimizado())
                            <div class="text-[color:var(--color-secondary)] [overflow-wrap:anywhere]">{{ $dono->email }}@if (Telefone::formatar($dono->telefone)) · {{ Telefone::formatar($dono->telefone) }}@endif</div>
                        @endunless
                    @endif
                </dd>
            </dl>
        </x-card>
    </div>

    <h2 class="text-base font-semibold mb-2">Diagnósticos ({{ $diagnosticos->count() }})</h2>
    @include('livewire.backoffice.partials.lista-diagnosticos', ['diagnosticos' => $diagnosticos, 'demandas' => $demandas, 'mostrarEmpresa' => false])
</div>
