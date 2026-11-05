@php
    $erro = fn (string $campo, string $dusk) => $errors->has($campo)
        ? '<p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="'.$dusk.'">'.e($errors->first($campo)).'</p>'
        : '';
    $data = fn ($d) => $d->format('d/m/Y');
@endphp

<div>
    <x-page-header :title="'Cupons'"
                   :subtitle="'Descontos parciais ou totais, criados em tela. Desativar impede novos usos e concessões, mas não afeta descontos já aplicados.'" />

    @if ($mensagem)
        <p class="mb-4 text-sm text-[color:var(--color-secondary)]" role="status" dusk="cupom-mensagem">{{ $mensagem }}</p>
    @endif

    {{-- Formulário --}}
    <x-card class="mb-6">
        <form wire:submit="salvarCupom" novalidate class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="cupom-codigo">Código</x-label>
                <x-input type="text" id="cupom-codigo" wire:model="cupom.codigo" maxlength="40" dusk="cupom-codigo" class="w-full"/>
                {!! $erro('cupom.codigo', 'erro-cupom-codigo') !!}
            </div>
            <div>
                <x-label for="cupom-tipo">Tipo de desconto</x-label>
                <x-select id="cupom-tipo" wire:model.live="cupom.tipo" dusk="cupom-tipo" class="w-full">
                    <option value="percentual">Percentual (1 a 100%)</option>
                    <option value="valor_fixo">Valor fixo (R$)</option>
                </x-select>
                {!! $erro('cupom.tipo', 'erro-cupom-tipo') !!}
            </div>
            <div>
                <x-label for="cupom-valor">{{ $cupom['tipo'] === 'percentual' ? 'Percentual (%)' : 'Valor (R$)' }}</x-label>
                <x-input type="text" inputmode="decimal" id="cupom-valor" wire:model="cupom.valor" dusk="cupom-valor" class="w-full"/>
                {!! $erro('cupom.valor', 'erro-cupom-valor') !!}
            </div>
            <div>
                <x-label for="cupom-inicio">Vigência: início</x-label>
                <x-input type="date" id="cupom-inicio" wire:model="cupom.vigencia_inicio" dusk="cupom-inicio" class="w-full"/>
                {!! $erro('cupom.vigencia_inicio', 'erro-cupom-inicio') !!}
            </div>
            <div>
                <x-label for="cupom-fim">Vigência: fim</x-label>
                <x-input type="date" id="cupom-fim" wire:model="cupom.vigencia_fim" dusk="cupom-fim" class="w-full"/>
                {!! $erro('cupom.vigencia_fim', 'erro-cupom-fim') !!}
            </div>
            <div>
                <x-label for="cupom-limite">Limite total de usos</x-label>
                <x-input type="text" inputmode="numeric" id="cupom-limite" wire:model="cupom.limite_usos" placeholder="Sem limite" dusk="cupom-limite" class="w-full"/>
                {!! $erro('cupom.limite_usos', 'erro-cupom-limite') !!}
            </div>
            <div>
                <x-label for="cupom-duracao">Duração do desconto</x-label>
                <x-select id="cupom-duracao" wire:model.live="cupom.duracao" dusk="cupom-duracao" class="w-full">
                    <option value="assinatura">Toda a assinatura</option>
                    <option value="ciclos">Número de ciclos</option>
                </x-select>
                {!! $erro('cupom.duracao', 'erro-cupom-duracao') !!}
            </div>
            @if ($cupom['duracao'] === 'ciclos')
                <div>
                    <x-label for="cupom-ciclos">Ciclos</x-label>
                    <x-input type="text" inputmode="numeric" id="cupom-ciclos" wire:model="cupom.ciclos" dusk="cupom-ciclos" class="w-full"/>
                    {!! $erro('cupom.ciclos', 'erro-cupom-ciclos') !!}
                </div>
            @endif
            <fieldset class="md:col-span-3 border-0 p-0 m-0">
                <legend class="text-sm font-medium mb-2">Itens cobertos</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                    @foreach ($opcoes as $chave => $rotulo)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="cupom.itens" value="{{ $chave }}" dusk="cupom-item-{{ str_replace(':', '-', $chave) }}"> {{ $rotulo }}
                        </label>
                    @endforeach
                </div>
                {!! $erro('cupom.itens', 'erro-cupom-itens') !!}
                {!! $erro('cupom.itens.*', 'erro-cupom-itens') !!}
            </fieldset>
            <div class="flex gap-2 md:col-span-3">
                <x-button type="submit" variant="primary" dusk="cupom-salvar">{{ $cupom['id'] ? 'Salvar cupom' : 'Criar cupom' }}</x-button>
                @if ($cupom['id'])
                    <x-button type="button" variant="secondary" wire:click="limparCupom" dusk="cupom-cancelar">Cancelar</x-button>
                @endif
            </div>
        </form>
    </x-card>

    {{-- Concessão --}}
    @if ($cupomConcessao)
        <x-card class="mb-6">
            <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Conceder o cupom {{ $cupomConcessao->codigo }}</h2>
            <form wire:submit="calcularConcessao" novalidate class="grid grid-cols-1 gap-4">
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" wire:model.live="concessao.modo" value="contas" dusk="concessao-modo-contas"> Contas específicas</label>
                    <label class="flex items-center gap-2"><input type="radio" wire:model.live="concessao.modo" value="todas" dusk="concessao-modo-todas"> Todas as contas cadastradas até uma data</label>
                </div>
                @if ($concessao['modo'] === 'contas')
                    <div>
                        <x-label for="concessao-emails">E-mails das contas (um por linha, ou separados por vírgula)</x-label>
                        <textarea id="concessao-emails" wire:model="concessao.emails" rows="4" dusk="concessao-emails" class="input min-h-24"></textarea>
                        {!! $erro('concessao.emails', 'erro-concessao-emails') !!}
                    </div>
                @else
                    <div class="md:w-1/3">
                        <x-label for="concessao-ate">Cadastradas até (inclusive)</x-label>
                        <x-input type="date" id="concessao-ate" wire:model="concessao.ate" dusk="concessao-ate" class="w-full"/>
                        {!! $erro('concessao.ate', 'erro-concessao-ate') !!}
                    </div>
                @endif
                {!! $erro('concessao.cupom_id', 'erro-concessao-cupom') !!}

                @if ($previa)
                    <p class="m-0 text-sm" role="status" dusk="concessao-previa">
                        Serão afetadas <strong>{{ $previa['total'] }}</strong> {{ $previa['total'] === 1 ? 'conta' : 'contas' }}:
                        {{ $previa['novas'] }} {{ $previa['novas'] === 1 ? 'receberá' : 'receberão' }} o cupom agora e {{ $previa['ja_tinham'] }} já {{ $previa['ja_tinham'] === 1 ? 'tinha' : 'tinham' }}.
                    </p>
                @endif

                <div class="flex gap-2">
                    <x-button type="submit" variant="secondary" dusk="concessao-calcular">Ver quantas contas</x-button>
                    @if ($previa && $previa['novas'] > 0)
                        <x-button type="button" variant="primary" wire:click="confirmarConcessao" wire:confirm="Conceder o cupom a {{ $previa['novas'] }} conta(s)?" dusk="concessao-confirmar">Confirmar concessão</x-button>
                    @endif
                    <x-button type="button" variant="secondary" wire:click="cancelarConcessao" dusk="concessao-cancelar">Cancelar</x-button>
                </div>
            </form>
        </x-card>
    @endif

    {{-- Lista --}}
    <div class="card p-0 overflow-x-auto mb-8">
        <table class="w-full text-sm text-left" dusk="cupons-lista">
            <thead class="text-[color:var(--color-secondary)]">
                <tr>
                    <th scope="col" class="px-3 py-2">Código</th>
                    <th scope="col" class="px-3 py-2">Desconto</th>
                    <th scope="col" class="px-3 py-2">Vigência</th>
                    <th scope="col" class="px-3 py-2">Itens</th>
                    <th scope="col" class="px-3 py-2">Concessões</th>
                    <th scope="col" class="px-3 py-2">Usos</th>
                    <th scope="col" class="px-3 py-2">Situação</th>
                    <th scope="col" class="px-3 py-2"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cupons as $item)
                    <tr class="border-t border-[color:var(--color-border)]" wire:key="cupom-{{ $item->id }}" dusk="cupom-linha-{{ $item->id }}">
                        <td class="px-3 py-2 align-top font-medium">{{ $item->codigo }}</td>
                        <td class="px-3 py-2 align-top">
                            {{ $item->tipo->formatar($item->valor) }}
                            <span class="block text-xs text-[color:var(--color-secondary)]">{{ $item->ciclos_duracao === null ? 'toda a assinatura' : $item->ciclos_duracao.' '.($item->ciclos_duracao === 1 ? 'ciclo' : 'ciclos') }}</span>
                        </td>
                        <td class="px-3 py-2 align-top whitespace-nowrap">{{ $data($item->vigencia_inicio) }} a {{ $data($item->vigencia_fim) }}</td>
                        <td class="px-3 py-2 align-top text-xs">
                            @foreach ($item->itens as $coberto)
                                <span class="block">{{ $coberto->plano ? $coberto->plano->nome.' ('.$coberto->ciclo.')' : 'Pacote '.$coberto->pacote?->nome }}</span>
                            @endforeach
                        </td>
                        <td class="px-3 py-2 align-top" dusk="cupom-concessoes-{{ $item->id }}">{{ $item->concessoes_count }}</td>
                        <td class="px-3 py-2 align-top" dusk="cupom-usos-{{ $item->id }}">{{ $item->usos_count }}{{ $item->limite_usos !== null ? ' / '.$item->limite_usos : '' }}</td>
                        <td class="px-3 py-2 align-top" dusk="cupom-situacao-{{ $item->id }}">{{ $item->ativo ? 'Ativo' : 'Inativo' }}</td>
                        <td class="px-3 py-2 align-top text-right whitespace-nowrap">
                            <x-button type="button" variant="secondary" size="sm" wire:click="editarCupom('{{ $item->id }}')" dusk="cupom-editar-{{ $item->id }}">Editar</x-button>
                            @if ($item->ativo)
                                <x-button type="button" variant="secondary" size="sm" wire:click="abrirConcessao('{{ $item->id }}')" dusk="cupom-conceder-{{ $item->id }}">Conceder</x-button>
                            @endif
                            <x-button type="button" variant="secondary" size="sm" wire:click="detalhar('{{ $item->id }}')" dusk="cupom-detalhes-{{ $item->id }}">Detalhes</x-button>
                            <x-button type="button" variant="secondary" size="sm" wire:click="alternarCupom('{{ $item->id }}')" dusk="cupom-alternar-{{ $item->id }}">{{ $item->ativo ? 'Desativar' : 'Ativar' }}</x-button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-4 text-[color:var(--color-secondary)]" dusk="cupons-vazio">Nenhum cupom criado ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Detalhe: concessões e usos --}}
    @if ($detalheCupom)
        <x-card dusk="cupom-detalhe">
            <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Cupom {{ $detalheCupom->codigo }}: concessões e usos</h2>

            <h3 class="text-base font-medium mt-0 mb-2">Concessões ({{ $concessoesDetalhe->count() }}{{ $concessoesDetalhe->count() === \App\Livewire\Backoffice\Cupons::DETALHE_LIMITE ? '+' : '' }})</h3>
            <ul class="list-none p-0 m-0 mb-4 text-sm" dusk="cupom-detalhe-concessoes">
                @forelse ($concessoesDetalhe as $c)
                    <li class="py-1">{{ $c->usuario->nome }} ({{ $c->usuario->email }}), em {{ $c->concedida_em->format('d/m/Y') }}</li>
                @empty
                    <li class="py-1 text-[color:var(--color-secondary)]">Nenhuma concessão.</li>
                @endforelse
            </ul>

            <h3 class="text-base font-medium mt-0 mb-2">Usos ({{ $usosDetalhe->count() }}{{ $usosDetalhe->count() === \App\Livewire\Backoffice\Cupons::DETALHE_LIMITE ? '+' : '' }})</h3>
            <ul class="list-none p-0 m-0 text-sm" dusk="cupom-detalhe-usos">
                @forelse ($usosDetalhe as $u)
                    <li class="py-1">{{ $u->usuario->nome }} ({{ $u->usuario->email }}), em {{ $u->usado_em->format('d/m/Y') }}, desconto de R$ {{ number_format($u->desconto_centavos / 100, 2, ',', '.') }}</li>
                @empty
                    <li class="py-1 text-[color:var(--color-secondary)]">Nenhum uso.</li>
                @endforelse
            </ul>
        </x-card>
    @endif
</div>
