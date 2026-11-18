<?php

declare(strict_types=1);

namespace App\Observabilidade;

/**
 * STORY-105 — onde o cliente trava no quiz. Um evento `quiz_travamento` por campo com erro, por
 * aviso de consistência mostrado e por bloqueio de consistência, a cada tentativa de avançar ou de
 * concluir. Sai por {@see EventLogger}: só o id do campo ou da regra, o bloco e o id do rascunho,
 * nunca o valor digitado.
 *
 * O relatório do backoffice soma estes eventos por campo e por regra.
 */
final class EventosQuiz
{
    public const TRAVAMENTO = 'quiz_travamento';

    /** Erro de validação de um campo (obrigatório, formato, faixa). */
    public const TIPO_CAMPO = 'campo';

    /** Aviso de consistência entre blocos que o cliente pode aceitar (R1–R3). */
    public const TIPO_AVISO = 'aviso';

    /** Bloqueio de consistência que só passa corrigindo o valor (R4). */
    public const TIPO_BLOQUEIO = 'bloqueio';

    /**
     * @param  list<string>  $chaves  ids de campo (Q08, socios) ou de regra (R1…R4)
     */
    public static function travamentos(string $tipo, array $chaves, int $bloco, ?string $quizId, ?string $usuarioId, ?string $empresaId): void
    {
        foreach (array_unique($chaves) as $chave) {
            EventLogger::emit(
                self::TRAVAMENTO,
                ['tipo' => $tipo, 'chave' => $chave, 'bloco' => $bloco, 'quiz_id' => $quizId],
                $usuarioId,
                $empresaId,
            );
        }
    }
}
