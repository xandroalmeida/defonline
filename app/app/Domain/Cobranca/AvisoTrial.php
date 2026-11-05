<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;

/**
 * STORY-074 / spec STORY-070-trial §3 (tabela "Avisos durante o teste") — qual aviso o Usuário em
 * trial vê na Home. Pura: recebe a assinatura (trial) e se ainda há saldo por outro meio.
 *
 * `severidade`: `info` ou `bloqueio` (DDR-009). `acao`: `planos` (rótulo na view).
 *
 * @phpstan-type Aviso array{severidade: 'info'|'bloqueio', texto: string, acao: string, rotulo: string}
 */
final class AvisoTrial
{
    private const FUSO = 'America/Sao_Paulo';

    /**
     * @param  bool  $temOutroSaldo  Há lote de crédito válido: o fim do teste não bloqueia nada.
     * @return Aviso|null
     */
    public static function para(AssinaturaVista $trial, CarbonImmutable $agora, bool $temOutroSaldo = false): ?array
    {
        if (! $trial->trial || $trial->status !== StatusAssinatura::Ativa) {
            return null;
        }

        $fim = $trial->vencimento->setTimezone(self::FUSO);
        $data = $fim->format('d/m/Y');

        if ($trial->vencimento->lessThanOrEqualTo($agora)) {
            return $temOutroSaldo ? null : [
                'severidade' => 'bloqueio',
                'texto' => "Seu teste terminou em {$data}. Seus diagnósticos continuam disponíveis.",
                'acao' => 'planos',
                'rotulo' => 'Ver planos',
            ];
        }

        $dias = (int) $agora->setTimezone(self::FUSO)->startOfDay()->diffInDays($fim->startOfDay());

        if ($dias === 0) {
            return ['severidade' => 'info', 'texto' => 'Seu teste termina hoje.', 'acao' => 'planos', 'rotulo' => 'Assinar um plano'];
        }

        if ($dias <= 2) {
            $quando = $dias === 1 ? 'em 1 dia' : "em {$dias} dias";

            return ['severidade' => 'info', 'texto' => "Seu teste termina em {$data} ({$quando}). Assine para continuar usando o que já faz.", 'acao' => 'planos', 'rotulo' => 'Assinar um plano'];
        }

        if ($trial->cotaRestante === 0) {
            return ['severidade' => 'info', 'texto' => "Você usou a análise do teste. O acesso ao Raio X continua até {$data}.", 'acao' => 'planos', 'rotulo' => 'Ver planos'];
        }

        $restantes = $trial->cotaRestante === 1 ? '1 análise restante' : "{$trial->cotaRestante} análises restantes";

        return ['severidade' => 'info', 'texto' => "Teste grátis: termina em {$data} · {$restantes}.", 'acao' => 'planos', 'rotulo' => 'Ver planos'];
    }
}
