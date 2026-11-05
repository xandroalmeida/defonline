<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
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
 * STORY-077 CA-1, CA-5, CA-7, CA-9 — da revisão do plano até o checkout recorrente hospedado (cartão) ou
 * o checkout Pix avulso do plano anual (STORY-080).
 *
 * Mesma ordem da compra de créditos: valida, registra o aceite, grava a Invoice ANTES de falar com
 * o provedor (o id dela é a chave de idempotência) e só então abre o checkout. A Invoice guarda o
 * retrato do plano (preço e cota) no momento da escolha: mudar o catálogo depois não a altera
 * (CA-5). A assinatura só nasce quando o provedor confirma o pagamento (ConfirmarAssinatura).
 */
final class IniciarAssinatura
{
    public function __construct(
        private readonly PaymentProvider $provedor,
        private readonly RegistrarAceiteDoCheckout $aceite,
        private readonly AbrirCheckoutDaInvoice $checkout,
    ) {}

    /** O plano e o ciclo mais recentes do catálogo valem a cada abertura de checkout. */
    public static function precoDe(Plano $plano, CicloCobranca $ciclo): int
    {
        return $ciclo->precoDe($plano->preco_mensal_centavos, $plano->preco_anual_centavos);
    }

    /**
     * Assinatura paga vigente do Usuário (o trial não conta: assinar o encerra). Inclui a renovação
     * cancelada enquanto o ciclo pago não acabou (STORY-081): o acesso ainda é dela, e assinar de novo
     * seria pagar duas vezes pelo mesmo período. Para voltar a cobrar, o caminho é reativar.
     */
    public static function assinaturaVigente(string $usuarioId): ?Assinatura
    {
        return Assinatura::query()
            ->where('usuario_id', $usuarioId)
            ->where('trial', false)
            ->where(fn ($q) => $q
                ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::EmAtraso->value])
                ->orWhere(fn ($c) => $c->where('status', StatusAssinatura::Cancelada->value)->where('proximo_vencimento', '>', DB::raw('now()'))))
            ->first();
    }

    /**
     * @throws CompraNaoPermitida
     * @throws ProvedorIndisponivel
     */
    public function executar(Usuario $usuario, Plano $plano, CicloCobranca $ciclo, ?string $ip, ?string $userAgent, MeioPagamento $meio = MeioPagamento::Cartao): Invoice
    {
        if (Personificacao::ativa() !== null) {
            throw CompraNaoPermitida::adminPersonificando();
        }

        if (! $usuario->emailConfirmado()) {
            throw CompraNaoPermitida::emailNaoConfirmado();
        }

        if (! $plano->ativo) {
            throw CompraNaoPermitida::planoIndisponivel();
        }

        if (self::assinaturaVigente($usuario->id) !== null) {
            throw CompraNaoPermitida::jaAssinante();
        }

        // STORY-080: o Pix existe só no ciclo anual (não há débito automático; o mensal fica no cartão).
        if ($meio === MeioPagamento::Pix && $ciclo !== CicloCobranca::Anual) {
            throw CompraNaoPermitida::pixSoNoAnual();
        }

        $valor = self::precoDe($plano, $ciclo);
        $aceiteId = $this->aceite->executar($usuario, $ip, $userAgent);

        // Clique repetido ou volta ao checkout: reaproveita o que ainda vale em vez de abrir outro.
        $existente = $this->checkoutAindaValido($usuario, $plano, $ciclo, $valor, $meio);

        if ($existente !== null) {
            return $existente;
        }

        $invoice = Invoice::query()->create([
            'usuario_id' => $usuario->id,
            'tipo' => 'assinatura',
            'plano_id' => $plano->id,
            'ciclo' => $ciclo->value,
            'cota_snapshot' => $plano->cota_mensal,
            'descricao' => "Plano {$plano->nome} · {$ciclo->value}",
            'valor_centavos' => $valor,
            'status' => EstadoCobranca::Pendente,
            'meio' => $meio->value,
            'provedor' => $this->provedor->nome(),
            'aceite_termo_id' => $aceiteId,
        ]);

        $pix = $meio === MeioPagamento::Pix;

        // Pix anual = Checkout Pix avulso, sem assinatura no provedor (ADR-010, ajuste da STORY-080).
        $this->checkout->executar($invoice, new PedidoCheckout(
            referenciaInterna: $invoice->id,
            descricao: $invoice->descricao,
            valorCentavos: $valor,
            meios: [$meio],
            expiraEmMinutos: $pix
                ? (int) config('services.payments.pix_expiracao_minutos', 30)
                : (int) config('services.payments.cartao_expiracao_minutos', 60),
            urlSucesso: route('assinatura.retorno', $invoice),
            urlCancelamento: route('checkout.assinatura', ['plano' => $plano->id, 'ciclo' => $ciclo->value] + ($pix ? ['meio' => 'pix'] : [])),
            urlExpirado: route('assinatura.retorno', $invoice),
            recorrencia: $pix ? null : $ciclo,
        ), 'iniciar_checkout_assinatura');

        EventosCobranca::checkoutIniciado($invoice);

        AuditLogger::log(
            action: 'cobranca.checkout_assinatura_criado',
            subjectType: 'Invoice',
            subjectId: $invoice->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
            after: ['valor_centavos' => $valor, 'plano_id' => $plano->id, 'ciclo' => $ciclo->value, 'provedor' => $invoice->provedor, 'meio' => $meio->value],
        );

        return $invoice;
    }

    private function checkoutAindaValido(Usuario $usuario, Plano $plano, CicloCobranca $ciclo, int $valor, MeioPagamento $meio): ?Invoice
    {
        return Invoice::query()
            ->where('usuario_id', $usuario->id)
            ->where('tipo', 'assinatura')
            ->where('renovacao', false)
            ->where('meio', $meio->value)
            ->where('plano_id', $plano->id)
            ->where('ciclo', $ciclo->value)
            ->where('valor_centavos', $valor)
            ->where('cota_snapshot', $plano->cota_mensal)
            ->where('provedor', $this->provedor->nome())
            ->where('status', EstadoCobranca::Pendente->value)
            ->whereNotNull('checkout_url')
            ->where('checkout_expira_em', '>', DB::raw('now()'))
            ->latest('created_at')
            ->first();
    }
}
