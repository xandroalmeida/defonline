<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-092 CA-4 — a assinatura em atraso (a renovação não foi cobrada) mantém análises e Raio X durante as
 * retentativas; o que o Usuário vê na Home é um aviso, sem bloqueio, com a ação de atualizar o cartão.
 *
 * @phpstan-type Aviso array{severidade: 'info'|'bloqueio', texto: string, acao: string, rotulo: string}
 */
final class AvisoDeAtraso
{
    /** @return Aviso|null */
    public static function para(AssinaturaVista $assinatura): ?array
    {
        if ($assinatura->status !== StatusAssinatura::EmAtraso) {
            return null;
        }

        return [
            'severidade' => 'info',
            'texto' => 'Sua assinatura está em atraso: não conseguimos cobrar a renovação. Você pode continuar usando enquanto tentamos de novo; atualize o cartão para evitar que o plano seja encerrado.',
            'acao' => 'meu-plano',
            'rotulo' => 'Atualizar cartão',
        ];
    }
}
