<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use RuntimeException;

/** STORY-082 — a troca de plano não pode ser feita. `motivo` é estável para a tela escolher a mensagem. */
final class TrocaNegada extends RuntimeException
{
    public const ADMIN_PERSONIFICANDO = 'admin_personificando';

    public const SEM_ASSINATURA = 'sem_assinatura';

    /** Spec: "Atualize o cartão antes de trocar de plano." */
    public const EM_ATRASO = 'em_atraso';

    /** Renovação cancelada: para trocar, reative primeiro. */
    public const CANCELADA = 'cancelada';

    /** CA-5: só planos ativos são destino. */
    public const PLANO_INDISPONIVEL = 'plano_indisponivel';

    public const IGUAL = 'igual';

    /** O Pix existe só no ciclo anual. */
    public const PIX_SO_ANUAL = 'pix_so_anual';

    public const MUDOU = 'mudou';

    private function __construct(public readonly string $motivo)
    {
        parent::__construct("Troca negada: {$motivo}.");
    }

    public static function porque(string $motivo): self
    {
        return new self($motivo);
    }
}
