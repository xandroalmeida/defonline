@php
    $erro = fn (string $campo, string $dusk) => $errors->has($campo)
        ? '<p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="'.$dusk.'">'.e($errors->first($campo)).'</p>'
        : '';
    $reais = fn (int $centavos) => 'R$ '.number_format($centavos / 100, 2, ',', '.');
@endphp

<div>
    <x-page-header :title="'Planos e pacotes'"
                   :subtitle="'Catálogo de cobrança. Alterações valem para novas contratações; quem já assina mantém o preço contratado. Para tirar algo de venda, desative.'" />

    {{-- Interruptor da cobrança (STORY-073) --}}
    <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Cobrança</h2>
    <x-card class="mb-8">
        <form wire:submit="salvarCobranca" novalidate class="flex flex-col gap-3">
            <p class="m-0 text-sm" dusk="cobranca-estado">
                {{ $cobrancaAtiva ? 'Cobrança ligada' : 'Cobrança desligada' }}
            </p>
            <p class="m-0 text-sm text-[color:var(--color-secondary)]">
                Desligada, cada análise funciona como hoje: nada é consumido e ninguém é bloqueado.
                Ligada, cada análise concluída debita a cota do plano ou, sem cota, um crédito, e quem não tem saldo é bloqueado.
            </p>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="cobrancaAtiva" dusk="cobranca-ativa"> Cobrança ativa
            </label>
            <div class="flex items-center gap-3">
                <x-button type="submit" variant="primary" dusk="cobranca-salvar">Salvar interruptor</x-button>
                @if ($mensagemCobranca)
                    <span class="text-sm text-[color:var(--color-secondary)]" role="status" dusk="cobranca-mensagem">{{ $mensagemCobranca }}</span>
                @endif
            </div>
        </form>
    </x-card>

    {{-- Planos --}}
    <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Planos</h2>
    <x-card class="mb-4">
        <form wire:submit="salvarPlano" novalidate class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-3">
                <x-label for="plano-nome">Nome</x-label>
                <x-input type="text" id="plano-nome" wire:model="plano.nome" maxlength="{{ \App\Livewire\Backoffice\Planos::NOME_MAX }}" dusk="plano-nome" class="w-full"/>
                {!! $erro('plano.nome', 'erro-plano-nome') !!}
            </div>
            <div>
                <x-label for="plano-cota">Cota mensal (análises)</x-label>
                <x-input type="text" inputmode="numeric" id="plano-cota" wire:model="plano.cota_mensal" dusk="plano-cota" class="w-full"/>
                {!! $erro('plano.cota_mensal', 'erro-plano-cota') !!}
            </div>
            <div>
                <x-label for="plano-mensal">Preço mensal</x-label>
                <x-input-moeda id="plano-mensal" wire:model="plano.preco_mensal" dusk="plano-mensal"/>
                {!! $erro('plano.preco_mensal', 'erro-plano-mensal') !!}
            </div>
            <div>
                <x-label for="plano-anual">Preço anual</x-label>
                <x-input-moeda id="plano-anual" wire:model="plano.preco_anual" dusk="plano-anual"/>
                {!! $erro('plano.preco_anual', 'erro-plano-anual') !!}
            </div>
            <div>
                <x-label for="plano-ordem">Ordem</x-label>
                <x-input type="text" inputmode="numeric" id="plano-ordem" wire:model="plano.ordem" dusk="plano-ordem" class="w-full"/>
                {!! $erro('plano.ordem', 'erro-plano-ordem') !!}
            </div>
            <label class="flex items-center gap-2 text-sm md:self-end md:pb-2">
                <input type="checkbox" wire:model="plano.ativo" dusk="plano-ativo"> Ativo
            </label>
            <div class="flex gap-2 md:col-span-3">
                <x-button type="submit" variant="primary" dusk="plano-salvar">{{ $plano['id'] ? 'Salvar plano' : 'Adicionar plano' }}</x-button>
                @if ($plano['id'])
                    <x-button type="button" variant="secondary" wire:click="limparPlano" dusk="plano-cancelar">Cancelar</x-button>
                @endif
            </div>
        </form>
    </x-card>

    <div class="card p-0 relative overflow-x-auto mb-8">
        <table class="w-full text-sm text-left" dusk="planos-lista">
            <thead class="text-[color:var(--color-secondary)]">
                <tr>
                    <th scope="col" class="px-3 py-2">Ordem</th>
                    <th scope="col" class="px-3 py-2">Plano</th>
                    <th scope="col" class="px-3 py-2">Cota mensal</th>
                    <th scope="col" class="px-3 py-2">Mensal</th>
                    <th scope="col" class="px-3 py-2">Anual</th>
                    <th scope="col" class="px-3 py-2">Situação</th>
                    <th scope="col" class="px-3 py-2 text-right"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($planos as $item)
                    <tr class="border-t border-[color:var(--color-border)]" wire:key="plano-{{ $item->id }}" dusk="plano-linha-{{ $item->id }}">
                        <td class="px-3 py-2">{{ $item->ordem }}</td>
                        <td class="px-3 py-2 font-medium">{{ $item->nome }}</td>
                        <td class="px-3 py-2">{{ $item->cota_mensal }}</td>
                        <td class="px-3 py-2">{{ $reais($item->preco_mensal_centavos) }}</td>
                        <td class="px-3 py-2">{{ $reais($item->preco_anual_centavos) }}</td>
                        <td class="px-3 py-2" dusk="plano-situacao-{{ $item->id }}">{{ $item->ativo ? 'Ativo' : 'Inativo' }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <x-button type="button" variant="secondary" size="sm" wire:click="editarPlano('{{ $item->id }}')" dusk="plano-editar-{{ $item->id }}">Editar</x-button>
                            <x-button type="button" variant="secondary" size="sm" wire:click="alternarPlano('{{ $item->id }}')" dusk="plano-alternar-{{ $item->id }}">{{ $item->ativo ? 'Desativar' : 'Ativar' }}</x-button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pacotes --}}
    <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Pacotes de créditos</h2>
    <x-card class="mb-4">
        <form wire:submit="salvarPacote" novalidate class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-3">
                <x-label for="pacote-nome">Nome</x-label>
                <x-input type="text" id="pacote-nome" wire:model="pacote.nome" maxlength="{{ \App\Livewire\Backoffice\Planos::NOME_MAX }}" dusk="pacote-nome" class="w-full"/>
                {!! $erro('pacote.nome', 'erro-pacote-nome') !!}
            </div>
            <div>
                <x-label for="pacote-creditos">Créditos</x-label>
                <x-input type="text" inputmode="numeric" id="pacote-creditos" wire:model.live.debounce.400ms="pacote.quantidade_creditos" dusk="pacote-creditos" class="w-full"/>
                {!! $erro('pacote.quantidade_creditos', 'erro-pacote-creditos') !!}
            </div>
            <div>
                <x-label for="pacote-preco">Preço</x-label>
                <x-input-moeda id="pacote-preco" wire:model.live.debounce.400ms="pacote.preco" dusk="pacote-preco"/>
                {!! $erro('pacote.preco', 'erro-pacote-preco') !!}
            </div>
            <div>
                <x-label for="pacote-validade">Validade (meses)</x-label>
                <x-input type="text" inputmode="numeric" id="pacote-validade" wire:model="pacote.validade_meses" dusk="pacote-validade" class="w-full"/>
                {!! $erro('pacote.validade_meses', 'erro-pacote-validade') !!}
            </div>
            <div>
                <x-label for="pacote-ordem">Ordem</x-label>
                <x-input type="text" inputmode="numeric" id="pacote-ordem" wire:model="pacote.ordem" dusk="pacote-ordem" class="w-full"/>
                {!! $erro('pacote.ordem', 'erro-pacote-ordem') !!}
            </div>
            <label class="flex items-center gap-2 text-sm md:self-end md:pb-2">
                <input type="checkbox" wire:model="pacote.ativo" dusk="pacote-ativo"> Ativo
            </label>
            @if ($avisoFormulario !== [])
                <p class="md:col-span-3 m-0 text-sm text-[color:var(--color-farol-amarelo-texto)]" role="status" dusk="pacote-aviso-form">
                    Atenção: o crédito sai mais barato que a análise do plano {{ implode(', ', $avisoFormulario) }}. Você pode salvar mesmo assim.
                </p>
            @endif
            <div class="flex gap-2 md:col-span-3">
                <x-button type="submit" variant="primary" dusk="pacote-salvar">{{ $pacote['id'] ? 'Salvar pacote' : 'Adicionar pacote' }}</x-button>
                @if ($pacote['id'])
                    <x-button type="button" variant="secondary" wire:click="limparPacote" dusk="pacote-cancelar">Cancelar</x-button>
                @endif
            </div>
        </form>
    </x-card>

    <div class="card p-0 relative overflow-x-auto mb-8">
        <table class="w-full text-sm text-left" dusk="pacotes-lista">
            <thead class="text-[color:var(--color-secondary)]">
                <tr>
                    <th scope="col" class="px-3 py-2">Ordem</th>
                    <th scope="col" class="px-3 py-2">Pacote</th>
                    <th scope="col" class="px-3 py-2">Créditos</th>
                    <th scope="col" class="px-3 py-2">Preço</th>
                    <th scope="col" class="px-3 py-2">Unitário</th>
                    <th scope="col" class="px-3 py-2">Validade</th>
                    <th scope="col" class="px-3 py-2">Situação</th>
                    <th scope="col" class="px-3 py-2 text-right"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pacotes as $item)
                    <tr class="border-t border-[color:var(--color-border)]" wire:key="pacote-{{ $item->id }}" dusk="pacote-linha-{{ $item->id }}">
                        <td class="px-3 py-2 align-middle">{{ $item->ordem }}</td>
                        <td class="px-3 py-2 align-middle font-medium">{{ $item->nome }}</td>
                        <td class="px-3 py-2 align-middle">{{ $item->quantidade_creditos }}</td>
                        <td class="px-3 py-2 align-middle">{{ $reais($item->preco_centavos) }}</td>
                        <td class="px-3 py-2 align-middle">
                            {{ $reais((int) round($item->preco_centavos / $item->quantidade_creditos)) }}
                            @if ($avisos[$item->id] !== [])
                                <p class="m-0 text-xs text-[color:var(--color-farol-amarelo-texto)]" dusk="pacote-aviso-{{ $item->id }}">Abaixo do unitário do plano {{ implode(', ', $avisos[$item->id]) }}.</p>
                            @endif
                        </td>
                        <td class="px-3 py-2 align-middle">{{ $item->validade_meses }} {{ $item->validade_meses === 1 ? 'mês' : 'meses' }}</td>
                        <td class="px-3 py-2 align-middle" dusk="pacote-situacao-{{ $item->id }}">{{ $item->ativo ? 'Ativo' : 'Inativo' }}</td>
                        <td class="px-3 py-2 align-middle text-right whitespace-nowrap">
                            <x-button type="button" variant="secondary" size="sm" wire:click="editarPacote('{{ $item->id }}')" dusk="pacote-editar-{{ $item->id }}">Editar</x-button>
                            <x-button type="button" variant="secondary" size="sm" wire:click="alternarPacote('{{ $item->id }}')" dusk="pacote-alternar-{{ $item->id }}">{{ $item->ativo ? 'Desativar' : 'Ativar' }}</x-button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Trial --}}
    <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Trial</h2>
    <x-card>
        <form wire:submit="salvarTrial" novalidate class="grid grid-cols-1 md:grid-cols-2 md:items-end gap-4">
            <label class="md:col-span-2 flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="trial.ativo" dusk="trial-ativo"> Trial ativo
            </label>
            <div>
                <x-label for="trial-dias">Dias</x-label>
                <x-input type="text" inputmode="numeric" id="trial-dias" wire:model="trial.dias" dusk="trial-dias" class="w-full"/>
                {!! $erro('trial.dias', 'erro-trial-dias') !!}
            </div>
            <div>
                <x-label for="trial-analises">Análises incluídas</x-label>
                <x-input type="text" inputmode="numeric" id="trial-analises" wire:model="trial.analises" dusk="trial-analises" class="w-full"/>
                {!! $erro('trial.analises', 'erro-trial-analises') !!}
            </div>
            <div class="md:col-span-2 flex items-center gap-3">
                <x-button type="submit" variant="primary" dusk="trial-salvar">Salvar trial</x-button>
                @if ($mensagemTrial)
                    <span class="text-sm text-[color:var(--color-secondary)]" role="status" dusk="trial-mensagem">{{ $mensagemTrial }}</span>
                @endif
            </div>
        </form>
    </x-card>
</div>
