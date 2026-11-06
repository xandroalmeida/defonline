@php
    use App\Domain\Cobranca\EstadoCobranca;

    $reais = fn (int $centavos): string => 'R$ '.number_format($centavos / 100, 2, ',', '.');
    $estado = $invoice->status;
    $aguardando = $estado === EstadoCobranca::Pendente && ! $invoice->expirada();
    $h1 = 'text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0';
    $paga = $estado === EstadoCobranca::Paga;
    $pix = $invoice->meio === 'pix';
    // Pago há mais de 2 minutos e a troca não valeu: o ciclo mudou no meio do caminho (alerta para a equipe).
    $semEfeito = $paga && ! $aplicada && $invoice->pago_em !== null && $invoice->pago_em->lt(now()->subMinutes(2));
@endphp
<div @if ($aguardando || ($paga && ! $aplicada && ! $semEfeito)) wire:poll.3s="conferir" @endif x-data x-init="$nextTick(() => $refs.titulo?.focus())">
    @if ($paga && $aplicada)
        <section class="max-w-[640px] flex flex-col gap-4" role="status" dusk="screen-troca-aplicada">
            <h1 x-ref="titulo" tabindex="-1" class="{{ $h1 }}">Plano trocado</h1>
            <p class="m-0 text-[color:var(--color-primary)]">
                @if ($invoice->cota_extra_troca !== null)
                    Você agora está no plano {{ $destino?->nome }}. @if ($invoice->cota_extra_troca > 0)Você ganhou {{ $invoice->cota_extra_troca }} {{ $invoice->cota_extra_troca === 1 ? 'análise' : 'análises' }} a mais neste ciclo. @endif Sua cota é de {{ $assinatura->cota_snapshot }} {{ $assinatura->cota_snapshot === 1 ? 'análise' : 'análises' }} por mês a partir de {{ \App\Support\DataExibicao::data($assinatura->proxima_cota_em ?? $assinatura->proximo_vencimento) }}.
                @else
                    Você agora está no plano {{ $destino?->nome }}. Sua cota é de {{ $assinatura->cota_snapshot }} {{ $assinatura->cota_snapshot === 1 ? 'análise' : 'análises' }} e você já pode usá-las.
                @endif
                Pagamos {{ $reais($invoice->valor_centavos) }}{{ $invoice->cartao_ultimos4 ? ' no cartão final '.$invoice->cartao_ultimos4 : '' }}.
            </p>
            <div class="flex flex-wrap gap-3">
                <x-button :href="route('diagnosticos.selecionar')" variant="primary">Fazer uma análise</x-button>
                <x-button :href="route('meu-plano')" variant="secondary">Ver meu plano</x-button>
            </div>
        </section>
    @elseif ($semEfeito)
        <section class="max-w-[640px] flex flex-col gap-4" role="alert" dusk="screen-troca-sem-efeito">
            <h1 x-ref="titulo" tabindex="-1" class="{{ $h1 }}">Recebemos o pagamento</h1>
            <p class="m-0 text-[color:var(--color-primary)]">Seu ciclo mudou enquanto você pagava, então não aplicamos a troca. Vamos conferir esta cobrança e devolver o valor, se for o caso. Dúvidas: eb@ebparcerias.com.</p>
            <div><x-button :href="route('meu-plano')" variant="secondary">Ver meu plano</x-button></div>
        </section>
    @elseif ($paga)
        <section class="max-w-[640px] flex flex-col gap-4" role="status" dusk="screen-troca-aguardando">
            <h1 x-ref="titulo" tabindex="-1" class="{{ $h1 }}">Estamos concluindo a troca</h1>
            <p class="m-0 text-[color:var(--color-primary)]">Recebemos o pagamento de {{ $reais($invoice->valor_centavos) }}. Esta tela se atualiza sozinha.</p>
        </section>
    @elseif ($estado === EstadoCobranca::Falhou && $invoice->motivo_encerramento !== 'provedor')
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-troca-recusa">
            <h1 x-ref="titulo" tabindex="-1" class="{{ $h1 }}">Trocar para o plano {{ $destino?->nome }}</h1>
            <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4" role="alert">
                <p class="m-0 font-medium text-[color:var(--color-primary)]">{{ $pix ? 'Pagamento não concluído' : 'Cartão recusado' }}</p>
                <p class="m-0 text-sm text-[color:var(--color-primary)]">Seu plano continua o {{ $atual?->nome }}. Nada foi cobrado.</p>
            </div>
            <div><x-button :href="route('plano.trocar', ['plano' => $invoice->plano_id, 'ciclo' => $invoice->ciclo])" variant="primary" dusk="screen-troca-tentar-de-novo">Tentar de novo</x-button></div>
        </section>
    @elseif ($estado === EstadoCobranca::Cancelada || $estado === EstadoCobranca::Falhou || $invoice->expirada())
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-troca-nao-concluida">
            <h1 x-ref="titulo" tabindex="-1" class="{{ $h1 }}">Troca não concluída</h1>
            <p class="m-0 text-[color:var(--color-primary)]">Nada foi cobrado e seu plano continua o {{ $atual?->nome }}.</p>
            <div class="flex flex-wrap gap-3">
                <x-button :href="route('plano.trocar', ['plano' => $invoice->plano_id, 'ciclo' => $invoice->ciclo])" variant="primary" dusk="screen-troca-tentar-de-novo">Tentar de novo</x-button>
                <x-button :href="route('meu-plano')" variant="secondary">Voltar para Meu plano</x-button>
            </div>
        </section>
    @else
        <section class="max-w-[640px] flex flex-col gap-4" dusk="screen-troca-aguardando">
            <h1 x-ref="titulo" tabindex="-1" class="{{ $h1 }}">Estamos confirmando seu pagamento</h1>
            <div class="rounded-md border border-[color:var(--color-tertiary)]/30 bg-[color:var(--color-tertiary)]/5 p-4" role="status">
                <p class="m-0 font-medium text-[color:var(--color-primary)]">Aguardando a confirmação do processador…</p>
                <p class="m-0 text-sm text-[color:var(--color-primary)]">{{ $invoice->descricao }} · {{ $reais($invoice->valor_centavos) }}. Esta tela se atualiza sozinha. Não pague de novo.</p>
            </div>
            <div><x-button :href="$invoice->checkout_url" variant="secondary" dusk="screen-troca-abrir-pagamento">{{ $pix ? 'Abrir o QR Code Pix' : 'Voltar à página de pagamento' }}</x-button></div>
        </section>
    @endif
</div>
