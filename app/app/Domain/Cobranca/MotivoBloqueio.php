<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-073 CA-11 — as causas da spec `STORY-070-bloqueio-sem-saldo` §4. A carteira devolve
 * o motivo, nunca um booleano. O texto de cada causa vive na view, não aqui.
 */
enum MotivoBloqueio: string
{
    case SemNada = 'sem_nada';
    case TesteUsado = 'teste_usado';
    case TesteEncerrado = 'teste_encerrado';
    case CotaEsgotada = 'cota_esgotada';
    case CreditosVencidos = 'creditos_vencidos';
    case EmAtraso = 'em_atraso';
    case PlanoEncerrado = 'plano_encerrado';
    /** Quem produz esta causa é o cupom (STORY-083); a tela já a conhece. */
    case DescontoAcabouSemCartao = 'desconto_acabou_sem_cartao';
}
