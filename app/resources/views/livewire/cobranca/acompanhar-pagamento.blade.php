@php
    $reais = 'R$ '.number_format($invoice->valor_centavos / 100, 2, ',', '.');
    $estado = $invoice->status;
    $paga = $estado === \App\Domain\Cobranca\EstadoCobranca::Paga;
    $pendente = $estado === \App\Domain\Cobranca\EstadoCobranca::Pendente && ! $invoice->expirada();
@endphp
{{-- O polling só roda enquanto há o que esperar; confirmado, expirado ou cancelado, para. --}}
<div @if ($pendente) wire:poll.3s @endif x-data x-init="$nextTick(() => $refs.titulo?.focus())">
    @if ($paga)
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-confirmacao" role="status">
            <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Pagamento confirmado</h1>
            <p class="m-0 text-[color:var(--color-primary)]">
                Recebemos o Pix de {{ $reais }}.
                @if ($lote)
                    Você tem {{ $lote->quantidade_comprada }} créditos, válidos até {{ $lote->data_expiracao->setTimezone('America/Sao_Paulo')->format('d/m/Y') }}.
                @endif
                Enviaremos a nota fiscal por e-mail.
            </p>
            <div class="flex flex-wrap gap-3">
                @if ($rascunho)
                    <x-button :href="route('diagnosticos.novo', $rascunho->empresa_analisada_id)" variant="primary" dusk="screen-checkout-cta-sucesso">Continuar o diagnóstico</x-button>
                @else
                    <x-button :href="route('diagnosticos.selecionar')" variant="primary" dusk="screen-checkout-cta-sucesso">Fazer uma análise</x-button>
                @endif
                @if ($temMeuPlano)
                    <x-button :href="route('meu-plano')" variant="secondary">Ver meu plano</x-button>
                @endif
            </div>
        </section>
    @elseif ($estado === \App\Domain\Cobranca\EstadoCobranca::Cancelada)
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-cancelada">
            <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Compra cancelada</h1>
            <p class="m-0 text-[color:var(--color-primary)]">Nada foi cobrado.</p>
            <div><x-button :href="route('creditos.comprar')" variant="secondary">Ver os pacotes</x-button></div>
        </section>
    @elseif ($estado === \App\Domain\Cobranca\EstadoCobranca::Falhou)
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-falhou">
            <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Pague com Pix</h1>
            <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4" role="alert">
                <p class="m-0 text-[color:var(--color-primary)]">Não conseguimos gerar o QR Code agora. Nada foi cobrado.</p>
            </div>
            <div><x-button type="button" variant="primary" wire:click="novoCodigo">Tentar de novo</x-button></div>
        </section>
    @elseif ($invoice->expirada())
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-expirado">
            <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Pague com Pix</h1>
            <div class="rounded-md border border-[color:var(--color-tertiary)]/30 bg-[color:var(--color-tertiary)]/5 p-4" role="status">
                <p class="m-0 font-medium text-[color:var(--color-primary)]">O QR Code expirou e nada foi cobrado.</p>
                <p class="m-0 text-sm text-[color:var(--color-primary)]">Gere outro para pagar.</p>
            </div>
            <div>
                <x-button type="button" variant="primary" wire:click="novoCodigo" dusk="screen-checkout-pix-novo">Gerar novo QR Code</x-button>
            </div>
        </section>
    @else
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-checkout-aguardando">
            <div>
                <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Pague com Pix</h1>
                <p class="m-0 text-sm text-[color:var(--color-secondary)]">{{ $invoice->descricao }} · {{ $reais }}</p>
            </div>

            <p class="m-0 text-[color:var(--color-primary)]">
                O pagamento é feito na página segura do provedor. Abra o QR Code, escaneie ou copie o código e cole no app do seu banco.
            </p>
            <div>
                <x-button :href="$invoice->checkout_url" variant="primary" dusk="screen-checkout-abrir-pagamento">Abrir o QR Code Pix</x-button>
            </div>

            <p class="m-0 text-sm text-[color:var(--color-secondary)]" role="timer" dusk="screen-checkout-pix-expira">
                Este código vale por {{ (int) config('services.payments.pix_expiracao_minutos', 30) }} minutos.
                Expira às {{ $invoice->checkout_expira_em?->setTimezone('America/Sao_Paulo')->format('H:i') }} ·
                <span aria-live="polite" aria-atomic="true">restam {{ $minutosRestantes }} min</span>
            </p>

            <div class="rounded-md border border-[color:var(--color-tertiary)]/30 bg-[color:var(--color-tertiary)]/5 p-4" role="status" dusk="screen-checkout-aguardando-aviso">
                <p class="m-0 font-medium text-[color:var(--color-primary)]">Aguardando o pagamento…</p>
                <p class="m-0 text-sm text-[color:var(--color-primary)]">Esta tela se atualiza sozinha quando o banco confirmar. Também enviamos o link do QR Code para {{ $email }}.</p>
            </div>

            @if ($cancelamentoFalhou)
                <p class="m-0 text-sm text-[color:var(--color-destructive)]" role="alert">Não conseguimos cancelar agora. Se você não pagar, o código expira sozinho.</p>
            @endif

            <div>
                <x-button type="button" variant="secondary" wire:click="cancelar" :disabled="$personificando" dusk="screen-checkout-cancelar">Cancelar compra</x-button>
            </div>
        </section>
    @endif
</div>
