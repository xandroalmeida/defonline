@php
    use App\Domain\Cobranca\CicloCobranca;
    use App\Services\Cobranca\TrocaNegada as N;

    $reais = fn (int $centavos): string => 'R$ '.number_format($centavos / 100, 2, ',', '.');
    $analises = fn (int $n): string => $n.' '.($n === 1 ? 'análise' : 'análises');
    $dia = fn ($d): string => \App\Support\DataExibicao::data($d);
    $h1 = 'text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4';
    $pix = $assinatura?->pagoComPix() ?? false;
    $upgrade = $previa?->imediata ?? false;
    $cobra = $upgrade && ! $previa->semCobranca;
    $nomeCiclo = strtolower($cicloNovo->rotulo());
    // STORY-090 CA-3: plano que veio de cupom gratuito não tem cartão no provedor; a tela diz o que acontece de fato.
    $semCartao = $assinatura !== null && $assinatura->id_externo === null && $assinatura->semMeioDePagamento();
    $prazoDoCartao = $previa === null ? null : ($previa->descontoNovo?->fim($cicloNovo, $assinatura->data_inicio) ?? $previa->proximaCobranca);
    $valorHoje = $reais($cobra ? $previa->aPagarCentavos : 0);
    $rotuloBotao = $upgrade ? 'Trocar para o '.$destino?->nome.' · '.$valorHoje : 'Agendar troca para o '.$destino?->nome;
    $nomeBotao = $upgrade ? 'Trocar para o plano '.$destino?->nome.' por '.$valorHoje : 'Agendar troca para o plano '.$destino?->nome;
    $textoNegada = match ($negada) {
        N::EM_ATRASO => 'Atualize o cartão antes de trocar de plano.',
        N::CANCELADA => 'Você cancelou a renovação do seu plano. Reative a renovação em Meu plano antes de trocar de plano.',
        N::SEM_ASSINATURA => 'Você não tem um plano para trocar. Escolha um plano para assinar.',
        N::PLANO_INDISPONIVEL => 'Este plano não está mais à venda.',
        N::IGUAL => 'Este já é o seu plano.',
        N::PIX_SO_ANUAL => 'O plano pago com Pix só troca para planos anuais.',
        default => 'Não foi possível trocar de plano agora.',
    };
