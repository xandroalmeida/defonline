@php
    $moeda = fn ($v) => $v === null ? '—' : 'R$ '.number_format((float) $v, 2, ',', '.');
@endphp
<div>
    <x-page-header :title="'Captação'"
                   :subtitle="'Demandas de captação informadas no bloco 4 do diagnóstico. O retorno ao cliente é em até ' . \App\Domain\Captacao\PrazoRetorno::DIAS_UTEIS . ' dias úteis.'" />

    <x-card class="mb-6">
        <form wire:submit="salvar" novalidate class="flex flex-col gap-3">
            <div>
                <x-label for="captacao-email">E-mail da equipe que recebe as demandas</x-label>
                <x-input type="email" id="captacao-email" wire:model.live.debounce.400ms="email" maxlength="255"
                         placeholder="Deixe em branco para não enviar" class="w-full" dusk="captacao-email"/>
                @error('email')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-captacao-email">{{ $message }}</p>
                @enderror
                <p class="text-sm text-[color:var(--color-secondary)] mt-1 mb-0" dusk="captacao-email-situacao">
                    @if ($emailCadastrado)
                        Cada demanda com contato autorizado é enviada para {{ $emailCadastrado }}.
                    @else
                        Sem e-mail cadastrado: as demandas ficam só nesta lista e no alerta da equipe.
                    @endif
                </p>
            </div>
            <div class="flex gap-3 items-center">
                <x-button type="submit" variant="primary" dusk="captacao-salvar">Salvar</x-button>
                @if ($salvo)
                    <span class="text-sm text-[color:var(--color-secondary)]" role="status" dusk="captacao-salvo">Salvo.</span>
                @endif
            </div>
        </form>
    </x-card>

    @if ($demandas->isEmpty())
        <x-card dusk="captacao-vazio">Nenhuma demanda de captação ainda.</x-card>
    @else
        <div class="card p-0 relative overflow-x-auto">
            <table class="w-full text-sm text-left" dusk="captacao-lista">
                <thead class="text-[color:var(--color-secondary)]">
                    <tr>
                        <th scope="col" class="px-2 py-2">Recebida</th>
                        <th scope="col" class="px-2 py-2">Empresa</th>
                        <th scope="col" class="px-2 py-2">Contato</th>
                        <th scope="col" class="px-2 py-2 text-right">Valor a captar</th>
                        <th scope="col" class="px-2 py-2">Contato autorizado</th>
                        <th scope="col" class="px-2 py-2">Retornar até</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($demandas as $demanda)
                        <tr class="border-t border-[color:var(--color-border)] align-top" wire:key="demanda-{{ $demanda->id }}" dusk="captacao-linha-{{ $demanda->id }}">
                            <td class="px-2 py-2 whitespace-nowrap">{{ \App\Support\DataExibicao::dataHora($demanda->created_at) }}</td>
                            <td class="px-2 py-2">
                                <span class="font-medium">{{ $demanda->empresa->nomeExibicao() }}</span><br>
                                <span class="text-[color:var(--color-secondary)]">{{ $demanda->empresa->documentoFormatado() }}</span>
                            </td>
                            <td class="px-2 py-2">
                                {{ $demanda->usuario->nome }}<br>
                                <span class="text-[color:var(--color-secondary)]">{{ $demanda->usuario->email }}</span>
                                @if ($demanda->empresa->telefoneFormatado())
                                    <br><span class="text-[color:var(--color-secondary)]">{{ $demanda->empresa->telefoneFormatado() }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">{{ $moeda($demanda->valor_captar) }}</td>
                            <td class="px-2 py-2">{{ $demanda->autoriza_contato ? 'Sim' : 'Não' }}</td>
                            <td class="px-2 py-2 whitespace-nowrap">{{ $demanda->autoriza_contato ? \App\Support\DataExibicao::dia($demanda->prazoRetorno()) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $demandas->links() }}</div>
    @endif
</div>
