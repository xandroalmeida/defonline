<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\ReguaDeCobranca;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use Illuminate\Support\Facades\DB;

/**
 * STORY-079 CA-1 / espec §3.7 — lembrete de renovação quando faltam 7 dias ou menos para o fim do ciclo.
 *
 * Idempotente por ciclo: a assinatura guarda para qual `periodos_pagos` o aviso já saiu. A vaga é
 * reservada com um UPDATE condicional ANTES do envio, então duas rodadas ao mesmo tempo (ou a rotina
 * rodando de novo) não mandam o e-mail duas vezes; se o envio falhar, a reserva é devolvida e a
 * próxima rodada tenta. Só avisa quem renova sozinho: assinatura `ativa`, não trial, com preço
 * contratado. Cancelada, em atraso e encerrada não recebem.
 */
final class AvisarRenovacao
{
    public function __construct(private readonly AvisosDeCobranca $avisos) {}

    /**
     * @param  (callable(int, int, int): void)|null  $progresso  recebe (feitas, total, enviados)
     * @return array{enviados: int, falhas: int}
     */
    public function executar(?callable $progresso = null): array
    {
        if (! CobrancaConfig::ligada()) {
            return ['enviados' => 0, 'falhas' => 0];
        }

        $agora = now();
        $ids = Assinatura::query()
            ->where('status', StatusAssinatura::Ativa->value)
            ->where('trial', false)
            ->whereNotNull('plano_id')
            ->whereNotNull('preco_snapshot_centavos')
            ->where('proximo_vencimento', '>', $agora->format('Y-m-d H:i:sP'))
            ->where('proximo_vencimento', '<=', $agora->copy()->addDays(ReguaDeCobranca::AVISO_RENOVACAO_DIAS)->format('Y-m-d H:i:sP'))
            ->where(fn ($q) => $q->whereNull('aviso_renovacao_periodo')->orWhereColumn('aviso_renovacao_periodo', '!=', 'periodos_pagos'))
            ->pluck('id');

        $enviados = 0;
        $falhas = 0;

        foreach ($ids as $i => $id) {
            $a = $this->reservar($id);

            if ($a !== null) {
                if ($this->avisos->renovacaoProxima($a)) {
                    $enviados++;
                } else {
                    $falhas++;
                    Assinatura::query()->whereKey($id)->update(['aviso_renovacao_periodo' => null]);
                }
            }

            if ($progresso !== null) {
                $progresso($i + 1, $ids->count(), $enviados);
            }
        }

        return ['enviados' => $enviados, 'falhas' => $falhas];
    }

    /** Fica com a vaga do aviso deste ciclo, ou `null` se outra rodada já ficou. */
    private function reservar(string $id): ?Assinatura
    {
        $reservada = Assinatura::query()
            ->whereKey($id)
            ->where('status', StatusAssinatura::Ativa->value)
            ->where(fn ($q) => $q->whereNull('aviso_renovacao_periodo')->orWhereColumn('aviso_renovacao_periodo', '!=', 'periodos_pagos'))
            ->update(['aviso_renovacao_periodo' => DB::raw('periodos_pagos')]);

        return $reservada === 1 ? Assinatura::query()->find($id) : null;
    }
}
