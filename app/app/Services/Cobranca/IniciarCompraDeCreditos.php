<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Jobs\EnviarEmailPagamento;
use App\Models\Invoice;
use App\Models\PacoteCredito;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Observabilidade\EventosCobranca;
use App\Support\Personificacao;
use Illuminate\Support\Facades\DB;

/**
 * STORY-076 CA-1, CA-2, CA-6, CA-7 — da revisão do pedido até o checkout hospedado. STORY-078: o meio é
 * Pix ou cartão (checkout avulso `DETACHED`, sem recorrência nos dois).
 *
 * Ordem: valida quem pode comprar, registra o aceite (data, IP, hash), grava a Invoice ANTES de
 * falar com o provedor (o id dela é a chave de idempotência) e só então abre o checkout. Falha do
 * provedor deixa a Invoice `falhou`, registrada em log de erro para alerta, e nunca credita.
 */
final class IniciarCompraDeCreditos
{
    public function __construct(
        private readonly PaymentProvider $provedor,
        private readonly RegistrarAceiteDoCheckout $aceite,
        private readonly AbrirCheckoutDaInvoice $checkout,
        private readonly CupomDoCheckout $cupons,
        private readonly Carteira $carteira,
    ) {}

    /**
     * @param  string|null  $codigoCupom  cupom digitado (STORY-083); o concedido à conta vale sem código
     *
     * @throws CompraNaoPermitida
     * @throws CupomRecusado
     * @throws ProvedorIndisponivel
     */
    public function executar(Usuario $usuario, PacoteCredito $pacote, ?string $ip, ?string $userAgent, MeioPagamento $meio = MeioPagamento::Pix, ?string $codigoCupom = null): Invoice
    {
        if (Personificacao::ativa() !== null) {
            throw CompraNaoPermitida::adminPersonificando();
        }

        if (! $usuario->emailConfirmado()) {
            throw CompraNaoPermitida::emailNaoConfirmado();
        }

        if (! $pacote->ativo) {
            throw CompraNaoPermitida::pacoteIndisponivel();
        }

        $item = new ItemDeCompra($pacote->preco_centavos, pacoteId: $pacote->id);
        $aplicado = $this->cupons->para($usuario, $codigoCupom, $item);
        $valor = $aplicado?->preco->final ?? $pacote->preco_centavos;
        $aceiteId = $this->aceite->executar($usuario, $ip, $userAgent);

        // Clique repetido ou volta ao checkout: reaproveita o que ainda vale em vez de abrir outro.
        $existente = $this->checkoutAindaValido($usuario, $pacote, $meio, $valor, $aplicado?->cupom->id);

        if ($existente !== null) {
            return $existente;
        }

        // A Invoice pendente com cupom segura a vaga do limite (CA-4): criá-la sob o lock do cupom.
        $invoice = DB::transaction(function () use ($usuario, $pacote, $meio, $aceiteId, $item, $aplicado): Invoice {
            $aplicado = $aplicado === null ? null : $this->cupons->reavaliarTravado($usuario, $aplicado, $item);
            $semCobranca = $aplicado?->preco->semCobranca() === true;

            $invoice = Invoice::query()->create([
                'usuario_id' => $usuario->id,
                'tipo' => 'credito',
                'pacote_credito_id' => $pacote->id,
                'descricao' => "Pacote {$pacote->nome} · {$pacote->quantidadePorExtenso()}",
                'quantidade_creditos' => $pacote->quantidade_creditos,
                'validade_meses' => $pacote->validade_meses,
                'valor_centavos' => $aplicado?->preco->final ?? $pacote->preco_centavos,
                'status' => $semCobranca ? EstadoCobranca::Paga : EstadoCobranca::Pendente,
                'meio' => $semCobranca ? 'nenhum' : $meio->value,
                'provedor' => $semCobranca ? 'interno' : $this->provedor->nome(),
                'aceite_termo_id' => $aceiteId,
                'pago_em' => $semCobranca ? now() : null,
            ] + $this->cupons->camposDaInvoice($aplicado, $pacote->preco_centavos));

            if ($semCobranca) {
                // CA-6: sem cobrança e sem provedor, mas nunca crédito sem Invoice e sem o registro do uso.
                $this->cupons->registrarUso($invoice);
                $this->carteira->concederCreditos($usuario->id, $pacote->quantidade_creditos, now()->addMonthsNoOverflow($pacote->validade_meses), $invoice->id, now());
                EventosCobranca::contratacaoConcluida($invoice, null, renovacao: false);
            }

            return $invoice;
        });

        if ($invoice->status === EstadoCobranca::Paga) {
            EventosCobranca::checkoutIniciado($invoice);

            AuditLogger::log(
                action: 'cobranca.compra_sem_cobranca',
                subjectType: 'Invoice',
                subjectId: $invoice->id,
                actorType: 'user',
                actorId: $usuario->id,
                usuarioId: $usuario->id,
                after: ['pacote_credito_id' => $pacote->id, 'desconto_centavos' => $invoice->desconto_centavos, 'cupom_id' => $invoice->cupom_id],
            );

            return $invoice;
        }

        $this->checkout->executar($invoice, new PedidoCheckout(
            referenciaInterna: $invoice->id,
            descricao: $invoice->descricao,
            valorCentavos: $invoice->valor_centavos,
            meios: [$meio],
            expiraEmMinutos: $meio === MeioPagamento::Pix
                ? (int) config('services.payments.pix_expiracao_minutos', 30)
                : (int) config('services.payments.cartao_expiracao_minutos', 60),
            urlSucesso: route('creditos.retorno', $invoice),
            urlCancelamento: route('creditos.comprar'),
            urlExpirado: route('creditos.retorno', $invoice),
        ), 'iniciar_checkout');

        EventosCobranca::checkoutIniciado($invoice);

        AuditLogger::log(
            action: 'cobranca.checkout_criado',
            subjectType: 'Invoice',
            subjectId: $invoice->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
            after: ['valor_centavos' => $invoice->valor_centavos, 'provedor' => $invoice->provedor, 'pacote_credito_id' => $pacote->id, 'meio' => $meio->value, 'desconto_centavos' => $invoice->desconto_centavos],
        );

        EnviarEmailPagamento::dispatch($invoice->id);

        return $invoice;
    }

    private function checkoutAindaValido(Usuario $usuario, PacoteCredito $pacote, MeioPagamento $meio, int $valor, ?string $cupomId): ?Invoice
    {
        return Invoice::query()
            ->where('usuario_id', $usuario->id)
            ->where('pacote_credito_id', $pacote->id)
            ->where('valor_centavos', $valor)
            ->when($cupomId === null, fn ($q) => $q->whereNull('cupom_id'), fn ($q) => $q->where('cupom_id', $cupomId))
            ->where('meio', $meio->value)
            ->where('provedor', $this->provedor->nome())
            ->where('status', EstadoCobranca::Pendente->value)
            ->whereNotNull('checkout_url')
            ->where('checkout_expira_em', '>', DB::raw('now()'))
            ->latest('created_at')
            ->first();
    }
}
