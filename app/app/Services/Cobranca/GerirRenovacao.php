<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\TrocaDeCartao;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Observabilidade\EventosCobranca;
use App\Support\Personificacao;
use Illuminate\Support\Facades\DB;

/**
 * STORY-081 CA-4 e CA-5 — cancelar e reativar a RENOVAÇÃO (espec §2.4), nunca o acesso: o que foi pago
 * vale até o fim do ciclo. Cancelar é `Cancelada` na carteira, com `proximo_vencimento` intacto.
 *
 * **Cancelar em atraso** (decisão do PO: o Termo v2 promete "a qualquer momento"): o ciclo não foi pago,
 * então não há acesso a preservar. A régua para, a recorrência é CANCELADA no provedor (não suspensa:
 * não há o que reativar), nenhuma cobrança nova nem retentativa ocorre, o acesso é bloqueado na hora
 * (`Expirada`, `encerrada_em` agora) e o histórico (faturas, `atraso_desde`, `atraso_cobranca_id`) fica.
 * Checkout de troca de cartão ainda aberto é fechado, para um pagamento tardio dele não religar a cobrança.
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
        return $this->mudar($usuario, [StatusAssinatura::Ativa, StatusAssinatura::EmAtraso], StatusAssinatura::Cancelada, 'cobranca.renovacao_cancelada');
    }

    /**
     * @throws GestaoNegada
     * @throws ProvedorIndisponivel
     */
    public function reativar(Usuario $usuario): Assinatura
    {
        return $this->mudar($usuario, [StatusAssinatura::Cancelada], StatusAssinatura::Ativa, 'cobranca.renovacao_reativada');
    }

    /** @param list<StatusAssinatura> $de */
    private function mudar(Usuario $usuario, array $de, StatusAssinatura $para, string $acao): Assinatura
    {
        if (Personificacao::ativa() !== null) {
            throw GestaoNegada::adminPersonificando();
        }

        return DB::transaction(function () use ($usuario, $de, $para, $acao): Assinatura {
            $assinatura = Assinatura::query()
                ->where('usuario_id', $usuario->id)
                ->where('trial', false)
                ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::Cancelada->value, StatusAssinatura::EmAtraso->value])
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

            $emAtraso = $assinatura->status === StatusAssinatura::EmAtraso;

            // Em atraso o ciclo já passou do vencimento por definição: só o estado importa.
            if (! in_array($assinatura->status, $de, true) || (! $emAtraso && $assinatura->proximo_vencimento->isPast())) {
                throw GestaoNegada::jaEncerrada();
            }

            if ($assinatura->id_externo === null || $assinatura->provedor === null) {
                throw GestaoNegada::naoRenovaSozinha();
            }

            if ($emAtraso) {
                return $this->cancelarEmAtraso($assinatura, $usuario);
            }

            $ref = new ReferenciaProvedor($assinatura->provedor, $assinatura->id_externo);
            $para === StatusAssinatura::Cancelada ? $this->provedor->suspendSubscription($ref) : $this->provedor->resumeSubscription($ref);

            $assinatura = $this->carteira->mudarStatus($assinatura->id, $para);

            $para === StatusAssinatura::Cancelada
                ? EventosCobranca::cancelamento($assinatura, EventosCobranca::ORIGEM_CLIENTE)
                : EventosCobranca::cancelamentoRevertido($assinatura);

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

    /** Chamado com a linha da assinatura travada (dentro da transação de {@see mudar()}). */
    private function cancelarEmAtraso(Assinatura $assinatura, Usuario $usuario): Assinatura
    {
        // Provedor primeiro: se não responde, nada muda e a tela diz que a renovação continua (a régua segue).
        $this->provedor->cancelSubscription(new ReferenciaProvedor((string) $assinatura->provedor, (string) $assinatura->id_externo));

        $agora = now();
        $assinatura->forceFill([
            'status' => StatusAssinatura::Expirada,
            'data_cancelamento' => $agora,
            'encerrada_em' => $agora,
            'proxima_tentativa_em' => null,
            'encerramento_pendente_no_provedor' => false,
        ])->save();

        TrocaDeCartao::query()->where('assinatura_id', $assinatura->id)->where('status', 'pendente')->update(['status' => 'cancelada']);

        EventosCobranca::cancelamento($assinatura, EventosCobranca::ORIGEM_CLIENTE_EM_ATRASO);

        AuditLogger::log(
            action: 'cobranca.renovacao_cancelada_em_atraso',
            subjectType: 'Assinatura',
            subjectId: $assinatura->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
            after: ['status' => StatusAssinatura::Expirada->value, 'atraso_desde' => $assinatura->atraso_desde?->toIso8601String(), 'cobranca_em_aberto' => $assinatura->atraso_cobranca_id],
        );

        return $assinatura;
    }
}
