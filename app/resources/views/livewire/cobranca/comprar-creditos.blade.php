@php
    $reais = fn (int $centavos): string => 'R$ '.number_format($centavos / 100, 2, ',', '.');
@endphp
<div x-data x-init="$nextTick(() => $refs.titulo?.focus())">
    <x-breadcrumb :items="[['label' => 'Início', 'url' => route('home')], ['label' => 'Comprar créditos']]"/>

    <h1 x-ref="titulo" tabindex="-1" dusk="screen-checkout-titulo"
        class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-1">
        Comprar créditos
    </h1>

    @if ($pacoteId === null)
        {{-- Sem pacote escolhido: lista simples, até a página de planos (STORY-075) ser a entrada. --}}
        <p class="text-[color:var(--color-secondary)] text-sm mt-0 mb-6">Escolha o pacote de análises.</p>
        <ul class="grid grid-cols-1 md:grid-cols-3 gap-4 list-none p-0 m-0" dusk="screen-checkout-pacotes">
            @forelse ($pacotes as $p)
                <li>
                    <x-card as="article" class="h-full flex flex-col gap-3" dusk="screen-checkout-pacote-{{ $p->codigo }}">
                        <h2 class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0">Pacote {{ $p->nome }}</h2>
                        <p class="m-0 text-sm text-[color:var(--color-secondary)]">{{ $p->quantidadePorExtenso() }} · {{ $reais($p->preco_centavos) }}</p>
                        <x-button :href="route('creditos.comprar', $p->id)" variant="primary" class="mt-auto"
                                  dusk="screen-checkout-comprar-{{ $p->codigo }}">Comprar</x-button>
                    </x-card>
                </li>
            @empty
                <li class="text-[color:var(--color-secondary)]">Nenhum pacote disponível no momento.</li>
            @endforelse
        </ul>
    @elseif ($pacote === null)
        <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4 max-w-[640px] flex flex-col gap-3"
             role="alert" dusk="screen-checkout-indisponivel">
            <p class="m-0 text-[color:var(--color-primary)]">Este pacote não está mais disponível.</p>
            <div><x-button :href="route('creditos.comprar')" variant="secondary">Ver os pacotes disponíveis</x-button></div>
        </div>
    @else
        <p class="text-[color:var(--color-secondary)] text-sm mt-0 mb-6">Pacote {{ $pacote->nome }} · {{ $pacote->quantidadePorExtenso() }}</p>

        @if (! $emailConfirmado)
            <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4 max-w-[640px] flex flex-col gap-3"
                 role="alert" dusk="screen-checkout-email-nao-confirmado">
                <p class="m-0 font-medium text-[color:var(--color-primary)]">Confirme seu e-mail para comprar.</p>
                <p class="m-0 text-sm text-[color:var(--color-primary)]">Enviamos um link para {{ $email }}. Depois de confirmar, volte aqui.</p>
                @if ($confirmacaoReenviada)
                    <p class="m-0 text-sm text-[color:var(--color-secondary)]" role="status" dusk="screen-checkout-confirmacao-reenviada">Enviamos um novo link. Veja sua caixa de entrada.</p>
                @endif
                <div class="flex flex-wrap gap-3">
                    <x-button type="button" variant="primary" wire:click="reenviarConfirmacao" dusk="screen-checkout-reenviar">Reenviar e-mail de confirmação</x-button>
                    <x-button :href="route('creditos.comprar')" variant="secondary">Voltar aos pacotes</x-button>
                </div>
            </div>
        @else
            <div class="flex flex-col gap-6 lg:grid lg:grid-cols-[3fr_2fr] lg:gap-8">
                {{-- Em mobile o resumo vem antes ("quanto?" antes de "como?"); em desktop fica à direita, fixo. --}}
                <section aria-labelledby="resumo-titulo" class="lg:order-2 lg:self-start lg:sticky lg:top-4">
                    <x-card dusk="screen-checkout-resumo">
                        <h2 id="resumo-titulo" class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0 mb-3">Resumo do pedido</h2>
                        <dl class="m-0 grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-4 gap-y-2 text-sm">
                            <dt class="text-[color:var(--color-secondary)]">Preço</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-preco">{{ $reais($pacote->preco_centavos) }}</dd>
                            <dt class="text-[color:var(--color-secondary)]">Desconto</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-desconto">{{ $aplicado ? '− '.$reais($aplicado->preco->desconto).' ('.$aplicado->cupom->codigo.')' : 'Sem desconto' }}</dd>
                            <dt class="font-medium text-[color:var(--color-primary)] border-t border-[color:var(--color-border)] pt-2">A pagar hoje</dt>
                            <dd class="m-0 -ml-4 pl-4 text-right font-medium text-[color:var(--color-primary)] border-t border-[color:var(--color-border)] pt-2" dusk="screen-checkout-resumo-a-pagar">{{ $reais($aPagar) }}</dd>
                            <dt class="text-[color:var(--color-secondary)]">Ciclo</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-ciclo">Compra única, não renova</dd>
                            <dt class="text-[color:var(--color-secondary)]">Próxima cobrança</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-proxima">Não há</dd>
                            <dt class="text-[color:var(--color-secondary)]">Depois</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-depois">Não há</dd>
                            <dt class="text-[color:var(--color-secondary)]">Validade dos créditos</dt>
                            <dd class="m-0 text-right" dusk="screen-checkout-resumo-validade">até {{ \App\Support\DataExibicao::data($validade) }}</dd>
                        </dl>
                    </x-card>
                </section>

                <section class="lg:order-1 flex flex-col gap-4">
                    <p class="m-0 text-sm text-[color:var(--color-secondary)]" dusk="screen-checkout-aviso-raiox">
                        Os créditos não incluem o Raio X do Negócio. Ele faz parte dos planos e do teste.
                    </p>

                    <div class="flex flex-col gap-3">
                        <x-cobranca.cupom :aplicado="$aplicado" :concedido="$cupomConcedido" :aberto="$cupomAberto" :erro="$erroCupom" :esgotou="$cupomEsgotou"/>
                        <h2 class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0">Pagamento</h2>
                        @if ($semCobranca)
                            <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-checkout-sem-pagamento">Você não precisa pagar nada agora.</p>
                        @else
                        <x-cobranca.seletor-meio :atual="$meio" :opcoes="[
                            ['pix', 'Pix', 'libera quando o banco confirmar'],
                            ['cartao', 'Cartão de crédito', 'libera na hora, sem renovação'],
                        ]"/>
                        @endif
                        @if ($semCobranca)
                        @elseif ($meio === 'cartao')
                            <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-checkout-pagamento">
                                Cartão de crédito, digitado na página segura do processador de pagamento. Cobrança única, sem renovação. Nós guardamos só a bandeira e os 4 últimos dígitos.
                            </p>
                            @if ($pixEmAberto)
                                <x-drm.aviso-inline variant="info" dusk="screen-checkout-pix-em-aberto">
                                    Você tem um QR Code Pix em aberto, válido até {{ \App\Support\DataExibicao::hora($pixEmAberto->checkout_expira_em) }}. Se pagar com cartão, ignore-o.
                                </x-drm.aviso-inline>
                            @endif
                        @else
                            <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="screen-checkout-pagamento">Pix · o QR Code vale por {{ $minutosPix }} minutos.</p>
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
                            <p class="m-0 font-medium text-[color:var(--color-primary)]">{{ $meio === 'cartao' ? 'Não conseguimos abrir o pagamento agora. Nada foi cobrado.' : 'Não conseguimos gerar o QR Code agora. Nada foi cobrado.' }}</p>
                            <p class="m-0 text-sm text-[color:var(--color-secondary)]">Tente de novo. Se continuar assim, escreva para eb@ebparcerias.com.</p>
                        </div>
                    @endif

                    <form wire:submit="gerar" class="flex flex-col gap-3 m-0" novalidate>
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
                                  wire:loading.attr="disabled" wire:target="gerar"
                                  dusk="screen-checkout-primary-cta">
                            <span wire:loading.remove wire:target="gerar">{{ $semCobranca ? 'Ativar sem pagar' : ($meio === 'cartao' ? 'Pagar com cartão' : 'Gerar QR Code Pix').' · '.$reais($aPagar) }}</span>
                            <span wire:loading wire:target="gerar">{{ $meio === 'cartao' ? 'Abrindo o pagamento…' : 'Gerando QR Code…' }}</span>
                        </x-button>
                        <p class="m-0 text-sm text-[color:var(--color-secondary)]">
                            Reembolso em até 7 dias, se você ainda não tiver feito nenhuma análise.
                        </p>
                    </form>

                    <div><x-link :href="route('creditos.comprar')" dusk="screen-checkout-voltar">Voltar aos pacotes</x-link></div>
                </section>
            </div>
        @endif
    @endif
</div>
