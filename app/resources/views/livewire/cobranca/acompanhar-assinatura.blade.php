@php
    use App\Domain\Cobranca\EstadoCobranca;

    $reais = fn (int $centavos): string => 'R$ '.number_format($centavos / 100, 2, ',', '.');
    $estado = $invoice->status;
    $paga = $estado === EstadoCobranca::Paga;
    $aguardando = $estado === EstadoCobranca::Pendente && ! $invoice->expirada();
    $fim = $assinatura?->proximo_vencimento->setTimezone('America/Sao_Paulo')->format('d/m/Y');
    $cartao = $assinatura?->cartao_ultimos4 ? ($assinatura->cartao_bandeira ? ucfirst(strtolower($assinatura->cartao_bandeira)).' ' : '').'final '.$assinatura->cartao_ultimos4 : null;
@endphp
{{-- O polling só roda enquanto há o que esperar; confirmado, recusado ou vencido, para. --}}
<div @if ($aguardando || ($paga && ! $assinatura)) wire:poll.3s @endif x-data x-init="$nextTick(() => $refs.titulo?.focus())">
    @if ($paga && $assinatura)
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-confirmacao" role="status">
            @if ($pix)
                <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">{{ $invoice->renovacao ? 'Plano renovado' : 'Plano anual ativo' }}</h1>
                <p class="m-0 text-[color:var(--color-primary)]">
                    Plano {{ $plano?->nome }} · anual, até {{ $fim }}.
                    Esta assinatura não renova sozinha: avisaremos {{ \App\Domain\Cobranca\RenovacaoPix::AVISO_DIAS }} dias antes.
                    Você tem {{ $assinatura->cota_restante_ciclo }} {{ $assinatura->cota_restante_ciclo === 1 ? 'análise disponível' : 'análises disponíveis' }} agora.
                </p>
            @elseif ($invoice->meio === 'nenhum')
                <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Assinatura ativa</h1>
                <p class="m-0 text-[color:var(--color-primary)]" dusk="screen-checkout-sem-cobranca">
                    Plano {{ $plano?->nome }} · {{ $invoice->ciclo }}, ativado sem cobrança com o desconto do cupom.
                    Você tem {{ $assinatura->cota_restante_ciclo }} {{ $assinatura->cota_restante_ciclo === 1 ? 'análise disponível' : 'análises disponíveis' }} agora.
                    O ciclo atual vai até {{ $fim }}. Quando o desconto acabar, cadastre um cartão em Meu plano para continuar.
                </p>
            @else
                <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Assinatura ativa</h1>
                <p class="m-0 text-[color:var(--color-primary)]">
                    Plano {{ $plano?->nome }} · {{ $invoice->ciclo }}.
                    Você tem {{ $assinatura->cota_restante_ciclo }} {{ $assinatura->cota_restante_ciclo === 1 ? 'análise disponível' : 'análises disponíveis' }} agora.
                    A próxima cobrança é em {{ $fim }}, de {{ $reais($assinatura->preco_snapshot_centavos ?? $invoice->valor_centavos) }}{{ $cartao ? ", no cartão {$cartao}" : '' }}.
                </p>
            @endif
            <div class="flex flex-wrap gap-3">
                <x-button :href="route('diagnosticos.selecionar')" variant="primary" dusk="screen-checkout-cta-sucesso">Fazer uma análise</x-button>
                @if ($temMeuPlano)
                    <x-button :href="route('meu-plano')" variant="secondary">Ver meu plano</x-button>
                @endif
            </div>
        </section>
    @elseif ($paga)
        {{-- Pago, mas a assinatura não pôde ser criada (já havia outra): a equipe é avisada por alerta. --}}
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-duplicada" role="alert">
            <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Recebemos o pagamento</h1>
            <p class="m-0 text-[color:var(--color-primary)]">Você já tem uma assinatura ativa, então não abrimos outra. Vamos conferir esta cobrança e devolver o valor, se for o caso. Dúvidas: eb@ebparcerias.com.</p>
        </section>
    @elseif ($estado === EstadoCobranca::Falhou && $invoice->motivo_encerramento !== 'provedor')
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-recusa">
            <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Assinar plano {{ $plano?->nome }}</h1>
            <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4" role="alert">
                <p class="m-0 font-medium text-[color:var(--color-primary)]">Cartão recusado</p>
                <p class="m-0 text-sm text-[color:var(--color-primary)]">O banco não autorizou o pagamento. Tente outro cartão.</p>
            </div>
            <p class="m-0 text-sm text-[color:var(--color-secondary)]">Você continua sem assinatura.</p>
            <div><x-button :href="$urlDeNovo" variant="primary" dusk="screen-checkout-tentar-de-novo">Tentar de novo</x-button></div>
        </section>
    @elseif ($estado === EstadoCobranca::Cancelada || $estado === EstadoCobranca::Falhou)
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-cancelada">
            <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Assinatura não concluída</h1>
            <p class="m-0 text-[color:var(--color-primary)]">Nada foi cobrado.</p>
            <div class="flex flex-wrap gap-3">
                <x-button :href="$urlDeNovo" variant="primary">Tentar de novo</x-button>
                <x-button :href="route('planos')" variant="secondary">Ver os planos</x-button>
            </div>
        </section>
    @elseif ($invoice->expirada())
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-expirado">
            <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">{{ $pix ? 'Pague o plano anual' : 'Assinar plano '.$plano?->nome }}</h1>
            <div class="rounded-md border border-[color:var(--color-tertiary)]/30 bg-[color:var(--color-tertiary)]/5 p-4" role="status">
                @if ($pix)
                    <p class="m-0 font-medium text-[color:var(--color-primary)]">O QR Code expirou e nada foi cobrado.</p>
                    <p class="m-0 text-sm text-[color:var(--color-primary)]">{{ $invoice->renovacao ? 'Seu plano segue valendo até o fim do ciclo.' : 'O plano não foi ativado.' }} Gere outro para pagar.</p>
                @else
                    <p class="m-0 font-medium text-[color:var(--color-primary)]">O pagamento não foi concluído e nada foi cobrado.</p>
                    <p class="m-0 text-sm text-[color:var(--color-primary)]">Você continua sem assinatura. Tente de novo quando quiser.</p>
                @endif
            </div>
            <div><x-button :href="$urlDeNovo" variant="primary" dusk="screen-checkout-tentar-de-novo">{{ $pix ? 'Gerar novo QR Code' : 'Tentar de novo' }}</x-button></div>
        </section>
    @elseif ($pix)
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-aguardando">
            <div>
                <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Pague o plano anual</h1>
                <p class="m-0 text-sm text-[color:var(--color-secondary)]">{{ $invoice->descricao }} · {{ $reais($invoice->valor_centavos) }}</p>
            </div>
            <p class="m-0 text-[color:var(--color-primary)]">
                O pagamento é feito na página segura do provedor. Abra o QR Code, escaneie ou copie o código e cole no app do seu banco.
                {{ $invoice->renovacao ? 'A renovação vale quando o banco confirmar o pagamento.' : 'O plano começa quando o banco confirmar o pagamento.' }}
            </p>
            <div><x-button :href="$invoice->checkout_url" variant="primary" dusk="screen-checkout-abrir-pagamento">Abrir o QR Code Pix</x-button></div>
            <p class="m-0 text-sm text-[color:var(--color-secondary)]" role="timer" dusk="screen-checkout-pix-expira">
                Este código vale até as {{ $invoice->checkout_expira_em?->setTimezone('America/Sao_Paulo')->format('H:i') }}.
            </p>
            <div class="rounded-md border border-[color:var(--color-tertiary)]/30 bg-[color:var(--color-tertiary)]/5 p-4" role="status" dusk="screen-checkout-aguardando-aviso">
                <p class="m-0 font-medium text-[color:var(--color-primary)]">Aguardando o pagamento…</p>
                <p class="m-0 text-sm text-[color:var(--color-primary)]">Esta tela se atualiza sozinha quando o banco confirmar. Não pague de novo.</p>
            </div>
        </section>
    @else
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-aguardando">
            <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Estamos confirmando seu pagamento</h1>
            <div class="rounded-md border border-[color:var(--color-tertiary)]/30 bg-[color:var(--color-tertiary)]/5 p-4" role="status" dusk="screen-checkout-aguardando-aviso">
                <p class="m-0 font-medium text-[color:var(--color-primary)]">Aguardando a confirmação do processador…</p>
                <p class="m-0 text-sm text-[color:var(--color-primary)]">{{ $invoice->descricao }} · {{ $reais($invoice->valor_centavos) }}. Esta tela se atualiza sozinha. Não pague de novo.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <x-button :href="$invoice->checkout_url" variant="secondary" dusk="screen-checkout-abrir-pagamento">Voltar à página de pagamento</x-button>
            </div>
        </section>
    @endif
</div>
