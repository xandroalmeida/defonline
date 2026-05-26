@php
    /**
     * Quiz de Indústria — STORY-027.
     * Labels e helps copiados do Anexo A (especificacao/V2/anexos/anexo-A-campos-quiz.md);
     * mudança de copy requer aprovação do PO (CA-3).
     *
     * Máscaras: Alpine inline igual STORY-014 (sem lib extra).
     *   - R$:  ponto como milhar, vírgula como decimal (até 2 casas).
     *   - dias: só dígitos, sufixo "dias".
     *   - %:    decimal com vírgula, sufixo "%".
     *   - CPF:  000.000.000-00.
     */
    $tituloBloco = [
        1 => 'Identificação',
        2 => 'DRE e Operação',
        3 => 'Balanço',
        4 => 'Contexto e Captação',
    ][$bloco_atual] ?? '';

    // Máscara R$ inline (Alpine). Mantém vírgula brasileira; o submit canonicaliza.
    $maskBrl = <<<'JS'
        const raw = $el.value.replace(/\D/g, '');
        if (raw === '') { $el.value = ''; return; }
        const cents = raw.padStart(3, '0');
        const inteiro = cents.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.').replace(/^0+(?=\d)/, '') || '0';
        const decimal = cents.slice(-2);
        $el.value = inteiro + ',' + decimal;
    JS;

    // Máscara dias (só dígitos, máx 4 — 9999 cobre prazo absurdo + folga para warning).
    $maskDias = <<<'JS'
        $el.value = ($el.value.replace(/\D/g, '') || '').slice(0, 4);
    JS;

    // Máscara % — até XX,YY (0..100,99 visual; validação rígida no servidor).
    $maskPct = <<<'JS'
        const raw = $el.value.replace(/\D/g, '');
        if (raw === '') { $el.value = ''; return; }
        const trimmed = raw.slice(0, 5);
        if (trimmed.length <= 2) { $el.value = trimmed; return; }
        $el.value = trimmed.slice(0, -2).replace(/^0+(?=\d)/, '') + ',' + trimmed.slice(-2);
    JS;

    // Máscara CPF — 000.000.000-00.
    $maskCpf = <<<'JS'
        const d = $el.value.replace(/\D/g, '').slice(0, 11);
        let m = d;
        if (d.length > 9) m = d.slice(0,3) + '.' + d.slice(3,6) + '.' + d.slice(6,9) + '-' + d.slice(9);
        else if (d.length > 6) m = d.slice(0,3) + '.' + d.slice(3,6) + '.' + d.slice(6);
        else if (d.length > 3) m = d.slice(0,3) + '.' + d.slice(3);
        $el.value = m;
    JS;
@endphp

