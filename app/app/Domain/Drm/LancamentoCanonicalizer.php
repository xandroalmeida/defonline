<?php

declare(strict_types=1);

namespace App\Domain\Drm;

use RuntimeException;

/**
 * Canonicalização do `lancamento_payload` e `payload_hash` (ADR-007, pergunta 3).
 *
 * Aplica as cinco regras do IDR-010: chaves ordenadas recursivamente; decimais como
 * string de casas fixas; string vazia vira `null`; booleanos literais; UTF-8 NFC.
 *
 * **Não reaproveita o `QuizPayloadCanonicalizer` do Diagnóstico, e isso é a decisão
 * (IDR-016).** A restrição do ADR-007 é que o DRM jamais consiga alterar
 * `diagnosticos.payload_hash`. Com duas classes independentes ela vale por
 * construção — nenhuma linha daqui pode mexer no hash do Diagnóstico — e o custo
 * é ~40 linhas duplicadas, contra um canonicalizador compartilhado que faria todo
 * ajuste do DRM depender dos golden hashes do Diagnóstico. Um teste arquitetural
 * (`LancamentoCanonicalizerArchTest`) veta qualquer referência ao motor do Diagnóstico.
 *
 * `alertas_aceitos` mora em coluna própria e nunca entra no payload, logo nunca no hash.
 */
final class LancamentoCanonicalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function canonicalizar(array $payload): array
    {
        /** @var array<string, mixed> */
        return self::normalizar($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function json(array $payload): string
    {
        $json = json_encode(
            self::canonicalizar($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );

        if ($json === false) {
            throw new RuntimeException('Falha ao serializar lancamento_payload: '.json_last_error_msg());
        }

        return $json;
    }

    /**
     * SHA-256 hexadecimal do payload canônico.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function hash(array $payload): string
    {
        return hash('sha256', self::json($payload));
    }

    private static function normalizar(mixed $valor): mixed
    {
        if (is_string($valor)) {
            if (trim($valor) === '') {
                return null;
            }

            return \Normalizer::normalize($valor, \Normalizer::FORM_C) ?: $valor;
        }

        if (is_int($valor) || is_float($valor)) {
            return number_format((float) $valor, 2, '.', '');
        }

        if (! is_array($valor)) {
            return $valor;
        }

        if (array_is_list($valor)) {
            return array_map(self::normalizar(...), $valor);
        }

        ksort($valor, SORT_STRING);

        return array_map(self::normalizar(...), $valor);
    }
}
