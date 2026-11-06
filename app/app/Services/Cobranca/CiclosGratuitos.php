<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\CotaContratada;
use App\Domain\Cobranca\CotaMensal;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\RenovacaoPix;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\Plano;
use App\Observabilidade\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * STORY-083 CA-6, CA-7, CA-8 — o que acontece no vencimento de uma assinatura que não tem cobrança no provedor
 * naquele ciclo:
 *
 * - **Ciclo gratuito do desconto** (desconto total, ou valor abaixo do mínimo): a renovação é nossa. Grava uma
 *   Invoice de valor zero (a cobrança fica no histórico), renova a cota e avança o ciclo.
 * - **Desconto acabou sem meio de pagamento:** a assinatura é encerrada e as funções pagas ficam bloqueadas
 *   até o usuário cadastrar um cartão (motivo `desconto_acabou_sem_cartao`).
 *
 * Idempotente: só toca assinatura vencida e confere de novo sob lock.
 */
final class CiclosGratuitos
{
    /** @return int quantas assinaturas foram tratadas */
    public function executar(?string $usuarioId = null): int
    {
        $ids = Assinatura::query()
            ->where('trial', false)
            ->where('status', StatusAssinatura::Ativa->value)
            ->where('proximo_vencimento', '<=', now())
            ->where(fn ($q) => $q->where('desconto_centavos', '>', 0)->orWhere('meio_pagamento', 'nenhum'))
            ->when($usuarioId !== null, fn ($q) => $q->where('usuario_id', $usuarioId))
            ->pluck('id');

        $tratadas = 0;

        foreach ($ids as $id) {
            $tratadas += DB::transaction(fn (): int => $this->tratar($id));
        }

        return $tratadas;
    }

    private function tratar(string $id): int
    {
        $a = Assinatura::query()->lockForUpdate()->find($id);

        if ($a === null || $a->status !== StatusAssinatura::Ativa || $a->proximo_vencimento->greaterThan(now())) {
            return 0;
        }

        // STORY-090 CA-4: o downgrade agendado vale nesta renovação, como na paga: o desconto que conta é o do plano
        // novo, e uma troca de ciclo começa o período 1.
        $cicloMudou = $a->troca_plano_id !== null && ($a->troca_ciclo ?? $a->ciclo) !== $a->ciclo;
        $proximo = $cicloMudou ? 1 : $a->periodos_pagos + 1;

        if ($a->descontoDaProximaCobranca()?->gratuito($proximo) === true) {
            $this->renovarGratis($a, $proximo);

            return 1;
        }

        // O desconto acabou e não há cartão no provedor: nada a cobrar, e nada a renovar.
        if ($a->semMeioDePagamento() && $a->id_externo === null) {
            $a->forceFill(['status' => StatusAssinatura::Expirada, 'encerrada_em' => now()])->save();

            AuditLogger::log(
                action: 'cobranca.desconto_acabou_sem_cartao',
                subjectType: 'Assinatura',
                subjectId: $a->id,
                actorType: 'system',
                usuarioId: $a->usuario_id,
                after: ['periodos_pagos' => $a->periodos_pagos],
            );

            return 1;
        }

        return 0;
    }

    private function renovarGratis(Assinatura $a, int $periodo): void
    {
        $plano = Plano::query()->findOrFail($a->troca_plano_id ?? $a->plano_id);
        $ciclo = CicloCobranca::from((string) ($a->troca_ciclo ?? $a->ciclo));
        $cicloMudou = $ciclo->value !== $a->ciclo;
        $agora = CarbonImmutable::now();
        $cota = CotaContratada::naRenovacao($a->cota_snapshot, (string) $a->plano_id, $plano->id, $plano->cota_mensal);
        // Mudou o ciclo: o período novo nasce no fim do atual (a data de início é a âncora do vencimento).
        $inicio = $cicloMudou ? CarbonImmutable::instance($a->proximo_vencimento) : CarbonImmutable::instance($a->data_inicio);
        $proximoPreco = (int) ($a->troca_preco_centavos ?? $a->preco_snapshot_centavos);
        $a->aplicarRetratoDaTroca();
        $desconto = $a->desconto();
        // Pix anual conta o ano a partir do início (RenovacaoPix); os demais, pelo ciclo.
        $vencimento = $a->pagoComPix() ? RenovacaoPix::novoVencimento($a->data_inicio, $a->periodos_pagos) : $ciclo->vencimento($inicio, $periodo);

        $invoice = Invoice::query()->create([
            'usuario_id' => $a->usuario_id,
            'tipo' => 'assinatura',
            'plano_id' => $plano->id,
            'ciclo' => $ciclo->value,
            'cota_snapshot' => $cota,
            'descricao' => "Plano {$plano->nome} · renovação {$ciclo->value}",
            'valor_centavos' => 0,
            'status' => EstadoCobranca::Paga,
            'meio' => 'nenhum',
            'provedor' => 'interno',
            'pago_em' => $agora,
            'assinatura_id' => $a->id,
            'renovacao' => true,
            'cupom_id' => $a->cupom_id,
            'preco_cheio_centavos' => $desconto?->cheio,
            'desconto_centavos' => $desconto === null ? 0 : $desconto->desconto,
        ]);

        app(Carteira::class)->renovarCota($a->id, $cota, $vencimento);

        $a->refresh()->forceFill([
            'plano_id' => $plano->id,
            'ciclo' => $ciclo->value,
            'data_inicio' => $inicio,
            'periodos_pagos' => $periodo,
            'preco_snapshot_centavos' => $desconto?->valorDoPeriodo($periodo + 1) ?? $proximoPreco,
            'proxima_cota_em' => $ciclo === CicloCobranca::Anual ? CotaMensal::proxima($inicio, $agora, $vencimento) : null,
            ...ConfirmarAssinatura::LIMPA_TROCA,
            ...($cicloMudou ? ['aviso_renovacao_periodo' => null] : []),
        ])->save();

        AuditLogger::log(
            action: 'cobranca.ciclo_gratuito_renovado',
            subjectType: 'Assinatura',
            subjectId: $a->id,
            actorType: 'system',
            usuarioId: $a->usuario_id,
            after: ['periodos_pagos' => $periodo, 'proximo_vencimento' => $vencimento->toIso8601String(), 'invoice_id' => $invoice->id],
        );
    }
}
