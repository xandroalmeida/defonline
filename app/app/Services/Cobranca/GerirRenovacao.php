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
use Illuminate\Support\Facades\Log;

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

    /**
     * STORY-093 CA-6 — três tempos, e a chamada ao provedor (rede) NUNCA com linha travada nem transação aberta:
     *
     * 1. confere o pedido sobre a assinatura lida, sem lock;
     * 2. avisa o provedor (suspender, retomar ou cancelar são idempotentes). Se ele não responder, nada mudou aqui;
     * 3. sob lock, confere de novo que a assinatura é a mesma e só então muda a carteira. Se mudou no meio (a régua,
     *    um pagamento), o pedido é desfeito no provedor (melhor esforço) e a ação é negada: o estado local nunca
     *    fica diferente do que o cliente vê. O que a desfazer não conseguir vira alerta para a equipe.
     *
     * @param  list<StatusAssinatura>  $de
     */
    private function mudar(Usuario $usuario, array $de, StatusAssinatura $para, string $acao): Assinatura
    {
        if (Personificacao::ativa() !== null) {
            throw GestaoNegada::adminPersonificando();
        }

        $lida = $this->assinaturaDoUsuario($usuario);

        // Clique repetido ou duas abas: já está como se pediu.
        if ($lida->status === $para && $lida->proximo_vencimento->isFuture()) {
            return $lida;
        }

        $this->conferir($lida, $de);

        $ref = new ReferenciaProvedor((string) $lida->provedor, (string) $lida->id_externo);
        $emAtraso = $lida->status === StatusAssinatura::EmAtraso;

        // Provedor primeiro: se não responde, nada muda e a tela diz o estado real (a renovação continua).
        if ($emAtraso) {
            $this->provedor->cancelSubscription($ref);
        } elseif ($para === StatusAssinatura::Cancelada) {
            $this->provedor->suspendSubscription($ref);
        } else {
            $this->provedor->resumeSubscription($ref);
        }

        try {
            return DB::transaction(function () use ($usuario, $de, $para, $acao, $lida, $emAtraso): Assinatura {
                $assinatura = Assinatura::query()->lockForUpdate()->find($lida->id);

                if ($assinatura === null || $assinatura->status !== $lida->status || $assinatura->id_externo !== $lida->id_externo || $assinatura->provedor !== $lida->provedor) {
                    throw GestaoNegada::jaEncerrada();
                }

                $this->conferir($assinatura, $de);

                if ($emAtraso) {
                    return $this->cancelarEmAtraso($assinatura, $usuario);
                }

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
        } catch (GestaoNegada $e) {
            $this->desfazerNoProvedor($lida, $ref, $para, $emAtraso);

            throw $e;
        }
    }

    /** A assinatura do usuário que pode ser gerida (ativa, cancelada ou em atraso), sem lock. */
    private function assinaturaDoUsuario(Usuario $usuario): Assinatura
    {
        $assinatura = Assinatura::query()
            ->where('usuario_id', $usuario->id)
            ->where('trial', false)
            ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::Cancelada->value, StatusAssinatura::EmAtraso->value])
            ->orderByDesc('data_inicio')
            ->first();

        return $assinatura ?? throw GestaoNegada::semAssinatura();
    }

    /**
     * @param  list<StatusAssinatura>  $de
     *
     * @throws GestaoNegada
     */
    private function conferir(Assinatura $assinatura, array $de): void
    {
        $emAtraso = $assinatura->status === StatusAssinatura::EmAtraso;

        // Em atraso o ciclo já passou do vencimento por definição: só o estado importa.
        if (! in_array($assinatura->status, $de, true) || (! $emAtraso && $assinatura->proximo_vencimento->isPast())) {
            throw GestaoNegada::jaEncerrada();
        }

        if ($assinatura->id_externo === null || $assinatura->provedor === null) {
            throw GestaoNegada::naoRenovaSozinha();
        }
    }

    /**
     * A assinatura mudou entre o aviso ao provedor e o lock: o que o provedor fez não vale mais. Cancelar em atraso
     * não tem volta (recorrência cancelada); suspender volta com retomar e vice-versa.
     */
    private function desfazerNoProvedor(Assinatura $lida, ReferenciaProvedor $ref, StatusAssinatura $para, bool $emAtraso): void
    {
        try {
            if ($emAtraso) {
                throw new ProvedorIndisponivel('o cancelamento em atraso no provedor não tem volta');
            }

            $para === StatusAssinatura::Cancelada ? $this->provedor->resumeSubscription($ref) : $this->provedor->suspendSubscription($ref);
        } catch (ProvedorIndisponivel $e) {
            Log::error('cobranca.gestao_do_provedor_divergente', [
                'module' => 'cobranca',
                'action' => 'gerir_renovacao',
                'assinatura_id' => $lida->id,
                'pedido' => $para->value,
                'motivo' => $e->getMessage(),
            ]);
        }
    }

    /** Chamado com a linha da assinatura travada (dentro da transação de {@see mudar()}); o provedor já foi avisado. */
    private function cancelarEmAtraso(Assinatura $assinatura, Usuario $usuario): Assinatura
    {
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