<div x-data
     x-on:focar-campo.window="$nextTick(() => document.getElementById($event.detail.campo)?.focus())">
    <x-page-header :title="'Diagnóstico — ' . ($empresa->nome_fantasia ?: $empresa->razao_social)"
                   :subtitle="'Bloco ' . $bloco_atual . ' de 4 — ' . $tituloBloco"/>

    {{-- Banner de retomada / expiração — UX suave, não erro. --}}
    @if ($retomando_rascunho)
        <p class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-neutral)] px-3 py-2 text-sm text-[color:var(--color-primary)] mb-4"
           dusk="quiz-retomando-rascunho">
            Retomando seu rascunho — você parou no Bloco {{ $bloco_atual }}.
        </p>
    @elseif ($rascunho_expirou)
        <p class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-neutral)] px-3 py-2 text-sm text-[color:var(--color-primary)] mb-4"
           dusk="quiz-rascunho-expirou">
            Seu rascunho expirou. Vamos começar do início.
        </p>
    @endif

    {{-- Barra de progresso visual + acessível. --}}
    <div class="flex items-center gap-2 mb-6" role="progressbar"
         aria-valuemin="1" aria-valuemax="4" aria-valuenow="{{ $bloco_atual }}"
         aria-label="Progresso do quiz">
        @for ($i = 1; $i <= 4; $i++)
            <div class="flex-1 h-2 rounded-full {{ $i <= $bloco_atual ? 'bg-[color:var(--color-tertiary)]' : 'bg-[color:var(--color-neutral)]' }}"
                 dusk="quiz-progresso-{{ $i }}"></div>
        @endfor
    </div>

    <x-card>
        <form wire:submit="submeter" novalidate class="flex flex-col gap-4">

            {{-- ============================== BLOCO 1 ============================== --}}
            @if ($bloco_atual === 1)
                <div class="flex flex-col gap-4" dusk="quiz-bloco-1">
                    <div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <x-label for="Q01" class="mb-0">Setor de atividade</x-label>
                            <x-help id="Q01" :text="config('quiz.help-industria.campos.Q01')" label="Setor de atividade"/>
                        </div>
                        <x-input type="text" id="Q01" name="Q01"
                                 :value="ucfirst($Q01)" readonly disabled
                                 dusk="quiz-Q01"/>
                        <p class="text-sm text-[color:var(--color-secondary)] mt-1 mb-0">
                            Esta versão atende apenas Indústria. Outros setores entram em onda futura.
                        </p>
                    </div>
                </div>
            @endif

            {{-- ============================== BLOCO 2 ============================== --}}
            @if ($bloco_atual === 2)
                <div class="flex flex-col gap-4" dusk="quiz-bloco-2">
                    <p class="text-sm text-[color:var(--color-secondary)] m-0">
                        Médias mensais dos últimos 12 meses — o motor anualiza (× 12) no cálculo.
                    </p>

                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q08', 'label' => 'Compras (média mensal)',
                        'help' => 'Mercadorias, matéria-prima, insumos.',
                        'mask' => $maskBrl,
                    ])
                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q09', 'label' => 'Vendas (média mensal)',
                        'help' => 'Receita operacional bruta.',
                        'mask' => $maskBrl,
                    ])
                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q14', 'label' => 'Custos e despesas fixas',
                        'help' => 'Folha, contador, aluguel, condomínio, energia, água, telefone, internet, retirada de sócios.',
                        'mask' => $maskBrl,
                    ])
                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q15', 'label' => 'Custos e despesas variáveis',
                        'help' => 'Fretes, comissões, tributos, despesas variáveis.',
                        'mask' => $maskBrl,
                    ])
                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q16', 'label' => 'Despesas financeiras',
                        'help' => 'Juros pagos a bancos, antecipações de cartões e desconto de boletos.',
                        'mask' => $maskBrl,
                    ])
                    @include('livewire.diagnostico.partials.campo-dias', [
                        'id' => 'Q10', 'label' => 'PMC — prazo médio de fornecedores',
                        'help' => 'Em quantos dias você paga seus fornecedores em média.',
                        'mask' => $maskDias,
                    ])
                    @include('livewire.diagnostico.partials.campo-dias', [
                        'id' => 'Q11', 'label' => 'PME — giro de estoque',
                        'help' => 'Quantos dias seus produtos/insumos ficam parados em estoque.',
                        'mask' => $maskDias,
                    ])
                    @include('livewire.diagnostico.partials.campo-dias', [
                        'id' => 'Q12', 'label' => 'PMR — prazo médio a clientes',
                        'help' => 'Em quantos dias seus clientes pagam você em média.',
                        'mask' => $maskDias,
                    ])
                    @include('livewire.diagnostico.partials.campo-pct', [
                        'id' => 'Q13', 'label' => 'Inadimplência',
                        'help' => 'Percentual médio de atraso dos clientes.',
                        'mask' => $maskPct,
                    ])
                </div>
            @endif

            {{-- ============================== BLOCO 3 ============================== --}}
            @if ($bloco_atual === 3)
                <div class="flex flex-col gap-4" dusk="quiz-bloco-3">
                    <p class="text-sm text-[color:var(--color-secondary)] m-0">
                        Saldos atuais — uma foto do balanço de hoje.
                    </p>

                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q02', 'label' => 'Recursos disponíveis',
                        'help' => 'Caixa + bancos + aplicações de liquidez imediata.',
                        'mask' => $maskBrl,
                    ])
                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q03', 'label' => 'Contas a receber',
                        'help' => 'Vendas a prazo a clientes — cheques, boletos, cartão, fiado.',
                        'mask' => $maskBrl,
                    ])
                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q04', 'label' => 'Estoque',
                        'help' => 'Produtos, mercadorias, matéria-prima, insumos.',
                        'mask' => $maskBrl,
                    ])
                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q05', 'label' => 'Patrimônio (imobilizado)',
                        'help' => 'Imóveis, equipamentos, veículos — valor de venda rápida.',
                        'mask' => $maskBrl,
                    ])
                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q06', 'label' => 'Dívidas financeiras',
                        'help' => 'Empréstimos, financiamentos, dívidas não operacionais.',
                        'mask' => $maskBrl,
                    ])
                    @include('livewire.diagnostico.partials.campo-brl', [
                        'id' => 'Q07', 'label' => 'Fornecedores a pagar',
                        'help' => 'Obrigações comerciais em aberto.',
                        'mask' => $maskBrl,
                    ])

                    {{-- STORY-034 — validações cruzadas DRE × Balanço (§6.6). Banner inline,
                         não-bloqueante: aparece ao tentar avançar quando há inconsistência. --}}
                    @if (count($alertas_cruzados) > 0)
                        <div class="rounded-md border border-[color:var(--color-farol-amarelo)]/40 bg-[color:var(--color-farol-amarelo)]/10 p-4 flex flex-col gap-3"
                             role="alert" aria-live="polite" dusk="quiz-alertas-cruzados">
                            <p class="font-semibold text-[color:var(--color-primary)] m-0">
                                Detectamos inconsistências. Você quer corrigir agora?
                            </p>
                            <ul class="flex flex-col gap-3 list-none m-0 p-0">
                                @foreach ($alertas_cruzados as $alerta)
                                    <li class="flex flex-col gap-1.5" dusk="quiz-alerta-{{ $alerta['regra'] }}">
                                        <p class="text-sm text-[color:var(--color-primary)] m-0">{{ $alerta['mensagem'] }}</p>
                                        <div>
                                            <x-button type="button" variant="secondary" size="sm"
                                                      wire:click="irParaCampo('{{ $alerta['campo_foco'] }}')"
                                                      dusk="quiz-alerta-corrigir-{{ $alerta['regra'] }}">
                                                {{ $alerta['botao_label'] }}
                                            </x-button>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="pt-1 border-t border-[color:var(--color-border)]">
                                <x-button type="button" variant="primary" size="sm"
                                          wire:click="continuarComAlertas"
                                          dusk="quiz-alertas-continuar">
                                    Continuar mesmo assim
                                </x-button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ============================== BLOCO 4 ============================== --}}
            @if ($bloco_atual === 4)
                <div class="flex flex-col gap-4" dusk="quiz-bloco-4">
                    <fieldset class="rounded-md border border-[color:var(--color-border)] p-4 flex flex-col gap-2">
                        <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-[color:var(--color-secondary)]">
                            Q17 — Necessita captar recursos?
                            <x-help id="Q17" :text="config('quiz.help-industria.campos.Q17')" label="Necessidade de captação"/>
                        </legend>
                        @foreach ([['sim', 'Sim'], ['nao', 'Não']] as [$valor, $rotulo])
                            <label class="flex gap-2.5 items-center cursor-pointer text-[color:var(--color-primary)]">
                                <input type="radio" name="Q17" value="{{ $valor }}"
                                       wire:model.live="Q17"
                                       class="accent-[color:var(--color-tertiary)]"
                                       dusk="quiz-Q17-{{ $valor }}">
                                <span>{{ $rotulo }}</span>
                            </label>
                        @endforeach
                        @error('Q17')
                            <p class="text-[color:var(--color-destructive)] text-sm m-0" dusk="quiz-erro-Q17">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    @if ($Q17 === 'sim')
                        <div class="flex flex-col gap-4" dusk="quiz-bloco-4-captacao">
                            @include('livewire.diagnostico.partials.campo-brl', [
                                'id' => 'Q18', 'label' => 'Valor que precisa captar',
                                'help' => 'Montante desejado.',
                                'mask' => $maskBrl,
                            ])
                            @include('livewire.diagnostico.partials.campo-brl', [
                                'id' => 'Q19', 'label' => 'Endividamento total no mercado',
                                'help' => 'Bancos + particulares.',
                                'mask' => $maskBrl,
                            ])
                            @include('livewire.diagnostico.partials.campo-brl', [
                                'id' => 'Q20', 'label' => 'Venda mensal com cartão / duplicatas',
                                'help' => 'Base de cálculo da capacidade de garantia (3× esse valor). Deve ser inferior à venda mensal total (Q09).',
                                'mask' => $maskBrl,
                            ])
                            @include('livewire.diagnostico.partials.campo-cpf', [
                                'id' => 'Q21', 'label' => 'CPF do sócio 1',
                                'mask' => $maskCpf,
                            ])
                            @include('livewire.diagnostico.partials.campo-cpf', [
                                'id' => 'Q22', 'label' => 'CPF do sócio 2',
                                'mask' => $maskCpf,
                            ])
                            @include('livewire.diagnostico.partials.campo-cpf', [
                                'id' => 'Q23', 'label' => 'CPF do sócio 3',
                                'mask' => $maskCpf,
                            ])
                        </div>
                    @endif
                </div>
            @endif

            {{-- ============================== NAVEGAÇÃO ============================== --}}
            @error('submeter')
                <p class="text-[color:var(--color-destructive)] text-sm m-0 rounded-md border border-[color:var(--color-destructive)]/30 bg-[color:var(--color-destructive)]/5 px-3 py-2"
                   dusk="quiz-erro-submeter">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap gap-2 justify-between pt-2 border-t border-[color:var(--color-border)]">
                @if ($bloco_atual > 1)
                    <x-button type="button" variant="secondary" wire:click="blocoAnterior"
                              dusk="quiz-voltar">
                        Voltar
                    </x-button>
                @else
                    <span></span>
                @endif

                @if ($bloco_atual < 4)
                    <x-button type="button" variant="primary" wire:click="proximoBloco"
                              wire:loading.attr="disabled" wire:target="proximoBloco"
                              dusk="quiz-proximo">
                        <span wire:loading.remove wire:target="proximoBloco">Próximo</span>
                        <span wire:loading wire:target="proximoBloco">Salvando…</span>
                    </x-button>
                @else
                    <x-button type="submit" variant="primary"
                              wire:loading.attr="disabled" wire:target="submeter"
                              dusk="quiz-calcular">
                        <span wire:loading.remove wire:target="submeter">Calcular diagnóstico</span>
                        <span wire:loading wire:target="submeter">Calculando…</span>
                    </x-button>
                @endif
            </div>
        </form>
    </x-card>
</div>
