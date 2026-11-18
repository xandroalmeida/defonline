@php
    use App\Support\DataExibicao;

    $moeda = fn (int $centavos) => 'R$ ' . number_format($centavos / 100, 2, ',', '.');
@endphp
<div>
    <x-page-header :title="'Clientes do cupom ' . $cupom->codigo"
                   :subtitle="'Quem recebeu ou usou o cupom e o que fez desde então. Para acompanhar uma parceria.'">
        <x-slot:actions>
            <x-button :href="route('backoffice.cupons')" variant="secondary" size="sm" dusk="cupom-clientes-voltar">Cupons</x-button>
            @if ($linhas !== [])
                <x-button :href="route('backoffice.cupons.clientes.exportar', $cupom)" variant="primary" size="sm" dusk="cupom-clientes-exportar">Baixar planilha</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 grid-cols-2 md:grid-cols-5 mb-6" dusk="cupom-clientes-totais">
        @foreach ([
            ['Clientes', $totais['clientes'], 'cupom-total-clientes'],
            ['Usaram o cupom', $totais['usaram'], 'cupom-total-usaram'],
            ['Com plano ativo', $totais['com_plano_ativo'], 'cupom-total-plano'],
            ['Fizeram diagnóstico', $totais['com_diagnostico'], 'cupom-total-com-diagnostico'],
            ['Diagnósticos', $totais['diagnosticos'], 'cupom-total-diagnosticos'],
        ] as [$rotulo, $valor, $dusk])
            <x-card class="p-4">
                <p class="m-0 text-xs text-[color:var(--color-secondary)]">{{ $rotulo }}</p>
                <p class="m-0 mt-1 text-2xl font-semibold" dusk="{{ $dusk }}">{{ $valor }}</p>
            </x-card>
        @endforeach
    </div>

    @if ($linhas === [])
        <x-card dusk="cupom-clientes-vazio">Ninguém recebeu nem usou este cupom ainda.</x-card>
    @else
        <div class="card p-0 relative overflow-x-auto">
            <table class="w-full text-sm text-left" dusk="cupom-clientes">
                <thead class="text-[color:var(--color-secondary)]">
                    <tr>
                        <th scope="col" class="px-3 py-2">Cliente</th>
                        <th scope="col" class="px-3 py-2">Situação</th>
                        <th scope="col" class="px-3 py-2">Desde</th>
                        <th scope="col" class="px-3 py-2 text-right">Desconto</th>
                        <th scope="col" class="px-3 py-2">Plano atual</th>
                        <th scope="col" class="px-3 py-2 text-right">Empresas</th>
                        <th scope="col" class="px-3 py-2 text-right">Diagnósticos</th>
                        <th scope="col" class="px-3 py-2">Último</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($linhas as $l)
                        <tr class="border-t border-[color:var(--color-border)] align-top" wire:key="cli-{{ $l['usuario_id'] }}" dusk="cupom-cliente-{{ $l['usuario_id'] }}">
                            <td class="px-3 py-2">
                                @if ($veFichas)
                                    <a class="link" href="{{ route('backoffice.usuarios.ficha', $l['usuario_id']) }}">{{ $l['nome'] }}</a>
                                @else
                                    {{ $l['nome'] }}
                                @endif
                                <div class="text-[color:var(--color-secondary)] [overflow-wrap:anywhere]">{{ $l['email'] }}@if ($l['telefone']) · {{ $l['telefone'] }}@endif</div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $l['situacao'] }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $l['desde'] ? DataExibicao::data($l['desde']) : '—' }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $l['desconto_centavos'] > 0 ? $moeda($l['desconto_centavos']) : '—' }}</td>
                            <td class="px-3 py-2">{{ $l['plano'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $l['empresas'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $l['diagnosticos'] }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $l['ultimo_diagnostico'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-[color:var(--color-secondary)] mt-2 mb-0">Desconto total concedido: {{ $moeda($totais['desconto_centavos']) }}.</p>
    @endif
</div>
