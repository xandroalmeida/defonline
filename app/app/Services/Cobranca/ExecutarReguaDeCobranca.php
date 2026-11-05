<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\AcaoDaRegua;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\ReguaDeCobranca;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TentativaNaoRealizada;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Observabilidade\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * STORY-079 CA-3, CA-4, CA-5 e CA-7 — a régua de cobrança, rodada de hora em hora.
 *
 * Para cada assinatura "Em atraso" cuja próxima tentativa chegou:
 *
 * 1. consulta a cobrança que está sendo cobrada. Se já foi paga, restabelece a assinatura (o webhook
 *    pode ter se perdido). Se o provedor não respondeu, NADA é contado: não é recusa do cliente (CA-7),
 *    a equipe é alertada e a próxima rodada tenta de novo;
 * 2. sob lock da linha, decide pela {@see ReguaDeCobranca}: aguardar, retentar ou encerrar. A tentativa
 *    é "reservada" (contador e próxima data gravados) ANTES de chamar o provedor, então duas rodadas
 *    simultâneas ou uma que cai no meio nunca pedem a mesma tentativa duas vezes;
 * 3. retentar = pedir ao provedor nova captura da MESMA cobrança ({@see PaymentProvider::retryCharge()}).
 *    Nunca se cria cobrança: por construção não existe cobrança em dobro. Se o provedor falhar nessa
 *    chamada, a reserva é desfeita;
 * 4. depois da última retentativa, 48 h sem pagamento: o ciclo é encerrado (status `expirada`, histórico
 *    intacto), o assinante é avisado e a recorrência é cancelada no provedor.
 *
 * Também reenvia o aviso de falha que não saiu e refaz o cancelamento no provedor que não completou.
 * Com a cobrança desligada (STORY-073) não faz nada.
 */
final class ExecutarReguaDeCobranca
{
    public function __construct(
        private readonly PaymentProvider $provedor,
        private readonly ConfirmarAssinatura $assinaturas,
        private readonly AvisosDeCobranca $avisos,
    ) {}

    /**
     * @param  (callable(int, int, array<string, int>): void)|null  $progresso  recebe (feitas, total, contadores)
     * @return array{retentadas: int, restabelecidas: int, encerradas: int, indisponivel: int, aguardando: int, avisos: int, cancelamentos: int}
     */
    public function executar(?callable $progresso = null): array
    {
        $c = ['retentadas' => 0, 'restabelecidas' => 0, 'encerradas' => 0, 'indisponivel' => 0, 'aguardando' => 0, 'avisos' => 0, 'cancelamentos' => 0];

        if (! CobrancaConfig::ligada()) {
            return $c;
        }

        // Pix anual nunca entra na régua de cartão: nada falhou, e a STORY-080 só avisa e encerra.
        $devidas = Assinatura::query()
            ->where('meio_pagamento', '!=', 'pix')
            ->where('status', StatusAssinatura::EmAtraso->value)
            ->whereNotNull('proxima_tentativa_em')
            ->where('proxima_tentativa_em', '<=', now()->format('Y-m-d H:i:sP'))
            ->orderBy('proxima_tentativa_em')
            ->pluck('id');
        $semAviso = Assinatura::query()
            ->where('meio_pagamento', '!=', 'pix')
            ->where('status', StatusAssinatura::EmAtraso->value)
            ->whereNull('atraso_aviso_em')
            ->pluck('id');
        $semCancelar = Assinatura::query()
            ->where('meio_pagamento', '!=', 'pix')
            ->where('encerramento_pendente_no_provedor', true)
            ->pluck('id');

        $total = $devidas->count() + $semAviso->count() + $semCancelar->count();
        $feitas = 0;
        $andamento = function () use (&$feitas, $total, &$c, $progresso): void {
            $feitas++;

            if ($progresso !== null) {
                $progresso($feitas, $total, $c);
            }
        };

        foreach ($devidas as $id) {
            $resultado = $this->processar($id);
            $c[$resultado]++;
            $andamento();
        }

        foreach ($semAviso as $id) {
            $a = Assinatura::query()->find($id);

            if ($a !== null && $a->status === StatusAssinatura::EmAtraso && $a->atraso_aviso_em === null && $this->avisos->falhaDeCobranca($a)) {
                $a->forceFill(['atraso_aviso_em' => now()])->save();
                $c['avisos']++;
            }

            $andamento();
        }

        foreach ($semCancelar as $id) {
            $a = Assinatura::query()->find($id);

            if ($a !== null && $this->cancelarNoProvedor($a)) {
                $c['cancelamentos']++;
            }

            $andamento();
        }

        return $c;
    }

