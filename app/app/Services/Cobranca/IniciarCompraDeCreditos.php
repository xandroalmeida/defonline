<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

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
use App\Support\Personificacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * STORY-076 CA-1, CA-2, CA-6, CA-7 — da revisão do pedido até o checkout hospedado.
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
    ) {}

    /**
     * @throws CompraNaoPermitida
     * @throws ProvedorIndisponivel
     */
    public function executar(Usuario $usuario, PacoteCredito $pacote, ?string $ip, ?string $userAgent): Invoice
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

        $aceiteId = $this->aceite->executar($usuario, $ip, $userAgent);

        // Clique repetido ou volta ao checkout: reaproveita o que ainda vale em vez de abrir outro.
        $existente = $this->checkoutAindaValido($usuario, $pacote);

        if ($existente !== null) {
            return $existente;
        }

        $invoice = Invoice::query()->create([
            'usuario_id' => $usuario->id,
            'tipo' => 'credito',
            'pacote_credito_id' => $pacote->id,
            'descricao' => "Pacote {$pacote->nome} · {$pacote->quantidade_creditos} análises",
            'quantidade_creditos' => $pacote->quantidade_creditos,
            'validade_meses' => $pacote->validade_meses,
            'valor_centavos' => $pacote->preco_centavos,
            'status' => EstadoCobranca::Pendente,
            'meio' => MeioPagamento::Pix->value,
            'provedor' => $this->provedor->nome(),
            'aceite_termo_id' => $aceiteId,
        ]);

        try {
            $sessao = $this->provedor->startCheckout(new PedidoCheckout(
                referenciaInterna: $invoice->id,
                descricao: $invoice->descricao,
                valorCentavos: $invoice->valor_centavos,
                meios: [MeioPagamento::Pix],
                expiraEmMinutos: (int) config('services.payments.pix_expiracao_minutos', 30),
                urlSucesso: route('creditos.retorno', $invoice),
                urlCancelamento: route('creditos.comprar'),
                urlExpirado: route('creditos.retorno', $invoice),
            ));
        } catch (ProvedorIndisponivel $e) {
            $invoice->forceFill([
                'status' => EstadoCobranca::Falhou,
                'falha_motivo' => mb_substr($e->getMessage(), 0, 200),
            ])->save();

            // Alvo do alerta operacional: nível error, módulo e ação estáveis. Sem corpo nem chave.
            Log::error('cobranca.provedor_falhou', [
                'module' => 'cobranca',
                'action' => 'iniciar_checkout',
                'invoice_id' => $invoice->id,
                'provedor' => $this->provedor->nome(),
                'motivo' => $e->getMessage(),
            ]);

            throw $e;
        }

        $invoice->forceFill([
            'checkout_id_externo' => $sessao->referencia->id,
            'checkout_url' => $sessao->url,
            'checkout_expira_em' => $sessao->expiraEm,
        ])->save();

        AuditLogger::log(
            action: 'cobranca.checkout_criado',
            subjectType: 'Invoice',
            subjectId: $invoice->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
            after: ['valor_centavos' => $invoice->valor_centavos, 'provedor' => $invoice->provedor, 'pacote_credito_id' => $pacote->id],
        );

        EnviarEmailPagamento::dispatch($invoice->id);

        return $invoice;
    }

    private function checkoutAindaValido(Usuario $usuario, PacoteCredito $pacote): ?Invoice
    {
        return Invoice::query()
            ->where('usuario_id', $usuario->id)
            ->where('pacote_credito_id', $pacote->id)
            ->where('valor_centavos', $pacote->preco_centavos)
            ->where('provedor', $this->provedor->nome())
            ->where('status', EstadoCobranca::Pendente->value)
            ->whereNotNull('checkout_url')
            ->where('checkout_expira_em', '>', DB::raw('now()'))
            ->latest('created_at')
            ->first();
    }
}
