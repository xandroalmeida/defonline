<?php

declare(strict_types=1);

namespace App\Domain\Motor\Farois;

use App\Domain\Motor\Farol;

/**
 * Classificador de farol — setor Indústria (espec V2.5 §4.5; Anexo E).
 *
 * Lê as faixas configuradas em `config/motor/faroes-industria.php` e aplica:
 *
 *   - **tipo = "maior_melhor"** → verde = `> verde.valor`; amarelo = `(amarelo.min, amarelo.max]`;
 *     vermelho = `<= vermelho.valor`.
 *   - **tipo = "menor_melhor"** → verde = `<= verde.valor`; amarelo = `(amarelo.min, amarelo.max]`;
 *     vermelho = `> vermelho.valor`.
 *
 * Valores na fronteira: o intervalo amarelo é **aberto à esquerda, fechado à direita**
 * (`(min, max]`), consistente com a planilha original ("20.01–25%" = (20, 25]). Isso
 * casa com o texto do Anexo E ("Margem Bruta > 25% verde, 20.01–25 amarelo, ≤ 20 vermelho").
 *
 * Indicadores não cobertos por este classificador:
 *   - **NCG absoluto** — sempre `Farol::NENHUM` (informativo). Classificação semântica
 *     vive em {@see App\Domain\Motor\Indicadores\NcgAbsoluto}.
 *   - **PME, Inadimplência, etc.** — adicionados na STORY-030 (motor V2).
 */
final class FarolIndustria
{
    /**
     * Devolve o farol correspondente a `$valor` para o indicador `$chave`.
     *
     * Pré-condição: `$chave` existe em `config('motor.faroes-industria')`.
     * Se o valor é `null`, devolve {@see Farol::NENHUM} — útil quando o
     * indicador chama o classificador sem checar `null` antes.
     *
     * @throws \InvalidArgumentException se `$chave` não tem faixa configurada.
     */
    public static function classificar(string $chave, float|int|null $valor): string
    {
        if ($valor === null) {
            return Farol::NENHUM;
        }

        $config = config('motor.faroes-industria.'.$chave);

        if (! is_array($config)) {
            throw new \InvalidArgumentException(
                "Faixa de farol não configurada para indicador '{$chave}' no setor Indústria. ".
                'Verifique config/motor/faroes-industria.php.',
            );
        }

        return match ($config['tipo']) {
            'maior_melhor' => self::aplicarMaiorMelhor((float) $valor, $config),
            'menor_melhor' => self::aplicarMenorMelhor((float) $valor, $config),
            default => throw new \InvalidArgumentException(
                "Tipo de farol inválido para '{$chave}': '{$config['tipo']}'. Esperado 'maior_melhor' ou 'menor_melhor'.",
            ),
        };
    }

    /**
     * @param  array{verde: array{op: string, valor: float}, amarelo: array{min: float, max: float}, vermelho: array{op: string, valor: float}, tipo: string}  $config
     */
    private static function aplicarMaiorMelhor(float $valor, array $config): string
    {
        if ($valor > $config['verde']['valor']) {
            return Farol::VERDE;
        }
        if ($valor > $config['amarelo']['min'] && $valor <= $config['amarelo']['max']) {
            return Farol::AMARELO;
        }

        return Farol::VERMELHO;
    }

    /**
     * @param  array{verde: array{op: string, valor: float}, amarelo: array{min: float, max: float}, vermelho: array{op: string, valor: float}, tipo: string}  $config
     */
    private static function aplicarMenorMelhor(float $valor, array $config): string
    {
        if ($valor <= $config['verde']['valor']) {
            return Farol::VERDE;
        }
        if ($valor > $config['amarelo']['min'] && $valor <= $config['amarelo']['max']) {
            return Farol::AMARELO;
        }

        return Farol::VERMELHO;
    }
}
