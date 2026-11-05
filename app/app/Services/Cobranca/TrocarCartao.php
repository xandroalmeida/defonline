<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\Plano;
use App\Models\TrocaDeCartao;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Support\Personificacao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * STORY-081 CA-3 e ADR-010 §2/Q6 — trocar o cartão sem ele passar pelo nosso servidor: um NOVO Checkout
 * recorrente no provedor. A assinatura antiga só é cancelada lá DEPOIS de o cartão novo ser confirmado,
 * e o ponteiro (`assinaturas.id_externo`) só muda na confirmação.
 *
 * - **Em dia:** o checkout começa a cobrar no vencimento já pago (`primeiraCobrancaEm`), então não há
 *   cobrança em dobro no ciclo.
 * - **Em atraso:** a 1ª cobrança é hoje, e a que for paga recupera a assinatura ({@see ConfirmarAssinatura}).
 * - Confirmado o novo, cancelar o antigo é repetível ({@see cancelarAntigas()}): se o provedor falhar na
 *   hora, uma rotina horária termina o serviço, e o alerta fica no log.
 */
final class TrocarCartao
{
    public function __construct(private readonly PaymentProvider $provedor) {}

    /**
     * @throws GestaoNegada
     * @throws ProvedorIndisponivel
     */
    public function iniciar(Usuario $usuario): TrocaDeCartao
    {
        if (Personificacao::ativa() !== null) {
            throw GestaoNegada::adminPersonificando();
        }

        $assinatura = Assinatura::query()
            ->where('usuario_id', $usuario->id)
            ->where('trial', false)
            ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::EmAtraso->value])
            ->first();

        if ($assinatura === null) {
            throw GestaoNegada::semAssinatura();
        }

        if ($assinatura->id_externo === null) {
            throw GestaoNegada::naoRenovaSozinha();
        }

        $aberta = TrocaDeCartao::query()
            ->where('assinatura_id', $assinatura->id)
            ->where('status', 'pendente')
            ->whereNotNull('checkout_url')
            ->where('checkout_expira_em', '>', DB::raw('now()'))
            ->latest('created_at')
            ->first();

        // Clique repetido: o mesmo checkout ainda vale.
        if ($aberta !== null) {
            return $aberta;
        }

        $atraso = $assinatura->status === StatusAssinatura::EmAtraso;
        $ciclo = CicloCobranca::from((string) $assinatura->ciclo);
        $plano = Plano::query()->findOrFail($assinatura->plano_id);
        $valor = $assinatura->preco_snapshot_centavos ?? IniciarAssinatura::precoDe($plano, $ciclo);

        $troca = TrocaDeCartao::query()->create([
            'usuario_id' => $usuario->id,
            'assinatura_id' => $assinatura->id,
            'provedor' => $this->provedor->nome(),
            'status' => 'pendente',
            'cobra_agora' => $atraso,
            'antiga_id_externo' => $assinatura->id_externo,
        ]);

        $volta = route('meu-plano');

        try {
            $sessao = $this->provedor->startCheckout(new PedidoCheckout(
                referenciaInterna: $troca->id,
                descricao: "Troca de cartão · Plano {$plano->nome}",
                valorCentavos: $valor,
                meios: [MeioPagamento::Cartao],
                expiraEmMinutos: (int) config('services.payments.cartao_expiracao_minutos', 60),
                urlSucesso: $volta,
                urlCancelamento: $volta,
                urlExpirado: $volta,
                recorrencia: $ciclo,
                primeiraCobrancaEm: $atraso ? null : $assinatura->proximo_vencimento->toDateTimeImmutable(),
            ));
        } catch (ProvedorIndisponivel $e) {
            $troca->forceFill(['status' => 'falhou'])->save();

            Log::error('cobranca.provedor_falhou', [
                'module' => 'cobranca',
                'action' => 'iniciar_troca_de_cartao',
                'troca_id' => $troca->id,
                'provedor' => $this->provedor->nome(),
                'motivo' => $e->getMessage(),
            ]);

            throw $e;
        }

        $troca->forceFill([
            'checkout_id_externo' => $sessao->referencia->id,
            'checkout_url' => $sessao->url,
            'checkout_expira_em' => $sessao->expiraEm,
        ])->save();

        AuditLogger::log(
            action: 'cobranca.troca_de_cartao_iniciada',
            subjectType: 'TrocaDeCartao',
            subjectId: $troca->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
            after: ['assinatura_id' => $assinatura->id, 'cobra_agora' => $atraso, 'provedor' => $troca->provedor],
        );

        return $troca;
    }

    /**
     * O provedor confirmou o cartão novo (e criou a assinatura nova `$novaId`). Idempotente.
     * Aceita também troca expirada ou cancelada na nossa ponta: se o provedor criou a assinatura, ela
     * existe e cobraria em dobro se ficasse órfã.
     */
    public function confirmar(TrocaDeCartao $troca, string $novaId, ?string $bandeira, ?string $ultimos4): void
    {
        $confirmada = DB::transaction(function () use ($troca, $novaId, $bandeira, $ultimos4): bool {
            $troca = TrocaDeCartao::query()->lockForUpdate()->findOrFail($troca->id);

            if ($troca->status === 'concluida') {
                return false;
            }

            $assinatura = Assinatura::query()->lockForUpdate()->findOrFail($troca->assinatura_id);

            if ($assinatura->id_externo !== $troca->antiga_id_externo) {
                // A assinatura já aponta para outra coisa (outra troca concluída antes): esta nova ficaria órfã.
                Log::error('cobranca.troca_de_cartao_obsoleta', [
                    'module' => 'cobranca',
                    'action' => 'confirmar_troca_de_cartao',
                    'troca_id' => $troca->id,
                    'assinatura_id' => $assinatura->id,
                ]);

                return false;
            }

            $assinatura->forceFill([
                'provedor' => $troca->provedor,
                'id_externo' => $novaId,
                'cartao_bandeira' => $bandeira ?? $assinatura->cartao_bandeira,
                'cartao_ultimos4' => $ultimos4 ?? $assinatura->cartao_ultimos4,
            ])->save();

            // Em atraso (STORY-079 × 081): a cobrança atrasada é a da assinatura ANTIGA, que vai ser cancelada.
            // A régua para de retentá-la e de encerrar o ciclo por ela: quem cobra agora é o cartão novo, e
            // o pagamento dele restabelece a assinatura ({@see ConfirmarAssinatura}). Sem isso, a régua
            // pediria nova captura da cobrança velha enquanto a nova cobra o mesmo ciclo (cobrança em dobro).
            if ($assinatura->status === StatusAssinatura::EmAtraso) {
                $assinatura->forceFill([
                    'atraso_cobranca_id' => null,
                    'proxima_tentativa_em' => null,
                    'tentativas_cobranca' => 0,
                ])->save();
            }

            $troca->forceFill(['status' => 'concluida', 'nova_id_externo' => $novaId, 'concluida_em' => now()])->save();

            AuditLogger::log(
                action: 'cobranca.troca_de_cartao_concluida',
                subjectType: 'TrocaDeCartao',
                subjectId: $troca->id,
                actorType: 'system',
                usuarioId: $troca->usuario_id,
                after: ['assinatura_id' => $assinatura->id, 'cartao_bandeira' => $bandeira, 'cartao_ultimos4' => $ultimos4],
            );

            return true;
        });

        if ($confirmada) {
            $this->cancelarAntiga(TrocaDeCartao::query()->findOrFail($troca->id));
        }
    }

    /** O checkout acabou sem cartão novo (prazo, desistência ou recusa): nada muda na assinatura. */
    public function encerrar(TrocaDeCartao $troca, string $status): void
    {
        TrocaDeCartao::query()->whereKey($troca->id)->where('status', 'pendente')->update(['status' => $status]);
    }

    /**
     * Termina o cancelamento das assinaturas antigas que o provedor não aceitou na hora.
     *
     * @return int quantas foram canceladas
     */
    public function cancelarAntigas(): int
    {
        $feitas = 0;

        foreach (TrocaDeCartao::query()->where('status', 'concluida')->whereNull('antiga_cancelada_em')->get() as $troca) {
            $feitas += $this->cancelarAntiga($troca) ? 1 : 0;
        }

        return $feitas;
    }

    private function cancelarAntiga(TrocaDeCartao $troca): bool
    {
        try {
            $this->provedor->cancelSubscription(new ReferenciaProvedor($troca->provedor, $troca->antiga_id_externo));
        } catch (ProvedorIndisponivel $e) {
            // Risco real de cobrança em dobro no próximo ciclo: alerta, e a rotina horária tenta de novo.
            Log::error('cobranca.troca_de_cartao_antiga_nao_cancelada', [
                'module' => 'cobranca',
                'action' => 'cancelar_assinatura_antiga',
                'troca_id' => $troca->id,
                'motivo' => $e->getMessage(),
            ]);

            return false;
        }

        $troca->forceFill(['antiga_cancelada_em' => CarbonImmutable::now()])->save();

        return true;
    }
}
