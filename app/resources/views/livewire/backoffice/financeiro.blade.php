@php
    $reais = fn (int $centavos) => 'R$ '.number_format($centavos / 100, 2, ',', '.');
    $data = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->timezone('America/Sao_Paulo')->format('d/m/Y H:i') : '—';
    $dia = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->timezone('America/Sao_Paulo')->format('d/m/Y') : '—';
    $erroDe = fn (string $campo, string $dusk) => $errors->has($campo)
        ? '<p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="'.$dusk.'">'.e($errors->first($campo)).'</p>'
        : '';
    $motivosDevolucao = [
        'assinatura_cancelada' => 'Pago depois do cancelamento da assinatura',
        'assinatura_antiga' => 'Pago na assinatura antiga, trocada por outro cartão',
        'assinatura_duplicada' => 'Pago com assinatura já existente',
    ];
    $alerta = 'border-[color:var(--color-destructive)] border-2';
@endphp

<div>
    <x-page-header :title="'Financeiro'"
                   :subtitle="'Assinaturas e cobranças, reembolso e controle de nota fiscal. Reembolso é total; a emissão da nota é feita fora do sistema.'" />

    @if ($mensagem)
        <p class="mb-4 text-sm text-[color:var(--color-secondary)]" role="status" dusk="financeiro-mensagem">{{ $mensagem }}</p>
    @endif
    @if ($erro)
        <p class="mb-4 text-sm text-[color:var(--color-destructive)]" role="alert" dusk="financeiro-erro">{{ $erro }}</p>
    @endif

    {{-- Painel de reembolso --}}
    @if ($reembolsoAberto)
        <x-card class="mb-6" dusk="reembolso-painel">
            <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Reembolsar {{ $reais($reembolsoAberto->valor_centavos) }}</h2>
            <p class="m-0 mb-2 text-sm">{{ $reembolsoAberto->usuario?->nome }} ({{ $reembolsoAberto->usuario?->email }}) · {{ $reembolsoAberto->descricao }} · paga em {{ $data($reembolsoAberto->pago_em) }}</p>
            <p class="m-0 mb-3 text-sm" dusk="reembolso-previa">{{ $previa->descricao() }}</p>
            @if ($previa->creditosUsados > 0)
                <p class="m-0 mb-3 text-sm font-medium text-[color:var(--color-destructive)]" role="alert" dusk="reembolso-creditos-usados">
                    Atenção: o usuário já usou {{ $previa->creditosUsados }} {{ $previa->creditosUsados === 1 ? 'crédito' : 'créditos' }} desta compra.
                </p>
            @endif
            <div class="mb-3">
                <x-label for="reembolso-motivo">Motivo do reembolso</x-label>
                <textarea id="reembolso-motivo" wire:model="motivo" rows="3" maxlength="{{ \App\Livewire\Backoffice\Financeiro::MOTIVO_MAX }}" dusk="reembolso-motivo" class="input min-h-20"></textarea>
                {!! $erroDe('motivo', 'erro-reembolso-motivo') !!}
            </div>
            <div class="flex gap-2">
                <x-botao-confirmar titulo="Confirmar reembolso" mensagem="O estorno de {{ $reais($reembolsoAberto->valor_centavos) }} será pedido ao provedor e o que a cobrança concedeu será retirado. Não dá para desfazer." acao="confirmarReembolso" dusk="reembolso-confirmar">Confirmar reembolso</x-botao-confirmar>
                <x-button type="button" variant="secondary" wire:click="cancelarReembolso" dusk="reembolso-cancelar">Cancelar</x-button>
            </div>
        </x-card>
    @endif

    {{-- Painel de nota fiscal --}}
    @if ($notaAberta)
        <x-card class="mb-6" dusk="nota-painel">
            <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Marcar nota fiscal como emitida</h2>
            <p class="m-0 mb-3 text-sm">{{ $notaAberta->usuario?->nome }} · {{ $notaAberta->descricao }} · {{ $reais($notaAberta->valor_centavos) }}</p>
            <form wire:submit="confirmarNota" novalidate class="flex flex-wrap items-end gap-3">
                <div>
                    <x-label for="nota-numero">Número da nota (opcional)</x-label>
                    <x-input type="text" id="nota-numero" wire:model="notaNumero" maxlength="{{ \App\Services\Cobranca\MarcarNotaFiscal::NUMERO_MAX }}" dusk="nota-numero" class="w-full"/>
                    {!! $erroDe('notaNumero', 'erro-nota-numero') !!}
                </div>
                <x-button type="submit" variant="primary" dusk="nota-confirmar">Marcar como emitida</x-button>
                <x-button type="button" variant="secondary" wire:click="cancelarNota" dusk="nota-cancelar">Cancelar</x-button>
            </form>
        </x-card>
    @endif

    {{-- Atenção: devolver e contestações --}}
    @if ($devolver->isNotEmpty() || $contestadas->isNotEmpty())
        <h2 class="text-lg font-medium text-[color:var(--color-destructive)] mt-0 mb-3">Atenção</h2>
        @if ($devolver->isNotEmpty())
            <x-card class="mb-4 {{ $alerta }}" dusk="financeiro-devolver">
                <h3 class="text-base font-medium mt-0 mb-2">Pagamentos a devolver ({{ $devolver->count() }})</h3>
                <p class="m-0 mb-3 text-sm text-[color:var(--color-secondary)]">O dinheiro entrou, mas nada foi concedido ao usuário. Reembolse.</p>
                <ul class="list-none p-0 m-0 text-sm">
                    @foreach ($devolver as $i)
                        <li class="py-2 border-t border-[color:var(--color-border)] flex flex-wrap items-center justify-between gap-2" dusk="devolver-{{ $i->id }}" wire:key="dev-{{ $i->id }}">
                            <span><strong>{{ $reais($i->valor_centavos) }}</strong> · {{ $i->usuario?->nome }} ({{ $i->usuario?->email }}) · {{ $dia($i->pago_em) }} · {{ $motivosDevolucao[$i->devolucao_motivo] ?? $i->devolucao_motivo }}</span>
                            @if ($i->reembolso_estado === null)
                                <x-button type="button" variant="destructive" size="sm" wire:click="abrirReembolso('{{ $i->id }}')" dusk="devolver-reembolsar-{{ $i->id }}">Reembolsar</x-button>
                            @else
                                <span class="pill">Reembolso {{ str_replace('_', ' ', $i->reembolso_estado) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
        @if ($contestadas->isNotEmpty())
            <x-card class="mb-4 {{ $alerta }}" dusk="financeiro-contestadas">
                <h3 class="text-base font-medium mt-0 mb-2">Contestações (chargeback) ({{ $contestadas->count() }})</h3>
                <ul class="list-none p-0 m-0 text-sm">
                    @foreach ($contestadas as $i)
                        <li class="py-2 border-t border-[color:var(--color-border)]" dusk="contestada-{{ $i->id }}" wire:key="cont-{{ $i->id }}">
                            <strong>{{ $reais($i->valor_centavos) }}</strong> · {{ $i->usuario?->nome }} ({{ $i->usuario?->email }}) · {{ $dia($i->pago_em) }} · {{ $i->descricao }}
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    @endif

    {{-- Filtros --}}
    <x-card class="mb-6">
        <form wire:submit="filtrar" novalidate class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end" role="search">
            <div>
                <x-label for="fin-busca">Usuário (nome ou e-mail)</x-label>
                <x-input type="search" id="fin-busca" wire:model="busca" dusk="fin-busca" class="w-full"/>
            </div>
            <div>
                <x-label for="fin-status-assinatura">Status da assinatura</x-label>
                <x-select id="fin-status-assinatura" wire:model="statusAssinatura" dusk="fin-status-assinatura" class="w-full">
                    <option value="">Todos</option>
                    @foreach ($statusAssinaturas as $s)
                        <option value="{{ $s->value }}">{{ $s->rotulo() }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <x-label for="fin-status-cobranca">Status da cobrança</x-label>
                <x-select id="fin-status-cobranca" wire:model="statusCobranca" dusk="fin-status-cobranca" class="w-full">
                    <option value="">Todos</option>
                    @foreach ($statusCobrancas as $s)
                        <option value="{{ $s->value }}">{{ $s->rotulo() }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <x-label for="fin-de">De</x-label>
                <x-input type="date" id="fin-de" wire:model="de" dusk="fin-de" class="w-full"/>
                {!! $erroDe('de', 'erro-fin-de') !!}
            </div>
            <div>
                <x-label for="fin-ate">Até</x-label>
                <x-input type="date" id="fin-ate" wire:model="ate" dusk="fin-ate" class="w-full"/>
                {!! $erroDe('ate', 'erro-fin-ate') !!}
            </div>
            <div class="flex gap-2 md:col-span-5">
                <x-button type="submit" variant="primary" dusk="fin-filtrar">Filtrar</x-button>
                <x-button type="button" variant="secondary" wire:click="limparFiltros" dusk="fin-limpar">Limpar</x-button>
            </div>
        </form>
    </x-card>

    {{-- Assinaturas --}}
    <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Assinaturas</h2>
    <div class="card p-0 overflow-x-auto mb-2">
        <table class="w-full text-sm text-left" dusk="financeiro-assinaturas">
            <thead class="text-[color:var(--color-secondary)]">
                <tr>
                    <th scope="col" class="px-3 py-2">Usuário</th>
                    <th scope="col" class="px-3 py-2">Plano</th>
                    <th scope="col" class="px-3 py-2">Status</th>
                    <th scope="col" class="px-3 py-2">Início</th>
                    <th scope="col" class="px-3 py-2">Vencimento</th>
                    <th scope="col" class="px-3 py-2">Preço</th>
                    <th scope="col" class="px-3 py-2">Meio</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assinaturas as $a)
                    <tr class="border-t border-[color:var(--color-border)]" wire:key="ass-{{ $a->id }}" dusk="assinatura-{{ $a->id }}">
                        <td class="px-3 py-2">{{ $a->usuario?->nome }}<div class="text-[color:var(--color-secondary)] [overflow-wrap:anywhere]">{{ $a->usuario?->email }}</div></td>
                        <td class="px-3 py-2">{{ $a->trial ? 'Teste grátis' : ($a->plano?->nome ?? '—').($a->ciclo ? ' ('.$a->ciclo.')' : '') }}</td>
                        <td class="px-3 py-2" dusk="assinatura-status-{{ $a->id }}">{{ $a->status->rotulo() }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $dia($a->data_inicio) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $dia($a->proximo_vencimento) }}</td>
                        <td class="px-3 py-2">{{ $a->preco_snapshot_centavos === null ? '—' : $reais($a->preco_snapshot_centavos) }}</td>
                        <td class="px-3 py-2">{{ $a->trial ? '—' : ($a->meio_pagamento === 'pix' ? 'Pix' : 'Cartão') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-4 text-[color:var(--color-secondary)]" dusk="assinaturas-vazio">Nenhuma assinatura encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mb-8">{{ $assinaturas->links(data: ['scrollTo' => false]) }}</div>

    {{-- Cobranças --}}
    <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Cobranças</h2>
    <div class="card p-0 overflow-x-auto mb-2">
        <table class="w-full text-sm text-left" dusk="financeiro-cobrancas">
            <thead class="text-[color:var(--color-secondary)]">
                <tr>
                    <th scope="col" class="px-3 py-2">Usuário</th>
                    <th scope="col" class="px-3 py-2">Cobrança</th>
                    <th scope="col" class="px-3 py-2">Valor</th>
                    <th scope="col" class="px-3 py-2">Status</th>
                    <th scope="col" class="px-3 py-2">Arrependimento</th>
                    <th scope="col" class="px-3 py-2">Nota fiscal</th>
                    <th scope="col" class="px-3 py-2"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cobrancas as $i)
                    @php $sinal = $sinais[$i->id] ?? null; @endphp
                    <tr class="border-t border-[color:var(--color-border)] {{ $i->status === \App\Domain\Cobranca\EstadoCobranca::Contestada || ($i->devolucao_motivo && $i->status === \App\Domain\Cobranca\EstadoCobranca::Paga) ? 'bg-[color:var(--color-neutral)] border-l-4 border-l-[color:var(--color-destructive)]' : '' }}" wire:key="cob-{{ $i->id }}" dusk="cobranca-{{ $i->id }}">
                        <td class="px-3 py-2 align-top">{{ $i->usuario?->nome }}<div class="text-[color:var(--color-secondary)] [overflow-wrap:anywhere]">{{ $i->usuario?->email }}</div></td>
                        <td class="px-3 py-2 align-top">{{ $i->descricao }}<div class="text-[color:var(--color-secondary)]">{{ $data($i->pago_em ?? $i->created_at) }}</div></td>
                        <td class="px-3 py-2 align-top whitespace-nowrap">{{ $reais($i->valor_centavos) }}</td>
                        <td class="px-3 py-2 align-top" dusk="cobranca-status-{{ $i->id }}">
                            {{ $i->status->rotulo() }}
                            @if ($i->status === \App\Domain\Cobranca\EstadoCobranca::Contestada)
                                <strong class="block text-[color:var(--color-destructive)]">Contestação aberta</strong>
                            @endif
                            @if ($i->devolucao_motivo && $i->status === \App\Domain\Cobranca\EstadoCobranca::Paga)
                                <strong class="block text-[color:var(--color-destructive)]" dusk="cobranca-devolver-{{ $i->id }}">Devolver: {{ $motivosDevolucao[$i->devolucao_motivo] ?? $i->devolucao_motivo }}</strong>
                            @endif
                            @if ($i->reembolso_estado === 'aguardando_autorizacao')
                                <span class="block text-xs" dusk="cobranca-aguardando-{{ $i->id }}">Estorno do Pix pedido: autorize no painel do Asaas (ações críticas). O estado muda sozinho quando o Asaas avisar.</span>
                            @elseif ($i->reembolso_estado === 'em_andamento')
                                <span class="block text-xs" dusk="cobranca-andamento-{{ $i->id }}">Estorno em andamento no provedor.</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 align-top text-xs" dusk="cobranca-arrependimento-{{ $i->id }}">
                            @if ($sinal)
                                <span class="block">{{ $sinal['no_prazo'] ? 'Dentro dos 7 dias (dia '.$sinal['dias'].')' : 'Fora dos 7 dias' }}</span>
                                <span class="block">{{ $sinal['fez_analise'] ? 'Houve análise desde a contratação' : 'Sem análise desde a contratação' }}</span>
                                @if ($sinal['no_prazo'] && ! $sinal['fez_analise'])
                                    <strong class="block">Cabe arrependimento</strong>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2 align-top" dusk="cobranca-nota-{{ $i->id }}">
                            {{ $i->nf_emitida_em ? 'Emitida em '.$dia($i->nf_emitida_em).($i->nf_numero ? ' (nº '.$i->nf_numero.')' : '') : '—' }}
                        </td>
                        <td class="px-3 py-2 align-top text-right whitespace-nowrap">
                            @if ($i->status === \App\Domain\Cobranca\EstadoCobranca::Paga && $i->reembolso_estado === null)
                                <x-button type="button" variant="secondary" size="sm" wire:click="abrirReembolso('{{ $i->id }}')" dusk="cobranca-reembolsar-{{ $i->id }}">Reembolsar</x-button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-4 text-[color:var(--color-secondary)]" dusk="cobrancas-vazio">Nenhuma cobrança encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mb-8">{{ $cobrancas->links(data: ['scrollTo' => false]) }}</div>

    {{-- Sem nota fiscal --}}
    <h2 class="text-lg font-medium text-[color:var(--color-primary)] mt-0 mb-3">Pagas sem nota fiscal</h2>
    <div class="card p-0 overflow-x-auto mb-8">
        <table class="w-full text-sm text-left" dusk="financeiro-sem-nota">
            <thead class="text-[color:var(--color-secondary)]">
                <tr>
                    <th scope="col" class="px-3 py-2">Usuário</th>
                    <th scope="col" class="px-3 py-2">Cobrança</th>
                    <th scope="col" class="px-3 py-2">Valor</th>
                    <th scope="col" class="px-3 py-2">Paga em</th>
                    <th scope="col" class="px-3 py-2"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($semNota as $i)
                    <tr class="border-t border-[color:var(--color-border)]" wire:key="nf-{{ $i->id }}" dusk="sem-nota-{{ $i->id }}">
                        <td class="px-3 py-2">{{ $i->usuario?->nome }}<div class="text-[color:var(--color-secondary)] [overflow-wrap:anywhere]">{{ $i->usuario?->email }}</div></td>
                        <td class="px-3 py-2">{{ $i->descricao }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $reais($i->valor_centavos) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $data($i->pago_em) }}</td>
                        <td class="px-3 py-2 text-right">
                            <x-button type="button" variant="secondary" size="sm" wire:click="abrirNota('{{ $i->id }}')" dusk="sem-nota-marcar-{{ $i->id }}">Marcar nota emitida</x-button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-[color:var(--color-secondary)]" dusk="sem-nota-vazio">Todas as cobranças pagas têm nota fiscal.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
