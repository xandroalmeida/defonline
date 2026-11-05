<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** STORY-074 CA-2 e CA-4 — por que o Usuário não pode ativar o trial. O texto vive em `TextoTrial`. */
enum MotivoInelegivelTrial: string
{
    /** Configuração desligada (CA-4): a oferta não existe e a ativação é recusada. */
    case Desativado = 'desativado';

    /** O CPF (ou esta conta) já ativou um trial. */
    case CpfJaUsou = 'cpf_ja_usou';

    /** Já tem assinatura vigente: o trial não faz sentido. */
    case JaTemAssinatura = 'ja_tem_assinatura';
}
