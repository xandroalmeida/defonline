@php
    use App\Domain\Cobranca\CicloCobranca;
    use App\Domain\Cobranca\SituacaoDoPlano as S;

    $reais = fn (int $centavos): string => 'R$ '.number_format($centavos / 100, 2, ',', '.');
    $dia = fn ($d): string => \App\Support\DataExibicao::data($d);
    $analises = fn (int $n): string => $n.' '.($n === 1 ? 'análise' : 'análises');
    $ciclo = $assinatura?->ciclo !== null ? CicloCobranca::tryFrom($assinatura->ciclo) : null;
    $acaoBloqueada = $personificando;
    $trocaAguardando = $troca !== null && $troca->aguardando();
    $trocaConcluida = $troca !== null && $troca->status === 'concluida';
    $trocaFalhou = $troca !== null && ! $trocaAguardando && ! $trocaConcluida;
    $esperando = $retornoCartao !== null && ($trocaAguardando || ($trocaConcluida && $troca->cobra_agora));
    // Um aviso de cobrança por vez (DDR-009 item 4): o de maior severidade.
    $avisoDeCobranca = match (true) {
        $pix && $janelaPix && $situacao === S::Ativo => ['info', 'Seu plano anual termina em '.$dia($vencimento).'. Pague a renovação com Pix para não perder o acesso.'],
        $situacao === S::EmAtraso => ['alerta', 'Não conseguimos cobrar a renovação de '.$dia($vencimento).'. Atualize o cartão para manter o acesso.'],
        $temLotePerto => ['info', 'Você tem créditos que vencem em breve. Veja as datas em Análises.'],
        default => null,
    };
    $mensagemArrependimento = $arrependimentoDias === null ? null
        : 'Você contratou '.($arrependimentoDias === 0 ? 'hoje' : "há {$arrependimentoDias} ".($arrependimentoDias === 1 ? 'dia' : 'dias')).' e ainda não fez nenhuma análise: dá para pedir o reembolso integral em '.$canalArrependimento.'.';
    $mensagemCancelar = 'Você continua com acesso até '.($vencimento ? $dia($vencimento) : '').' e não será cobrado de novo. Dá para reativar até lá.'
        .($mensagemArrependimento ? ' '.$mensagemArrependimento : '');
    $mensagemCancelarEmAtraso = 'A renovação será cancelada e nada mais será cobrado. Como o ciclo de '.($vencimento ? $dia($vencimento) : '').' não foi pago, o acesso é encerrado agora, não no fim do ciclo. Seu histórico de cobranças e diagnósticos continua disponível.';
    $h2 = 'text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0 mb-3';
