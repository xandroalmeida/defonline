@php
    $reais = fn (int $centavos): string => 'R$ '.number_format($centavos / 100, 2, ',', '.');
@endphp
<div x-data x-init="$nextTick(() => $refs.titulo?.focus())">
    <x-breadcrumb :items="[['label' => 'Planos e preços', 'url' => route('planos')], ['label' => 'Renovar plano']]"/>

    @if ($assinatura === null)
        <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4">Renovar plano</h1>
        <x-drm.aviso-inline variant="info" dusk="screen-meuplano-sem-pix">
            <p class="m-0 mb-2">Você não tem um plano anual pago com Pix para renovar.</p>
            <x-button :href="route('planos')" variant="secondary">Ver os planos</x-button>
        </x-drm.aviso-inline>
    @elseif (! $vigente)
        {{-- Sem pagamento até o fim: encerrado, sem "em atraso", porque nada falhou (CA-5). --}}
        <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4">Plano encerrado</h1>
        <x-drm.aviso-inline variant="info" dusk="screen-meuplano-encerrado">
            <p class="m-0 mb-2">Seu plano terminou em {{ \App\Support\DataExibicao::data($fim) }}. Seus diagnósticos continuam disponíveis.</p>
            <div class="flex flex-wrap gap-3">
                @if ($plano)
                    <x-button :href="route('checkout.assinatura', ['plano' => $plano->id, 'ciclo' => 'anual', 'meio' => 'pix'])" variant="primary" dusk="screen-meuplano-renovar-pix">Renovar com Pix</x-button>
                    <x-button :href="route('checkout.assinatura', ['plano' => $plano->id, 'ciclo' => 'anual'])" variant="secondary">Assinar com cartão</x-button>
                @else
                    <x-button :href="route('planos')" variant="primary">Ver os planos</x-button>
                @endif
            </div>
        </x-drm.aviso-inline>
    @elseif (! $emailConfirmado)
        <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4">Renovar plano {{ $plano?->nome }}</h1>
        <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4 max-w-[640px] flex flex-col gap-3"
             role="alert" dusk="screen-checkout-email-nao-confirmado">
            <p class="m-0 font-medium text-[color:var(--color-primary)]">Confirme seu e-mail para renovar.</p>
            <p class="m-0 text-sm text-[color:var(--color-primary)]">Enviamos um link para {{ $email }}. Depois de confirmar, volte aqui.</p>
            @if ($confirmacaoReenviada)
                <p class="m-0 text-sm text-[color:var(--color-secondary)]" role="status" dusk="screen-checkout-confirmacao-reenviada">Enviamos um novo link. Veja sua caixa de entrada.</p>
            @endif
            <div><x-button type="button" variant="primary" wire:click="reenviarConfirmacao" dusk="screen-checkout-reenviar">Reenviar e-mail de confirmação</x-button></div>
        </div>
    @elseif (! $janelaAberta)
        <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4">Renovar plano {{ $plano?->nome }}</h1>
        <x-drm.aviso-inline variant="info" dusk="screen-meuplano-renovacao-fechada">
            <p class="m-0">Este plano não renova sozinho. Termina em {{ \App\Support\DataExibicao::data($fim) }}. Você poderá pagar a renovação a partir de {{ \App\Support\DataExibicao::data($abreEm) }}, e avisaremos por e-mail.</p>
        </x-drm.aviso-inline>
    @else
        <h1 x-ref="titulo" tabindex="-1" dusk="screen-checkout-titulo"
            class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4">
            Renovar plano {{ $plano?->nome }}
        </h1>

        <x-drm.aviso-inline variant="info" dusk="screen-meuplano-aviso-renovacao">
            <p class="m-0">Seu plano anual termina em {{ \App\Support\DataExibicao::data($fim) }}. Ele não renova sozinho.</p>
        </x-drm.aviso-inline>

        <div class="mt-4 flex flex-col gap-6 lg:grid lg:grid-cols-[3fr_2fr] lg:items-start lg:gap-8">
            <section aria-labelledby="resumo-titulo" class="lg:order-2 lg:sticky lg:top-4">
                <x-card dusk="screen-checkout-resumo">
                    <h2 id="resumo-titulo" class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0 mb-3">Resumo do pedido</h2>
                    <dl class="m-0 grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 text-sm">
                        <dt class="text-[color:var(--color-secondary)]">Plano</dt>
                        <dd class="m-0 text-right" dusk="screen-checkout-resumo-plano">{{ $plano?->nome }} · anual</dd>
                        <dt class="text-[color:var(--color-secondary)]">Preço</dt>
                        <dd class="m-0 text-right" dusk="screen-checkout-resumo-preco">{{ $reais($cheio) }}</dd>
                        <dt class="text-[color:var(--color-secondary)]">Desconto</dt>
                        <dd class="m-0 text-right" dusk="screen-checkout-resumo-desconto">{{ $descontoValor > 0 ? '− '.$reais($descontoValor).($aplicado ? ' ('.$aplicado->cupom->codigo.')' : '') : 'Sem desconto' }}</dd>
                        <dt class="font-medium text-[color:var(--color-primary)] border-t border-[color:var(--color-border)] pt-2">A pagar hoje</dt>
                        <dd class="m-0 text-right font-medium text-[color:var(--color-primary)] border-t border-[color:var(--color-border)] pt-2" dusk="screen-checkout-resumo-a-pagar">{{ $reais($valor) }}</dd>
                        <dt class="text-[color:var(--color-secondary)]">Ciclo</dt>
                        <dd class="m-0 text-right" dusk="screen-checkout-resumo-ciclo">Anual, não renova sozinho — avisamos 7 dias antes</dd>
                        <dt class="text-[color:var(--color-secondary)]">Novo ciclo</dt>
                        <dd class="m-0 text-right" dusk="screen-checkout-resumo-novo-ciclo">O novo ciclo vai até {{ \App\Support\DataExibicao::data($novoFim) }}.</dd>
                    </dl>
                </x-card>
            </section>

            <section class="lg:order-1 flex flex-col gap-4">
                @if ($continua)
                    <x-drm.aviso-inline variant="info" dusk="screen-checkout-desconto-continua">O desconto do seu cupom continua nesta renovação.</x-drm.aviso-inline>
                @else
                    <x-cobranca.cupom :aplicado="$aplicado" :concedido="$cupomConcedido" :aberto="$cupomAberto" :erro="$erroCupom" :esgotou="$cupomEsgotou"/>
                @endif
                <div>
                    <h2 class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0 mb-1">Pagamento</h2>
                    @if ($semCobranca)
                        <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-checkout-sem-pagamento">Você não precisa pagar nada agora.</p>
                    @else
                    <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-checkout-pagamento">Pix · o QR Code vale por {{ $minutosPix }} minutos. A renovação começa quando o ciclo atual termina, sem perder dias.</p>
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
                        <p class="m-0 font-medium text-[color:var(--color-primary)]">Não conseguimos gerar o QR Code agora. Nada foi cobrado.</p>
                        <p class="m-0 text-sm text-[color:var(--color-secondary)]">Tente de novo. Se continuar assim, escreva para eb@ebparcerias.com.</p>
                    </div>
                @endif

                <form wire:submit="renovar" class="flex flex-col gap-3 m-0" novalidate>
                    <label class="flex gap-2.5 items-start text-sm font-normal cursor-pointer min-h-[44px]">
                        <input type="checkbox" wire:model.live="aceito" dusk="screen-checkout-aceite"
                               aria-describedby="@error('aceito')aceite-erro @enderror"
                               class="mt-1 accent-[color:var(--color-tertiary)]">
                        <span class="text-[color:var(--color-primary)]">
                            Li e aceito o
                            <a href="{{ route('termos.termo-adesao') }}" target="_blank" rel="noopener" class="link">Termo de Adesão</a> (v2) e a
                            <a href="{{ route('termos.politica-privacidade') }}" target="_blank" rel="noopener" class="link">Política de Privacidade</a>.
                            @error('aceito')
                                <span id="aceite-erro" class="block mt-1 text-[color:var(--color-destructive)]" dusk="screen-checkout-error-aceite">{{ $message }}</span>
                            @enderror
                        </span>
                    </label>

                    <x-button type="submit" variant="primary" :disabled="$personificando"
                              wire:loading.attr="disabled" wire:target="renovar"
                              dusk="screen-checkout-primary-cta">
                        <span wire:loading.remove wire:target="renovar">{{ $semCobranca ? 'Renovar sem pagar' : 'Pagar renovação · '.$reais($valor) }}</span>
                        <span wire:loading wire:target="renovar">Gerando QR Code…</span>
                    </x-button>
                </form>
            </section>
        </div>
    @endif
</div>