@endphp
<div x-data x-init="$nextTick(() => $refs.titulo?.focus())">
    <x-breadcrumb :items="[['label' => 'Planos e preços', 'url' => route('planos')], ['label' => 'Trocar plano']]"/>

    @if ($destino === null)
        <h1 x-ref="titulo" tabindex="-1" class="{{ $h1 }}">Trocar plano</h1>
        <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4 max-w-[640px] flex flex-col gap-3" role="alert" dusk="screen-troca-indisponivel">
            <p class="m-0 text-[color:var(--color-primary)]">Este plano não está mais disponível.</p>
            <div><x-button :href="route('planos')" variant="secondary">Ver os planos</x-button></div>
        </div>
    @elseif ($resultado !== null)
        <section class="max-w-[640px] flex flex-col gap-4" role="status" aria-live="polite" dusk="screen-troca-{{ $resultado === 'agendada' ? 'agendado' : 'aplicada' }}">
            @if ($resultado === 'agendada')
                <h1 x-ref="titulo" tabindex="-1" class="{{ $h1 }}">Troca agendada</h1>
                <p class="m-0 text-[color:var(--color-primary)]">Troca agendada para {{ $dia($previa?->vigenteAte ?? $assinatura?->proximo_vencimento) }}. Dá para desfazer até lá, em Meu plano.</p>
            @else
                <h1 x-ref="titulo" tabindex="-1" class="{{ $h1 }}">Plano trocado</h1>
                @if ($assinatura !== null && $assinatura->cota_restante_ciclo < $assinatura->cota_snapshot)
                    <p class="m-0 text-[color:var(--color-primary)]" dusk="screen-troca-aplicada-cota">Você agora está no plano {{ $destino->nome }}. Neste ciclo você tem {{ $assinatura->cota_restante_ciclo }} {{ $assinatura->cota_restante_ciclo === 1 ? 'análise' : 'análises' }}, e sua cota de {{ $analises($destino->cota_mensal) }} por mês vale a partir de {{ $dia($assinatura->proxima_cota_em ?? $assinatura->proximo_vencimento) }}.</p>
                @else
                    <p class="m-0 text-[color:var(--color-primary)]" dusk="screen-troca-aplicada-cota">Você agora está no plano {{ $destino->nome }}. Sua cota é de {{ $analises($destino->cota_mensal) }} e você já pode usá-las.</p>
                @endif
            @endif
            <div class="flex flex-wrap gap-3">
                <x-button :href="route('meu-plano')" variant="primary" dusk="screen-troca-ver-meu-plano">Ver meu plano</x-button>
            </div>
        </section>
    @elseif ($negada !== null)
        <h1 x-ref="titulo" tabindex="-1" class="{{ $h1 }}">Trocar para o plano {{ $destino->nome }}</h1>
        <x-drm.aviso-inline :variant="$negada === N::EM_ATRASO ? 'alerta' : 'info'" dusk="screen-troca-negada">
            {{ $textoNegada }}
        </x-drm.aviso-inline>
        <div class="flex flex-wrap gap-3">
            <x-button :href="route('meu-plano')" variant="secondary">Voltar para Meu plano</x-button>
        </div>
    @else
        <h1 x-ref="titulo" tabindex="-1" dusk="screen-troca-titulo" class="{{ $h1 }}">Trocar para o plano {{ $destino->nome }}</h1>

        @if ($personificando)
            <x-drm.aviso-inline variant="info" dusk="screen-troca-admin">Você está acessando como administrador. Aqui é só leitura.</x-drm.aviso-inline>
        @endif

        @if (! $pix)
            <fieldset class="m-0 mb-4 p-0 border-0 min-w-0" dusk="screen-troca-ciclo">
                <legend class="sr-only">Ciclo de cobrança</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 lg:w-[calc((100%-2rem)*0.6)]">
                    @foreach ([CicloCobranca::Mensal, CicloCobranca::Anual] as $c)
                        @php $p = $c->precoDe($destino->preco_mensal_centavos, $destino->preco_anual_centavos); @endphp
                        <label class="relative block cursor-pointer">
                            <input type="radio" class="peer sr-only" name="ciclo" value="{{ $c->value }}" wire:model.live="ciclo" dusk="screen-troca-ciclo-{{ $c->value }}">
                            <span class="flex flex-col justify-center min-h-[44px] rounded-md border-2 border-[color:var(--color-border)] px-3 py-2 text-sm text-[color:var(--color-secondary)]
                                         peer-checked:border-[color:var(--color-tertiary)] peer-checked:bg-[color:var(--color-surface)] peer-checked:text-[color:var(--color-primary)]
                                         peer-focus-visible:ring-2 peer-focus-visible:ring-[color:var(--color-ring)]">
                                <span class="font-medium">{{ $c->rotulo() }} — {{ $reais($p) }} {{ $c->porExtenso() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        @if ($erro !== null)
            <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4 max-w-[640px] mb-4" role="alert" dusk="screen-troca-falha">
                <p class="m-0 font-medium text-[color:var(--color-primary)]">{{ $erro }}</p>
            </div>
        @endif

        @if ($previa !== null)
            <div class="flex flex-col gap-6 lg:grid lg:grid-cols-[3fr_2fr] lg:gap-8">
                <section aria-labelledby="resumo-titulo" class="lg:order-2 lg:self-start lg:sticky lg:top-4" role="region">
                    <x-card dusk="screen-troca-resumo">
                        <h2 id="resumo-titulo" class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0 mb-3">Resumo do pedido</h2>
                        <dl class="m-0 grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-4 gap-y-2 text-sm">
                            <dt class="text-[color:var(--color-secondary)]">Plano</dt>
                            <dd class="m-0 text-right" dusk="screen-troca-resumo-plano">{{ $destino->nome }} · {{ $nomeCiclo }}</dd>
                            @if ($upgrade)
                                <dt class="text-[color:var(--color-secondary)]">{{ $previa->mesmoCiclo ? "Preço do {$destino->nome} pelos {$previa->diasRestantes} dias" : "Preço do {$destino->nome}" }}</dt>
                                <dd class="m-0 text-right" dusk="screen-troca-resumo-preco">{{ $reais($previa->parcelaNovaCentavos) }}</dd>
                                <dt class="text-[color:var(--color-secondary)]">Crédito do {{ $atual?->nome }} não usado</dt>
                                <dd class="m-0 text-right" dusk="screen-troca-credito">− {{ $reais($previa->creditoCentavos) }}</dd>
                            @else
                                <dt class="text-[color:var(--color-secondary)]">Preço do {{ $destino->nome }}</dt>
                                <dd class="m-0 text-right" dusk="screen-troca-resumo-preco">{{ $reais($previa->precoNovoCentavos) }}</dd>
                            @endif
                            <dt class="font-medium text-[color:var(--color-primary)] border-t border-[color:var(--color-border)] pt-2">A pagar hoje</dt>
                            <dd class="m-0 -ml-4 pl-4 text-right font-medium text-[color:var(--color-primary)] border-t border-[color:var(--color-border)] pt-2" dusk="screen-troca-resumo-a-pagar">{{ $reais($cobra ? $previa->aPagarCentavos : 0) }}</dd>
                            <dt class="text-[color:var(--color-secondary)]">Ciclo</dt>
                            <dd class="m-0 text-right">{{ $pix ? 'Anual, não renova sozinho' : ($semCartao ? $cicloNovo->rotulo().', sem cartão cadastrado' : $cicloNovo->rotulo().', renova sozinho no cartão') }}</dd>
                            <dt class="text-[color:var(--color-secondary)]">Próxima cobrança</dt>
                            <dd class="m-0 text-right" dusk="screen-troca-resumo-proxima">{{ $pix ? 'Renovação em '.$dia($previa->proximaCobranca) : ($semCartao ? 'Nenhuma, até você cadastrar um cartão' : $dia($previa->proximaCobranca).' · '.$reais($previa->proximoValorCentavos)) }}</dd>
                            <dt class="text-[color:var(--color-secondary)]">Depois</dt>
                            <dd class="m-0 text-right">{{ $pix ? $reais($previa->precoNovoCheioCentavos).' por ano, por um novo QR Code' : $reais($previa->precoNovoCheioCentavos).' '.$cicloNovo->porExtenso() }}</dd>
                        </dl>
                    </x-card>
                    @if ($semCartao)
                        <x-drm.aviso-inline variant="alerta" dusk="screen-troca-sem-cartao">Você não tem cartão cadastrado, então nada será cobrado automaticamente. Depois da troca, cadastre um cartão em Meu plano até {{ $dia($prazoDoCartao) }}: sem ele, o plano termina nessa data e as funções pagas são bloqueadas.</x-drm.aviso-inline>
                    @endif
                    @if ($previa->descontoTermina)
                        <x-drm.aviso-inline variant="alerta" dusk="screen-troca-desconto-termina">Seu desconto de cupom não vale para o {{ $destino->nome }} {{ $nomeCiclo }}: ele termina nesta troca e o preço passa a ser o cheio.</x-drm.aviso-inline>
                    @elseif ($previa->descontoNovo !== null)
                        <p class="mt-3 mb-0 text-sm text-[color:var(--color-primary)]" dusk="screen-troca-desconto-continua">Seu desconto de cupom continua no {{ $destino->nome }}: {{ $reais($previa->descontoNovo->desconto) }} a menos por ciclo, pelos ciclos que restam.</p>
                    @endif
                    <p class="mt-3 mb-0 text-sm text-[color:var(--color-primary)]" dusk="screen-troca-explicacao">
                        @if ($upgrade && $previa->mesmoCiclo)
                            @if ($cobra)Hoje você paga só a diferença pelos {{ $previa->diasRestantes }} dias que faltam neste ciclo. @else A diferença é pequena demais para cobrar: a troca é feita sem cobrança. @endif
                            @if ($previa->cotaExtraAgora > 0)Neste ciclo você ganha {{ $analises($previa->cotaExtraAgora) }} a mais, proporcionais aos dias que faltam. @else Como faltam poucos dias, neste ciclo você mantém as análises que tem. @endif
                            Sua cota passa a ser de {{ $analises($previa->cotaNova) }} por mês a partir de {{ $dia($previa->cotaCheiaEm) }}.
                        @elseif ($upgrade)
                            Um novo ciclo {{ $nomeCiclo }} começa hoje, e o que você não usou do ciclo atual vira crédito. Sua cota passa a ser de {{ $analises($previa->cotaNova) }} a partir de agora.
                        @else
                            Você continua no {{ $atual?->nome }} até {{ $dia($previa->vigenteAte) }}, com todas as análises do ciclo. Depois disso, passa a ter {{ $analises($previa->cotaNova) }} por mês.
                        @endif
                    </p>
                </section>

                <section class="lg:order-1 flex flex-col gap-4">
                    @if ($cobra)
                        <h2 class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0">Pagamento</h2>
                        @if ($pix)
                            <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-troca-pagamento">Pix · um novo QR Code da diferença, válido por {{ $minutosPix }} minutos. O plano novo vale quando o banco confirmar o pagamento.</p>
                        @else
                            <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-troca-pagamento">Cartão de crédito, digitado na página segura do processador de pagamento. O plano novo vale quando o pagamento for aprovado; se for recusado, seu plano continua o {{ $atual?->nome }}.</p>
                        @endif
                    @endif

                    <div class="flex flex-wrap gap-3">
                        <x-button type="button" variant="primary" wire:click="confirmar" wire:loading.attr="disabled" wire:target="confirmar" :disabled="$personificando" dusk="screen-troca-confirmar" :aria-label="$nomeBotao">
                            <span wire:loading.remove wire:target="confirmar">{{ $rotuloBotao }}</span>
                            <span wire:loading wire:target="confirmar">Trocando…</span>
                        </x-button>
                        <x-button :href="route('meu-plano')" variant="secondary">Voltar</x-button>
                    </div>
                </section>
            </div>
        @endif
    @endif
</div>
