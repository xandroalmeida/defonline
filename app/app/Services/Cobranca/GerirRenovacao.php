<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Support\Personificacao;
use Illuminate\Support\Facades\DB;

/**
 * STORY-081 CA-4 e CA-5 — cancelar e reativar a RENOVAÇÃO (espec §2.4), nunca o acesso: o que foi pago
 * vale até o fim do ciclo. Cancelar é `Cancelada` na carteira, com `proximo_vencimento` intacto.
 *
 * O provedor é avisado ANTES de a carteira mudar: se ele não responder, nada muda e a tela diz o
 * estado real ("sua renovação continua ativa"), em vez de prometer o que ainda pode cobrar.
 */
final class GerirRenovacao
{
    public function __construct(
        private readonly PaymentProvider $provedor,
        private readonly Carteira $carteira,
    ) {}

    /**
     * @throws GestaoNegada
     * @throws ProvedorIndisponivel
     */
    public function cancelar(Usuario $usuario): Assinatura
    {
        return $this->mudar($usuario, StatusAssinatura::Ativa, StatusAssinatura::Cancelada, 'cobranca.renovacao_cancelada');
    }

    /**
     * @throws GestaoNegada
     * @throws ProvedorIndisponivel
     */
    public function reativar(Usuario $usuario): Assinatura
    {
        return $this->mudar($usuario, StatusAssinatura::Cancelada, StatusAssinatura::Ativa, 'cobranca.renovacao_reativada');
    }

    private function mudar(Usuario $usuario, StatusAssinatura $de, StatusAssinatura $para, string $acao): Assinatura
    {
        if (Personificacao::ativa() !== null) {
            throw GestaoNegada::adminPersonificando();
        }

        return DB::transaction(function () use ($usuario, $de, $para, $acao): Assinatura {
            $assinatura = Assinatura::query()
                ->where('usuario_id', $usuario->id)
                ->where('trial', false)
                ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::Cancelada->value])
                ->orderByDesc('data_inicio')
                ->lockForUpdate()
                ->first();

            if ($assinatura === null) {
                throw GestaoNegada::semAssinatura();
            }

            // Clique repetido ou duas abas: já está como se pediu.
            if ($assinatura->status === $para && $assinatura->proximo_vencimento->isFuture()) {
                return $assinatura;
            }

            if ($assinatura->status !== $de || $assinatura->proximo_vencimento->isPast()) {
                throw GestaoNegada::jaEncerrada();
            }

            if ($assinatura->id_externo === null || $assinatura->provedor === null) {
                throw GestaoNegada::naoRenovaSozinha();
            }

            $ref = new ReferenciaProvedor($assinatura->provedor, $assinatura->id_externo);
            $para === StatusAssinatura::Cancelada ? $this->provedor->suspendSubscription($ref) : $this->provedor->resumeSubscription($ref);

            $assinatura = $this->carteira->mudarStatus($assinatura->id, $para);

            AuditLogger::log(
                action: $acao,
                subjectType: 'Assinatura',
                subjectId: $assinatura->id,
                actorType: 'user',
                actorId: $usuario->id,
                usuarioId: $usuario->id,
                after: ['status' => $para->value, 'acesso_ate' => $assinatura->proximo_vencimento->toIso8601String()],
            );

            return $assinatura;
        });
    }
}