    /** @return 'retentadas'|'restabelecidas'|'encerradas'|'indisponivel'|'aguardando' */
    private function processar(string $id): string
    {
        $a = Assinatura::query()->find($id);

        if ($a === null || $a->pagoComPix() || $a->status !== StatusAssinatura::EmAtraso || $a->atraso_cobranca_id === null || $a->provedor === null) {
            return 'aguardando';
        }

        $referencia = new ReferenciaProvedor($a->provedor, $a->atraso_cobranca_id);

        try {
            $cobranca = $this->provedor->getCharge($referencia);
        } catch (ProvedorIndisponivel) {
            $this->alertarIndisponibilidade($a, 'consultar_cobranca');

            return 'indisponivel';
        }

        if ($cobranca->estado === EstadoCobranca::Paga) {
            $this->assinaturas->aplicar(null, $cobranca, $a->provedor);

            return 'restabelecidas';
        }

        $reserva = $this->reservar($id, $cobranca->estado->final());

        if ($reserva['acao'] === AcaoDaRegua::Aguardar) {
            return 'aguardando';
        }

        if ($reserva['acao'] === AcaoDaRegua::Encerrar) {
            $this->concluirEncerramento(Assinatura::query()->findOrFail($id));

            return 'encerradas';
        }

        try {
            $depois = $this->provedor->retryCharge($referencia);
        } catch (ProvedorIndisponivel) {
            $this->desfazerReserva($id, $reserva);
            $this->alertarIndisponibilidade($a, 'retentar_cobranca');

            return 'indisponivel';
        } catch (TentativaNaoRealizada) {
            // O provedor está de pé e recusou o pedido: a tentativa conta como feita, sem sucesso.
            Log::error('cobranca.retentativa_recusada_pelo_provedor', [
                'module' => 'cobranca',
                'action' => 'regua_de_cobranca',
                'assinatura_id' => $id,
                'tentativa' => $reserva['tentativas'],
            ]);

            return 'retentadas';
        }

        Log::info('cobranca.retentativa_pedida', [
            'module' => 'cobranca',
            'action' => 'regua_de_cobranca',
            'assinatura_id' => $id,
            'tentativa' => $reserva['tentativas'],
        ]);

        if ($depois->estado === EstadoCobranca::Paga) {
            $this->assinaturas->aplicar(null, $depois, $a->provedor);

            return 'restabelecidas';
        }

        return 'retentadas';
    }

    /**
     * Reserva a tentativa sob lock: grava o contador e a próxima data, ou marca o encerramento. Uma
     * cobrança que o provedor deu por perdida (cancelada, estornada) não tem como ser retentada e
     * encerra na hora.
     *
     * @return array{acao: AcaoDaRegua, tentativas: int, anterior: CarbonImmutable|null}
     */
    private function reservar(string $id, bool $cobrancaPerdida): array
    {
        return DB::transaction(function () use ($id, $cobrancaPerdida): array {
            $a = Assinatura::query()->lockForUpdate()->findOrFail($id);
            $agora = CarbonImmutable::now();
            $anterior = $a->proxima_tentativa_em === null ? null : CarbonImmutable::instance($a->proxima_tentativa_em);

            if ($a->status !== StatusAssinatura::EmAtraso) {
                return ['acao' => AcaoDaRegua::Aguardar, 'tentativas' => $a->tentativas_cobranca, 'anterior' => $anterior];
            }

            $acao = $cobrancaPerdida ? AcaoDaRegua::Encerrar : ReguaDeCobranca::decidir($a->tentativas_cobranca, $a->proxima_tentativa_em, $agora);

            if ($acao === AcaoDaRegua::Retentar) {
                $a->forceFill([
                    'tentativas_cobranca' => $a->tentativas_cobranca + 1,
                    'proxima_tentativa_em' => ReguaDeCobranca::proximaTentativa($agora),
                ])->save();
            }

            if ($acao === AcaoDaRegua::Encerrar) {
                $a->forceFill([
                    'status' => StatusAssinatura::Expirada,
                    'encerrada_em' => $agora,
                    'proxima_tentativa_em' => null,
                    'encerramento_pendente_no_provedor' => $a->id_externo !== null,
                ])->save();

                AuditLogger::log(
                    action: 'cobranca.assinatura_encerrada_por_falta_de_pagamento',
                    subjectType: 'Assinatura',
                    subjectId: $a->id,
                    actorType: 'system',
                    usuarioId: $a->usuario_id,
                    after: ['tentativas' => $a->tentativas_cobranca, 'atraso_desde' => $a->atraso_desde?->toIso8601String(), 'cobranca_perdida' => $cobrancaPerdida],
                );
            }

            return ['acao' => $acao, 'tentativas' => $a->tentativas_cobranca, 'anterior' => $anterior];
        });
    }

    /** @param array{acao: AcaoDaRegua, tentativas: int, anterior: CarbonImmutable|null} $reserva */
    private function desfazerReserva(string $id, array $reserva): void
    {
        DB::transaction(function () use ($id, $reserva): void {
            $a = Assinatura::query()->lockForUpdate()->findOrFail($id);

            // Só desfaz se continua em atraso e ninguém mexeu depois da reserva.
            if ($a->status === StatusAssinatura::EmAtraso && $a->tentativas_cobranca === $reserva['tentativas']) {
                $a->forceFill([
                    'tentativas_cobranca' => max(0, $reserva['tentativas'] - 1),
                    'proxima_tentativa_em' => $reserva['anterior'],
                ])->save();
            }
        });
    }

    private function concluirEncerramento(Assinatura $a): void
    {
        $this->avisos->assinaturaEncerrada($a);
        $this->cancelarNoProvedor($a);
    }

    /** Para a recorrência lá. Falhar aqui não desfaz o encerramento: a marca fica e a próxima rodada tenta. */
    private function cancelarNoProvedor(Assinatura $a): bool
    {
        if ($a->provedor === null || $a->id_externo === null) {
            $a->forceFill(['encerramento_pendente_no_provedor' => false])->save();

            return true;
        }

        try {
            $this->provedor->cancelSubscription(new ReferenciaProvedor($a->provedor, $a->id_externo));
        } catch (ProvedorIndisponivel) {
            $this->alertarIndisponibilidade($a, 'cancelar_recorrencia');

            return false;
        }

        $a->forceFill(['encerramento_pendente_no_provedor' => false])->save();

        return true;
    }

    private function alertarIndisponibilidade(Assinatura $a, string $passo): void
    {
        Log::error('cobranca.provedor_indisponivel', [
            'module' => 'cobranca',
            'action' => 'regua_de_cobranca',
            'passo' => $passo,
            'assinatura_id' => $a->id,
        ]);
    }
}
