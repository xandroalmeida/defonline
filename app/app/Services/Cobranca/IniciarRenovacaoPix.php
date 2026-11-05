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
     * @throws CompraNaoPermitida
     * @throws ProvedorIndisponivel
     */
    public function executar(Usuario $usuario, ?string $ip, ?string $userAgent): Invoice
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

        $plano = Plano::query()->find($assinatura->plano_id);

        if ($plano === null || ! $plano->ativo) {
            throw CompraNaoPermitida::planoIndisponivel();
        }

        $valor = (int) $assinatura->preco_snapshot_centavos;
        $aceiteId = $this->aceite->executar($usuario, $ip, $userAgent);

        // Clique repetido ou volta à tela: reaproveita o QR Code que ainda vale.
        $existente = $this->checkoutAindaValido($assinatura, $valor);

        if ($existente !== null) {
            return $existente;
        }

        $invoice = Invoice::query()->create([
            'usuario_id' => $usuario->id,
            'tipo' => 'assinatura',
            'plano_id' => $plano->id,
            'ciclo' => CicloCobranca::Anual->value,
            'cota_snapshot' => $plano->cota_mensal,
            'descricao' => "Plano {$plano->nome} · renovação anual",
            'valor_centavos' => $valor,
            'status' => EstadoCobranca::Pendente,
            'meio' => MeioPagamento::Pix->value,
            'provedor' => $this->provedor->nome(),
            'assinatura_id' => $assinatura->id,
            'renovacao' => true,
            'aceite_termo_id' => $aceiteId,
        ]);

        $this->checkout->executar($invoice, new PedidoCheckout(
            referenciaInterna: $invoice->id,
            descricao: $invoice->descricao,
            valorCentavos: $valor,
            meios: [MeioPagamento::Pix],
            expiraEmMinutos: (int) config('services.payments.pix_expiracao_minutos', 30),
            urlSucesso: route('assinatura.retorno', $invoice),
            urlCancelamento: route('assinatura.renovar'),
            urlExpirado: route('assinatura.retorno', $invoice),
        ), 'iniciar_renovacao_pix');

        AuditLogger::log(
            action: 'cobranca.checkout_renovacao_criado',
            subjectType: 'Invoice',
            subjectId: $invoice->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
            after: ['valor_centavos' => $valor, 'assinatura_id' => $assinatura->id, 'provedor' => $invoice->provedor],
        );

        return $invoice;
    }

    private function checkoutAindaValido(Assinatura $assinatura, int $valor): ?Invoice
    {
        return Invoice::query()
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
