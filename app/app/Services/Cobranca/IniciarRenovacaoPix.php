<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\RenovacaoPix;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\Plano;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Observabilidade\EventosCobranca;
use App\Support\Personificacao;
use Illuminate\Support\Facades\DB;

/**
 * STORY-080 CA-4 — o novo Checkout Pix da renovação do plano anual. Só para quem tem plano anual ativo
 * pago com Pix e só nos últimos 7 dias do ciclo. O preço é o da última contratação (o aviso de 30 dias
 * para mudança de preço é da STORY-079); a cota é a do catálogo de hoje. O pagamento estende o plano a
 * partir do fim do ciclo atual ({@see ConfirmarAssinatura}).
 */
final class IniciarRenovacaoPix
{
    public function __construct(
        private readonly PaymentProvider $provedor,
        private readonly RegistrarAceiteDoCheckout $aceite,
        private readonly AbrirCheckoutDaInvoice $checkout,
        private readonly CupomDoCheckout $cupons,
        private readonly ConfirmarAssinatura $confirmar,
    ) {}

    /** O plano anual Pix mais recente do Usuário (vigente ou já encerrado), para a tela de renovação. */
    public static function assinaturaDoUsuario(string $usuarioId): ?Assinatura
    {
        return Assinatura::query()
            ->where('usuario_id', $usuarioId)
            ->where('trial', false)
            ->where('meio_pagamento', 'pix')
            ->where('ciclo', CicloCobranca::Anual->value)
            ->orderByDesc('data_inicio')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Cupom na renovação (STORY-083): se a assinatura já tem desconto para este ciclo, ele continua e nenhum
     * outro se soma (cupons não acumulam); senão vale o concedido à conta ou o código digitado.
     *
     * @throws CompraNaoPermitida
     * @throws CupomRecusado
     * @throws ProvedorIndisponivel
     */
    public function executar(Usuario $usuario, ?string $ip, ?string $userAgent, ?string $codigoCupom = null): Invoice
    {
        if (Personificacao::ativa() !== null) {
            throw CompraNaoPermitida::adminPersonificando();
        }

        if (! $usuario->emailConfirmado()) {
            throw CompraNaoPermitida::emailNaoConfirmado();
        }

        $assinatura = self::assinaturaDoUsuario($usuario->id);

        if ($assinatura === null || $assinatura->status !== StatusAssinatura::Ativa || $assinatura->plano_id === null) {
            throw CompraNaoPermitida::semAssinaturaPix();
        }

        if (! RenovacaoPix::janelaAberta($assinatura->proximo_vencimento, now())) {
            throw CompraNaoPermitida::foraDaJanelaDeRenovacao();
        }

        // STORY-082: o downgrade agendado vale nesta renovação: paga-se o plano novo, pelo preço escolhido.
        $plano = Plano::query()->find($assinatura->troca_plano_id ?? $assinatura->plano_id);

        if ($plano === null || ($assinatura->troca_plano_id === null && ! $plano->ativo)) {
            throw CompraNaoPermitida::planoIndisponivel();
        }

        $periodo = $assinatura->periodos_pagos + 1;
        $emCurso = $assinatura->descontoDaProximaCobranca();
        $continua = $emCurso?->vale($periodo) === true;
        $cheio = $continua ? $emCurso->cheio : $assinatura->precoCheioDaProximaCobranca();
        $item = new ItemDeCompra($cheio, planoId: $plano->id, ciclo: CicloCobranca::Anual->value);
        $aplicado = $continua ? null : $this->cupons->para($usuario, $codigoCupom, $item);
        $valor = $continua ? $emCurso->valorDoPeriodo($periodo) : ($aplicado?->preco->final ?? $cheio);
        $aceiteId = $this->aceite->executar($usuario, $ip, $userAgent);

        // Clique repetido ou volta à tela: reaproveita o QR Code que ainda vale.
        $existente = $this->checkoutAindaValido($assinatura, $valor, $aplicado?->cupom->id);

        if ($existente !== null) {
            return $existente;
        }

        $invoice = DB::transaction(function () use ($usuario, $plano, $assinatura, $aceiteId, $item, $aplicado, $continua, $emCurso, $valor, $cheio): Invoice {
            $aplicado = $aplicado === null ? null : $this->cupons->reavaliarTravado($usuario, $aplicado, $item);
            $final = $aplicado?->preco->final ?? $valor;
            $semCobranca = $final === 0;

            $invoice = Invoice::query()->create([
                'usuario_id' => $usuario->id,
                'tipo' => 'assinatura',
                'plano_id' => $plano->id,
                'ciclo' => CicloCobranca::Anual->value,
                'cota_snapshot' => $plano->cota_mensal,
                'descricao' => "Plano {$plano->nome} · renovação anual",
                'valor_centavos' => $final,
                'status' => EstadoCobranca::Pendente,
                'meio' => $semCobranca ? 'nenhum' : MeioPagamento::Pix->value,
                'provedor' => $semCobranca ? 'interno' : $this->provedor->nome(),
                'assinatura_id' => $assinatura->id,
                'renovacao' => true,
                'aceite_termo_id' => $aceiteId,
            ] + ($continua
                ? ['preco_cheio_centavos' => $emCurso->cheio, 'desconto_centavos' => $emCurso->desconto]
                : $this->cupons->camposDaInvoice($aplicado, $cheio)));

            if ($semCobranca) {
                $this->confirmar->renovarAnualSemCobranca($invoice);
            }

            return $invoice->refresh();
        });

        if ($invoice->status === EstadoCobranca::Paga) {
            EventosCobranca::checkoutIniciado($invoice);

            return $invoice;
        }

        $this->checkout->executar($invoice, new PedidoCheckout(
            referenciaInterna: $invoice->id,
            descricao: $invoice->descricao,
            valorCentavos: $invoice->valor_centavos,
            meios: [MeioPagamento::Pix],
            expiraEmMinutos: (int) config('services.payments.pix_expiracao_minutos', 30),
            urlSucesso: route('assinatura.retorno', $invoice),
            urlCancelamento: route('assinatura.renovar'),
            urlExpirado: route('assinatura.retorno', $invoice),
        ), 'iniciar_renovacao_pix');

        EventosCobranca::checkoutIniciado($invoice);

        AuditLogger::log(
            action: 'cobranca.checkout_renovacao_criado',
            subjectType: 'Invoice',
            subjectId: $invoice->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
            after: ['valor_centavos' => $invoice->valor_centavos, 'desconto_centavos' => $invoice->desconto_centavos, 'assinatura_id' => $assinatura->id, 'provedor' => $invoice->provedor],
        );

        return $invoice;
    }

    private function checkoutAindaValido(Assinatura $assinatura, int $valor, ?string $cupomId): ?Invoice
    {
        return Invoice::query()
            ->when($cupomId === null, fn ($q) => $q->whereNull('cupom_id'), fn ($q) => $q->where('cupom_id', $cupomId))
            ->where('assinatura_id', $assinatura->id)
            ->where('renovacao', true)
            ->where('meio', MeioPagamento::Pix->value)
            ->where('valor_centavos', $valor)
            ->where('status', EstadoCobranca::Pendente->value)
            ->whereNotNull('checkout_url')
            ->where('checkout_expira_em', '>', DB::raw('now()'))
            ->latest('created_at')
            ->first();
    }
}
