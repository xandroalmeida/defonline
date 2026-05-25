<?php

declare(strict_types=1);

namespace App\Domain\Motor;

/**
 * Canonicalização do `quiz_payload` para idempotência (IDR-010 §sub-decisão 3;
 * `design/idempotencia.md`).
 *
 * Regras aplicadas em ordem:
 *
 *   1. Strings vazias e strings só-espaços normalizadas para `null`.
 *   2. Encoding UTF-8 NFC em todas as strings (acentos canônicos).
 *   3. Chaves de array associativo ordenadas lexicograficamente, recursivo.
 *   4. Numéricos passados como string ("123.45") **permanecem string** — o
 *      canonicalizador NÃO força casas decimais (responsabilidade do quiz).
 *      Numéricos PHP `int`/`float` permanecem como tal.
 *
 * O método {@see toJson()} serializa o canonical com flags determinísticas
 * (`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION`),
 * idênticas às usadas no hash de output ({@see Motor}).
 *
 * **Importante.** PHP `json_encode` preserva a ordem de inserção do array.
 * Por isso ordenar chaves ANTES de serializar é a forma correta — qualquer
 * `array_merge` posterior pode destruir a ordem.
 */
final class QuizPayloadCanonicalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function canonicalize(array $payload): array
    {
        return self::normalize($payload);
    }

    /**
     * Serializa o canonical em JSON determinístico — flags casam com o usado
     * para `payload_hash` (IDR-010 §sub-decisão 3) e para golden hashes do output.
     *
     * @param  array<string, mixed>  $canonical
     */
    public static function toJson(array $canonical): string
    {
        $json = json_encode(
            $canonical,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );

        if ($json === false) {
            throw new \RuntimeException('Falha ao serializar quiz_payload canonical: '.json_last_error_msg());
        }

        return $json;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private static function normalize($value)
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            return \Normalizer::isNormalized($value, \Normalizer::FORM_C)
                ? $value
                : (\Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value);
        }

        if (! is_array($value)) {
            return $value;
        }

        // Distingue array associativo de lista — listas preservam ordem original.
        $isList = array_is_list($value);

        if ($isList) {
            $out = [];
            foreach ($value as $item) {
                $out[] = self::normalize($item);
            }

            return $out;
        }

        // Associativo: ordena chaves lexicograficamente, recursivo.
        ksort($value, SORT_STRING);

        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = self::normalize($v);
        }

        return $out;
    }
}
