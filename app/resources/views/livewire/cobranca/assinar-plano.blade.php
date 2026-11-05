@php
    use App\Domain\Cobranca\CicloCobranca;

    $reais = fn (int $centavos): string => 'R$ '.number_format($centavos / 100, 2, ',', '.');
    $analises = fn (int $n): string => $n.' '.($n === 1 ? 'análise' : 'análises');
    $anual = $cicloAtual === CicloCobranca::Anual;
    $pix = $meioAtual === \App\Domain\Cobranca\MeioPagamento::Pix;
@endphp
<div x-data x-init="$nextTick(() => $refs.titulo?.focus())">
    <x-breadcrumb :items="[['label' => 'Planos e preços', 'url' => route('planos')], ['label' => 'Assinar']]"/>

    @if ($plano === null)
        <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4">Assinar plano</h1>
        <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4 max-w-[640px] flex flex-col gap-3"
             role="alert" dusk="screen-checkout-indisponivel">
            <p class="m-0 text-[color:var(--color-primary)]">Este plano não está mais disponível.</p>
            <div><x-button :href="route('planos')" variant="secondary">Ver os planos</x-button></div>
        </div>
    @elseif ($vigente)
        <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4">Assinar plano {{ $plano->nome }}</h1>
        <x-drm.aviso-inline variant="info" dusk="screen-checkout-ja-assinante">
            @if ($vigente->pagoComPix())
                <p class="m-0 mb-2">Você já tem o plano {{ $planoVigente?->nome }}, que termina em {{ \App\Support\DataExibicao::data($vigente->proximo_vencimento) }} e não renova sozinho.</p>
            @else
                <p class="m-0 mb-2">Você já tem o plano {{ $planoVigente?->nome }}, {{ $vigente->status === \App\Domain\Cobranca\StatusAssinatura::Cancelada ? 'com a renovação cancelada e acesso até' : 'que renova em' }} {{ \App\Support\DataExibicao::data($vigente->proximo_vencimento) }}.</p>
            @endif
            <div class="flex flex-wrap gap-3">
                @if ($urlRenovar)<x-button :href="$urlRenovar" variant="primary" dusk="screen-meuplano-renovar-pix">Pagar renovação</x-button>@endif
                @if ($urlMeuPlano)<x-button :href="$urlMeuPlano" variant="secondary">Ver meu plano</x-button>@endif
                @if ($urlTrocar)<x-button :href="$urlTrocar" variant="secondary">Trocar de plano</x-button>@endif
                <x-button :href="route('planos')" variant="secondary">Voltar aos planos</x-button>
            </div>
        </x-drm.aviso-inline>
    @else
        <h1 x-ref="titulo" tabindex="-1" dusk="screen-checkout-titulo"
            class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4">
            Assinar plano {{ $plano->nome }}
        </h1>

        @if (! $emailConfirmado)
            <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4 max-w-[640px] flex flex-col gap-3"
                 role="alert" dusk="screen-checkout-email-nao-confirmado">
                <p class="m-0 font-medium text-[color:var(--color-primary)]">Confirme seu e-mail para assinar.</p>
                <p class="m-0 text-sm text-[color:var(--color-primary)]">Enviamos um link para {{ $email }}. Depois de confirmar, volte aqui.</p>
                @if ($confirmacaoReenviada)
                    <p class="m-0 text-sm text-[color:var(--color-secondary)]" role="status" dusk="screen-checkout-confirmacao-reenviada">Enviamos um novo link. Veja sua caixa de entrada.</p>
                @endif
                <div class="flex flex-wrap gap-3">
                    <x-button type="button" variant="primary" wire:click="reenviarConfirmacao" dusk="screen-checkout-reenviar">Reenviar e-mail de confirmação</x-button>
                    <x-button :href="route('planos')" variant="secondary">Voltar aos planos</x-button>
                </div>
            </div>
        @else
            @if ($emTeste)
                <x-drm.aviso-inline variant="info" dusk="screen-checkout-vindo-do-teste">
                    Ao assinar, seu teste termina. A análise que sobrou do teste não soma à cota do plano.
                </x-drm.aviso-inline>
            @endif

            <fieldset class="m-0 mb-4 p-0 border-0 min-w-0" dusk="screen-checkout-ciclo">
                <legend class="sr-only">Ciclo de cobrança</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-[640px]">
                    @foreach ([CicloCobranca::Mensal, CicloCobranca::Anual] as $c)
                        @php $p = $c->precoDe($plano->preco_mensal_centavos, $plano->preco_anual_centavos); @endphp
                        <label class="relative block cursor-pointer">
                            <input type="radio" class="peer sr-only" name="ciclo" value="{{ $c->value }}" wire:model.live="ciclo" dusk="screen-checkout-ciclo-{{ $c->value }}">
                            <span class="flex flex-col justify-center min-h-[44px] rounded-md border-2 border-[color:var(--color-border)] px-3 py-2 text-sm
                                         text-[color:var(--color-secondary)]
                                         peer-checked:border-[color:var(--color-tertiary)] peer-checked:bg-[color:var(--color-surface)] peer-checked:text-[color:var(--color-primary)]
                                         peer-focus-visible:ring-2 peer-focus-visible:ring-[color:var(--color-ring)]">
                                <span class="font-medium">{{ $c->rotulo() }} — {{ $reais($p) }} {{ $c->porExtenso() }}</span>
                                <span>{{ $c === CicloCobranca::Mensal ? 'renova todo mês' : ($pix ? '12 meses, você paga a renovação' : 'renova a cada 12 meses').($economia > 0 ? " · economia de {$economia}%" : '') }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="flex flex-col gap-6 lg:grid lg:grid-cols-[3fr_2fr] lg:items-start lg:gap-8">
                <section aria-labelledby="resumo-titulo" class="lg:order-2 lg:sticky lg:top-4">
                    <x-card dusk="screen-checkout-resumo">
                        <h2 id="resumo-titulo" class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0 mb-3">Resumo do pedido</h2>
                        <dl class="m-0 grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 text-sm">
                            <dt class="text-[color:var(--color-secondary)]">Plano</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-plano">{{ $plano->nome }} · {{ strtolower($cicloAtual->rotulo()) }}</dd>
                            <dt class="text-[color:var(--color-secondary)]">Preço</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-preco">{{ $reais($preco) }}</dd>
                            <dt class="text-[color:var(--color-secondary)]">Desconto</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-desconto">{{ $aplicado ? '− '.$reais($aplicado->preco->desconto).' ('.$aplicado->cupom->codigo.')' : 'Sem desconto' }}</dd>
                            <dt class="font-medium text-[color:var(--color-primary)] border-t border-[color:var(--color-border)] pt-2">A pagar hoje</dt>
                            <dd class="m-0 text-right font-medium text-[color:var(--color-primary)] border-t border-[color:var(--color-border)] pt-2" dusk="screen-checkout-resumo-a-pagar">{{ $reais($aPagar) }}</dd>
                            <dt class="text-[color:var(--color-secondary)]">Ciclo</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-ciclo">{{ $cupomResumo ? $cupomResumo['ciclo'] : ($pix ? 'Anual, não renova sozinho — avisamos '.$avisoDias.' dias antes' : $cicloAtual->rotulo().', renova sozinho no cartão') }}</dd>
                            <dt class="text-[color:var(--color-secondary)]">Próxima cobrança</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-proxima">{{ $cupomResumo ? $cupomResumo['proxima'] : ($pix ? 'Não há cobrança automática. Renovação em '.\App\Support\DataExibicao::data($proximaCobranca) : \App\Support\DataExibicao::data($proximaCobranca).' · '.$reais($preco)) }}</dd>
                            <dt class="text-[color:var(--color-secondary)]">Depois</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-depois">{{ $cupomResumo ? $cupomResumo['depois'] : ($pix ? 'Você paga '.$reais($preco).' por um novo QR Code, se quiser continuar' : $reais($preco).' '.$cicloAtual->porExtenso()) }}</dd>
                        </dl>
                    </x-card>
                    <p class="mt-3 mb-0 text-sm text-[color:var(--color-secondary)]" dusk="screen-checkout-beneficios">
                        Inclui {{ $analises($plano->cota_mensal) }} por mês, empresas sem limite e o Raio X do Negócio.
                    </p>
                </section>

                <section class="lg:order-1 flex flex-col gap-4">
                    <div class="flex flex-col gap-3">
                        <x-cobranca.cupom :aplicado="$aplicado" :concedido="$cupomConcedido" :aberto="$cupomAberto" :erro="$erroCupom" :esgotou="$cupomEsgotou"/>
                        <h2 class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0">Pagamento</h2>
                        {{-- Mensal: só cartão, sem seletor (DDR-010 item 4). --}}
                        @if ($semCobranca)
                            <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-checkout-sem-pagamento">Você não precisa pagar nada agora.</p>
                        @elseif ($anual)
                            <x-cobranca.seletor-meio :atual="$meio" :opcoes="[
                                ['cartao', 'Cartão de crédito', 'libera na hora, renova sozinho'],
                                ['pix', 'Pix', 'libera quando o banco confirmar, você paga a renovação'],
                            ]"/>
                        @endif
                        @if ($semCobranca)
                            {{-- Sem meio de pagamento: a contratação é ativada sem cobrança (ADR-010 4.7). --}}
                        @elseif ($pix)
                            <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-checkout-pagamento">Pix · o QR Code vale por {{ $minutosPix }} minutos. O plano começa quando o banco confirmar o pagamento.</p>
                        @else
                            <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-checkout-pagamento">Cartão de crédito, digitado na página segura do processador de pagamento. Nós guardamos só a bandeira e os 4 últimos dígitos.</p>
                        @endif
                    </div>

                    @if ($personificando)
                        <p class="m-0 rounded-md border border-[color:var(--color-border)] p-3 text-sm text-[color:var(--color-primary)]" dusk="screen-checkout-admin">
                            Você está acessando como administrador. Contratar e pagar não está disponível nesse modo.
                        </p>
                    @endif

                    @if ($falhaDoProvedor)
                        <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4 flex flex-col gap-1"
                             role="alert" dusk="screen-checkout-falha">
                            <p class="m-0 font-medium text-[color:var(--color-primary)]">{{ $pix ? 'Não conseguimos gerar o QR Code agora. Nada foi cobrado.' : 'Não conseguimos falar com o processador de pagamento agora. Não é problema com o seu cartão.' }}</p>
                            <p class="m-0 text-sm text-[color:var(--color-secondary)]">Tente de novo. Se continuar assim, escreva para eb@ebparcerias.com.</p>
                        </div>
                    @endif

                    <form wire:submit="assinar" class="flex flex-col gap-3 m-0" novalidate>
                        <label class="flex gap-2.5 items-start text-sm font-normal cursor-pointer min-h-[44px]">
                            <input type="checkbox" wire:model="aceito" dusk="screen-checkout-aceite"
                                   aria-describedby="@error('aceito')aceite-erro @enderror"
                                   class="mt-1 accent-[color:var(--color-tertiary)]">
                            <span class="text-[color:var(--color-primary)]">
                                Li e aceito o
                                <a href="{{ route('termos.termo-adesao') }}" target="_blank" rel="noopener" class="link">Termo de Adesão</a> (v2) e a
                                <a href="{{ route('termos.politica-privacidade') }}" target="_blank" rel="noopener" class="link">Política de Privacidade</a>.
                            </span>
                        </label>
                        @error('aceito')
                            <p id="aceite-erro" class="text-[color:var(--color-destructive)] text-sm m-0" dusk="screen-checkout-error-aceite">{{ $message }}</p>
                        @enderror

                        <x-button type="submit" variant="primary" :disabled="$personificando"
                                  wire:loading.attr="disabled" wire:target="assinar"
                                  dusk="screen-checkout-primary-cta">
                            <span wire:loading.remove wire:target="assinar">{{ $semCobranca ? 'Ativar sem pagar' : ($pix ? 'Gerar QR Code Pix' : 'Assinar e pagar').' · '.$reais($aPagar) }}</span>
                            <span wire:loading wire:target="assinar">{{ $pix ? 'Gerando QR Code…' : 'Abrindo o pagamento…' }}</span>
                        </x-button>
                        <p class="m-0 text-sm text-[color:var(--color-secondary)]">
                            {{ $pix ? 'Este plano não renova sozinho, então não há o que cancelar.' : 'Cancele quando quiser em Meu plano.' }} Reembolso em até 7 dias, se você ainda não tiver feito nenhuma análise.
                        </p>
                    </form>

                    <div><x-link :href="route('planos')" dusk="screen-checkout-voltar">Voltar aos planos</x-link></div>
                </section>
            </div>
        @endif
    @endif
</div>
