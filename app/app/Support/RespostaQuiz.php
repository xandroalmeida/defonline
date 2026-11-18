<?php

declare(strict_types=1);

namespace App\Support;

/**
 * STORY-105 — uma resposta do `quiz_payload` (formato canônico gravado no diagnóstico) como texto
 * para a equipe. CPF de sócio sai mascarado: a tela de respostas mostra dado financeiro, não
 * documento de terceiros.
 */
final class RespostaQuiz
{
    public static function formatar(string $tipo, mixed $valor): string
    {
        if ($valor === null || $valor === '' || $valor === []) {
            return '—';
        }

        return match ($tipo) {
            'moeda' => is_numeric($valor) ? 'R$ '.number_format((float) $valor, 2, ',', '.') : (string) $valor,
            'dias' => is_numeric($valor) ? ((int) $valor).' dias' : (string) $valor,
            'percentual' => is_numeric($valor) ? number_format((float) $valor, 2, ',', '.').'%' : (string) $valor,
            'simnao' => $valor === true || $valor === 'sim' ? 'Sim' : 'Não',
            'setor' => ucfirst((string) $valor),
            'socios' => self::socios($valor),
            default => is_scalar($valor) ? (string) $valor : '—',
        };
    }

    private static function socios(mixed $valor): string
    {
        if (! is_array($valor)) {
            return '—';
        }

        $linhas = [];
        foreach ($valor as $socio) {
            if (! is_array($socio)) {
                continue;
            }
            $cpf = preg_replace('/\D+/', '', (string) ($socio['cpf'] ?? '')) ?? '';
            $mascarado = strlen($cpf) === 11 ? '***.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-**' : '—';
            $linhas[] = trim((string) ($socio['nome'] ?? '')).' ('.$mascarado.')';
        }

        return $linhas === [] ? '—' : implode('; ', $linhas);
    }
}