@endphp
<div class="max-w-[1100px]" x-data x-init="$nextTick(() => $refs.titulo?.focus())"
     @if ($esperando) wire:poll.3s @endif>
    <h1 x-ref="titulo" tabindex="-1" dusk="screen-meuplano-titulo"
        class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4">Meu plano</h1>

    @if ($personificando)
        <x-drm.aviso-inline variant="info" dusk="screen-meuplano-admin">Você está acessando como administrador. Aqui é só leitura.</x-drm.aviso-inline>
    @endif

    @if ($avisoDeCobranca !== null)
        <x-drm.aviso-inline :variant="$avisoDeCobranca[0]" dusk="screen-meuplano-aviso">{{ $avisoDeCobranca[1] }}</x-drm.aviso-inline>
    @endif

    <div aria-live="polite" dusk="screen-meuplano-resultado">
        @if ($mensagem !== null)
            <x-drm.aviso-inline :variant="$tipoMensagem === 'erro' ? 'alerta' : 'info'" dusk="screen-meuplano-mensagem-{{ $tipoMensagem }}">{{ $mensagem }}</x-drm.aviso-inline>
        @endif
        @if ($retornoCartao !== null && $troca !== null)
            @if ($esperando)
                <x-drm.aviso-inline variant="info" dusk="screen-meuplano-troca-aguardando">
                    {{ $trocaConcluida ? 'Cartão atualizado. Estamos cobrando a renovação em atraso…' : 'Estamos confirmando o seu novo cartão…' }}
                </x-drm.aviso-inline>
            @elseif ($trocaConcluida)
                <x-drm.aviso-inline variant="info" dusk="screen-meuplano-troca-concluida">Cartão atualizado.{{ $cartao ? " {$cartao}." : '' }}</x-drm.aviso-inline>
            @elseif ($trocaFalhou)
                <x-drm.aviso-inline variant="alerta" dusk="screen-meuplano-troca-falhou">Não conseguimos confirmar o novo cartão. O cartão anterior continua valendo.</x-drm.aviso-inline>
            @endif
        @endif
    </div>

    @if ($situacao === S::SemPlano && $creditosTotal === 0)
        {{-- CA-7: sem plano e sem créditos, a tela é a oferta. --}}
        <section aria-labelledby="oferta-titulo" class="flex flex-col gap-4 mb-6" dusk="screen-meuplano-oferta">
            <h2 id="oferta-titulo" class="{{ $h2 }} !mb-0">Você ainda não tem um plano</h2>
            <p class="m-0 text-[color:var(--color-secondary)] text-sm">Escolha como quer fazer suas análises.</p>
            @if ($trialElegivel && $urlTeste)
                <x-button :href="$urlTeste" variant="primary" dusk="screen-meuplano-oferta-teste" class="self-start">Começar teste grátis</x-button>
            @endif
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @foreach ($planos as $p)
                    <x-card dusk="screen-meuplano-oferta-plano">
                        <p class="m-0 font-medium text-[color:var(--color-primary)]">{{ $p->nome }}</p>
                        <p class="m-0 text-sm text-[color:var(--color-secondary)]">{{ $analises($p->cota_mensal) }} por mês · {{ $reais($p->preco_mensal_centavos) }} por mês</p>
                        <div class="mt-3"><x-button :href="route('checkout.assinatura', ['plano' => $p->id, 'ciclo' => 'mensal'])" variant="secondary" size="sm">Assinar {{ $p->nome }}</x-button></div>
                    </x-card>
                @endforeach
                @foreach ($pacotes as $k)
                    <x-card dusk="screen-meuplano-oferta-pacote">
                        <p class="m-0 font-medium text-[color:var(--color-primary)]">{{ $k->nome }}</p>
                        <p class="m-0 text-sm text-[color:var(--color-secondary)]">{{ $analises($k->quantidade_creditos) }} · {{ $reais($k->preco_centavos) }}</p>
                        @if ($urlComprar)
                            <div class="mt-3"><x-button :href="route('creditos.comprar', ['pacote' => $k->id])" variant="secondary" size="sm">Comprar {{ $k->nome }}</x-button></div>
                        @endif
                    </x-card>
                @endforeach
            </div>
            @if ($urlPlanos)
                <div><x-link :href="$urlPlanos" dusk="screen-meuplano-oferta-planos">Ver planos e preços</x-link></div>
            @endif
        </section>
    @else
        <div class="flex flex-col gap-4 lg:grid lg:grid-cols-2 lg:items-start mb-6">
            <section aria-labelledby="plano-titulo" dusk="screen-meuplano-plano">
                <x-card>
                    <h2 id="plano-titulo" class="{{ $h2 }}">Plano</h2>

                    @if ($situacao === S::SemPlano)
                        <p class="m-0 mb-3 text-[color:var(--color-primary)]" dusk="screen-meuplano-situacao">Sem plano. Você usa créditos avulsos.</p>
                        <div class="flex flex-wrap gap-3">
                            @if ($urlPlanos)<x-button :href="$urlPlanos" variant="secondary" dusk="screen-meuplano-ver-planos">Ver planos</x-button>@endif
                        </div>
                    @else
                        <p class="m-0 mb-1 flex flex-wrap items-center gap-2 text-[color:var(--color-primary)]">
                            <span class="font-medium">
                                @if ($assinatura->trial) Teste gratuito
                                @else {{ $plano?->nome ?? 'Plano' }}@if ($ciclo) · {{ strtolower($ciclo->rotulo()) }}@endif
                                @endif
                            </span>
                            <span class="pill" dusk="screen-meuplano-situacao">{{ $situacao->rotulo() }}</span>
                        </p>

                        @switch($situacao)
                            @case(S::Teste)
                                <p class="m-0 mb-1 text-sm text-[color:var(--color-primary)]">Termina em {{ $dia($vencimento) }} · {{ $analises($assinatura->cota_restante_ciclo) }}</p>
                                @break
                            @case(S::Cancelado)
                                <p class="m-0 mb-1 text-sm text-[color:var(--color-primary)]" dusk="screen-meuplano-acesso-ate">Você cancelou a renovação. Seu acesso continua até {{ $dia($vencimento) }}. Não haverá nova cobrança.</p>
                                @break
                            @case(S::Encerrado)
                                <p class="m-0 mb-1 text-sm text-[color:var(--color-primary)]" dusk="screen-meuplano-encerrado">Seu {{ $assinatura->trial ? 'teste' : 'plano' }} terminou em {{ $dia($vencimento) }}. Seus diagnósticos continuam disponíveis.</p>
                                @if ($pix && $urlRenovar)<p class="m-0 mb-1 text-sm text-[color:var(--color-secondary)]">Plano anual com Pix: para voltar, pague a renovação com um novo QR Code.</p>@endif
                                @break
                            @case(S::EmAtraso)
                                <p class="m-0 mb-1 text-sm text-[color:var(--color-primary)]">Não conseguimos cobrar a renovação. Atualize o cartão para manter o acesso, ou cancele a renovação: nesse caso o acesso é encerrado na hora, porque o ciclo não foi pago.</p>
                                @break
                            @default
                                @if ($renovaSozinha)
                                    <p class="m-0 mb-1 text-sm text-[color:var(--color-primary)]" dusk="screen-meuplano-renovacao">
                                        @if ($assinatura->preco_snapshot_centavos !== null && $ciclo){{ $reais($assinatura->preco_snapshot_centavos) }} {{ $ciclo->porExtenso() }} · @endif renova em {{ $dia($vencimento) }}
                                    </p>
                                @else
                                    <p class="m-0 mb-1 text-sm text-[color:var(--color-primary)]" dusk="screen-meuplano-acesso-ate">Este plano não renova sozinho. Termina em {{ $dia($vencimento) }}.</p>
                                @endif
                        @endswitch

                        @if ($trocaAgendada !== null && $situacao === S::Ativo)
                            <div class="mb-3 text-sm text-[color:var(--color-primary)]" aria-live="polite" dusk="screen-troca-agendado">
                                <p class="m-0">Troca para o plano {{ $trocaAgendada->nome }}{{ $assinatura->troca_ciclo ? ' · '.$assinatura->troca_ciclo : '' }} agendada para {{ $dia($vencimento) }}. Dá para desfazer até lá.</p>
                                <button type="button" class="btn btn--ghost mt-1" wire:click="desfazerTroca" @disabled($acaoBloqueada) dusk="screen-troca-desfazer">Desfazer troca</button>
                            </div>
                        @endif

                        @if ($paga && $renovaSozinha && $cartao && ! in_array($situacao, [S::Encerrado], true))
                            <p class="m-0 mb-3 text-sm text-[color:var(--color-secondary)]" dusk="screen-meuplano-cartao">Cartão {{ $cartao }}</p>
                        @endif

                        <div class="flex flex-wrap gap-3 mt-3">
                            @if ($situacao === S::Ativo && $renovaSozinha)
                                @if ($urlTrocarPlano)
                                    <x-button :href="$urlTrocarPlano" variant="secondary" :disabled="$acaoBloqueada" dusk="screen-meuplano-trocar-plano">Trocar plano</x-button>
                                @endif
                                <x-button type="button" variant="secondary" wire:click="abrirTrocaDeCartao" :disabled="$acaoBloqueada" dusk="screen-meuplano-trocar-cartao">Trocar cartão</x-button>
                                <button type="button" class="btn btn--ghost" @disabled($acaoBloqueada) dusk="screen-meuplano-cancelar"
                                        x-on:click="$dispatch('pedir-confirmacao', {
                                            titulo: 'Cancelar a renovação?',
                                            mensagem: {{ \Illuminate\Support\Js::from($mensagemCancelar) }},
                                            rotulo: 'Cancelar renovação',
                                            voltar: 'Voltar',
                                            acao: () => $wire.cancelarRenovacao(),
                                        })">Cancelar renovação</button>
                            @elseif ($situacao === S::Cancelado && $renovaSozinha)
                                <x-button type="button" variant="primary" wire:click="reativarRenovacao" :disabled="$acaoBloqueada" dusk="screen-meuplano-reativar">Reativar renovação</x-button>
                            @elseif ($situacao === S::EmAtraso)
                                <x-button type="button" variant="primary" wire:click="abrirTrocaDeCartao" :disabled="$acaoBloqueada" dusk="screen-meuplano-trocar-cartao">Trocar cartão</x-button>
                                @if ($renovaSozinha)
                                    <button type="button" class="btn btn--ghost" @disabled($acaoBloqueada) dusk="screen-meuplano-cancelar"
                                            x-on:click="$dispatch('pedir-confirmacao', {
                                                titulo: 'Cancelar a renovação?',
                                                mensagem: {{ \Illuminate\Support\Js::from($mensagemCancelarEmAtraso) }},
                                                rotulo: 'Cancelar renovação',
                                                voltar: 'Voltar',
                                                acao: () => $wire.cancelarRenovacao(),
                                            })">Cancelar renovação</button>
                                @endif
                            @elseif ($pix && $urlRenovar && ($situacao === S::Ativo ? $janelaPix : $situacao === S::Encerrado))
                                <x-button :href="$urlRenovar" variant="primary" dusk="screen-meuplano-renovar-pix">{{ $situacao === S::Encerrado ? 'Renovar com Pix' : 'Pagar renovação' }}</x-button>
                                @if ($situacao === S::Encerrado && $urlPlanos)<x-button :href="$urlPlanos" variant="secondary" dusk="screen-meuplano-assinar">Assinar um plano</x-button>@endif
                            @elseif ($situacao === S::Teste || $situacao === S::Encerrado)
                                @if ($urlPlanos)<x-button :href="$urlPlanos" variant="primary" dusk="screen-meuplano-assinar">Assinar um plano</x-button>@endif
                            @endif
                        </div>

                        @if ($trocandoCartao && in_array($situacao, [S::Ativo, S::EmAtraso], true))
                            <div class="mt-4 pt-4 border-t border-[color:var(--color-border)] flex flex-col gap-3" dusk="screen-meuplano-troca-cartao">
                                <p class="m-0 text-sm text-[color:var(--color-primary)]">
                                    Você digita o novo cartão na página segura do processador de pagamento. O cartão atual só é desativado depois que o novo for confirmado
                                    @if ($situacao === S::EmAtraso), e a renovação em atraso é cobrada no cartão novo.@else, e a próxima cobrança continua em {{ $dia($vencimento) }}.@endif
                                </p>
                                <div class="flex flex-wrap gap-3">
                                    <x-button type="button" variant="primary" wire:click="trocarCartao" wire:loading.attr="disabled" wire:target="trocarCartao" :disabled="$acaoBloqueada" dusk="screen-meuplano-salvar-cartao">
                                        <span wire:loading.remove wire:target="trocarCartao">{{ $situacao === S::EmAtraso ? 'Salvar e cobrar agora' : 'Salvar cartão' }}</span>
                                        <span wire:loading wire:target="trocarCartao">Abrindo o pagamento…</span>
                                    </x-button>
                                    <x-button type="button" variant="secondary" wire:click="fecharTrocaDeCartao" dusk="screen-meuplano-fechar-cartao">Agora não</x-button>
                                </div>
                            </div>
                        @endif
                    @endif
                </x-card>
            </section>

            <section aria-labelledby="analises-titulo" dusk="screen-meuplano-analises">
                <x-card>
                    <h2 id="analises-titulo" class="{{ $h2 }}">Análises</h2>

                    @if (in_array($situacao, [S::Teste, S::Ativo, S::Cancelado, S::EmAtraso], true))
                        <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-meuplano-cota">
                            <span class="text-[color:var(--color-secondary)]">Do plano</span>
                            {{ $assinatura->cota_restante_ciclo }} de {{ $assinatura->cota_snapshot }} {{ $assinatura->cota_snapshot === 1 ? 'restante' : 'restantes' }} neste ciclo
                        </p>
                        <p class="m-0 mb-3 text-sm text-[color:var(--color-secondary)]">
                            @if ($situacao === S::Cancelado || ! $renovaSozinha || $assinatura->trial)
                                Acesso até {{ $dia($vencimento) }} (não acumula)
                            @else
                                Renova em {{ $dia($renovaCotaEm) }} (não acumula)
                            @endif
                        </p>
                    @endif

                    <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-meuplano-creditos">
                        <span class="text-[color:var(--color-secondary)]">Créditos</span>
                        {{ $creditosTotal === 0 ? 'nenhum' : $creditosTotal.' no total' }}
                    </p>
                    @if ($lotes !== [])
                        <ul class="m-0 mt-1 mb-3 pl-5 text-sm text-[color:var(--color-primary)]" dusk="screen-meuplano-lotes">
                            @foreach ($lotes as $l)
                                <li dusk="screen-meuplano-lote">
                                    {{ $l['restante'] }} — vencem em {{ $dia($l['expira']) }}
                                    @if ($l['perto'])<span class="pill ml-1" dusk="screen-meuplano-lote-perto">vence em breve</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="flex flex-wrap gap-3 mt-2">
                        @if ($urlComprar)
                            <x-button :href="route('creditos.comprar')" variant="secondary" :disabled="$acaoBloqueada" dusk="screen-meuplano-comprar-creditos">Comprar créditos</x-button>
                        @endif
                    </div>

                    <p class="m-0 mt-3 text-sm text-[color:var(--color-secondary)]" dusk="screen-meuplano-drm">
                        @if (in_array($situacao, [S::Teste, S::Ativo, S::Cancelado, S::EmAtraso], true))
                            O Raio X do Negócio está incluído no plano.
                        @else
                            O Raio X do Negócio não está incluído nos créditos.
                            @if ($urlPlanos)<a href="{{ $urlPlanos }}" class="link">Assinar para ter o Raio X</a>@endif
                        @endif
                    </p>
                </x-card>
            </section>
        </div>
    @endif

    <section aria-labelledby="cobrancas-titulo" class="mb-6" dusk="screen-meuplano-cobrancas">
        <x-card>
            <h2 id="cobrancas-titulo" class="{{ $h2 }}">Cobranças</h2>

            @if ($cobrancasFalharam)
                <div class="flex flex-wrap items-center gap-3" role="alert" dusk="screen-meuplano-cobrancas-erro">
                    <p class="m-0 text-sm text-[color:var(--color-primary)]">Não conseguimos carregar as cobranças agora.</p>
                    <x-button type="button" variant="secondary" size="sm" wire:click="recarregar">Tentar de novo</x-button>
                </div>
            @elseif ($cobrancas === [])
                <p class="m-0 text-sm text-[color:var(--color-secondary)]" dusk="screen-meuplano-cobrancas-vazio">Você ainda não fez nenhuma compra.</p>
            @else
                <table class="hidden md:table w-full text-sm text-left border-collapse">
                    <caption class="sr-only">Cobranças</caption>
                    <thead>
                        <tr class="text-[color:var(--color-secondary)]">
                            <th scope="col" class="py-2 pr-3 font-medium">Data</th>
                            <th scope="col" class="py-2 pr-3 font-medium">Descrição</th>
                            <th scope="col" class="py-2 pr-3 font-medium">Valor</th>
                            <th scope="col" class="py-2 pr-3 font-medium">Desconto</th>
                            <th scope="col" class="py-2 pr-3 font-medium">Meio</th>
                            <th scope="col" class="py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cobrancas as $c)
                            <tr class="border-t border-[color:var(--color-border)]" dusk="screen-meuplano-cobranca">
                                <td class="py-2 pr-3">{{ $dia($c['data']) }}</td>
                                <td class="py-2 pr-3">{{ $c['descricao'] }}</td>
                                <td class="py-2 pr-3">{{ $reais($c['valor_centavos']) }}</td>
                                <td class="py-2 pr-3">{{ $c['desconto_centavos'] > 0 ? $reais($c['desconto_centavos']) : '—' }}</td>
                                <td class="py-2 pr-3">{{ $c['meio'] }}</td>
                                <td class="py-2"><span class="pill">{{ $c['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <ul class="md:hidden m-0 p-0 list-none flex flex-col gap-3">
                    @foreach ($cobrancas as $c)
                        <li class="border-t border-[color:var(--color-border)] pt-3 first:border-t-0 first:pt-0">
                            <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                                <dt class="text-[color:var(--color-secondary)]">Data</dt><dd class="m-0">{{ $dia($c['data']) }}</dd>
                                <dt class="text-[color:var(--color-secondary)]">Descrição</dt><dd class="m-0">{{ $c['descricao'] }}</dd>
                                <dt class="text-[color:var(--color-secondary)]">Valor</dt><dd class="m-0">{{ $reais($c['valor_centavos']) }}</dd>
                                <dt class="text-[color:var(--color-secondary)]">Desconto</dt><dd class="m-0">{{ $c['desconto_centavos'] > 0 ? $reais($c['desconto_centavos']) : '—' }}</dd>
                                <dt class="text-[color:var(--color-secondary)]">Meio</dt><dd class="m-0">{{ $c['meio'] }}</dd>
                                <dt class="text-[color:var(--color-secondary)]">Status</dt><dd class="m-0"><span class="pill">{{ $c['status'] }}</span></dd>
                            </dl>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </section>

    <p class="m-0 text-sm text-[color:var(--color-secondary)]" dusk="screen-meuplano-arrependimento">
        Arrependimento: em até 7 dias da contratação, se você ainda não fez nenhuma análise, peça o reembolso em
        <a href="mailto:{{ $canalArrependimento }}" class="link">{{ $canalArrependimento }}</a>.
    </p>
</div>
