<?php

declare(strict_types=1);

namespace App\Domain\Drm\Serie;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Lancamento;

/**
 * Um mês LANÇADO da série, lido da versão vigente (ADR-007): é a única fonte dos comparativos.
 *
 * Nada aqui recalcula: `nominais` e `percentuais` são os do snapshot (`demonstracao`) no modo do
 * pró-labore pedido, e `campos` são os D01..D42 do `lancamento_payload` (insumo da regra A4).
 * Valor `null` quer dizer "sem valor naquele mês" (item de grupo agregado, percentual sem
 * faturamento) e **nunca** vira zero em conta alguma.
 */
final readonly class MesDaSerie
{
    /**
     * @param  array<string, int|null>  $nominais  código da linha => centavos
     * @param  array<string, string|null>  $percentuais  código da linha => `12.3456` (4 casas)
     * @param  array<string, int|null>  $campos  D01..D42 => centavos (nulo = não coletado)
     * @param  list<string>  $nivel1  códigos das linhas de nível 1, na ordem do snapshot (a última é o resultado final)
     */
    public function __construct(
        public Competencia $competencia,
        public int $versao,
        public array $nominais,
        public array $percentuais,
        public array $campos,
        public array $nivel1 = [],
    ) {}

    /**
     * @param  array<string, mixed>  $demonstracao  o snapshot, como gravado
     * @param  array<string, mixed>  $payload  `lancamento_payload`
     */
    public static function doSnapshot(Competencia $competencia, int $versao, array $demonstracao, array $payload, string $posicao): self
    {
        $porPosicao = is_array($demonstracao['linhas'] ?? null) ? $demonstracao['linhas'] : [];
        $brutas = $porPosicao[$posicao] ?? ($porPosicao[array_key_first($porPosicao)] ?? []);

        $nominais = [];
        $percentuais = [];
        $nivel1 = [];
        foreach (is_array($brutas) ? $brutas : [] as $bruta) {
            if (! is_array($bruta) || ! is_string($bruta['codigo'] ?? null)) {
                continue;
            }
            if ((int) ($bruta['nivel'] ?? 1) === 1) {
                $nivel1[] = $bruta['codigo'];
            }
            $nominal = $bruta['nominal'] ?? null;
            $percentual = $bruta['percentual'] ?? null;
            $nominais[$bruta['codigo']] = is_string($nominal) && preg_match('/^-?\d+(\.\d+)?$/', $nominal) === 1
                ? Dinheiro::deDecimal($nominal)
                : null;
            $percentuais[$bruta['codigo']] = is_string($percentual) && preg_match('/^-?\d+\.\d{4}$/', $percentual) === 1
                ? $percentual
                : null;
        }

        return new self($competencia, $versao, $nominais, $percentuais, self::camposDoPayload($payload), $nivel1);
    }

    /**
     * Valor nominal da linha neste mês, ou `null` se o mês não a informou (grupo agregado).
     *
     * Linha OMITIDA por estar zerada não é linha não informada: a L7b some do snapshot quando D41 e D42
     * são zero (`estrutura-drm` §3.3) e vale 0 (STORY-095, D-2). Sem isto, os comparativos dela
     * ficavam indisponíveis por "grupo agregado" e o acumulado do ano se perdia.
     */
    public function nominal(string $linha): ?int
    {
        if (array_key_exists($linha, $this->nominais)) {
            return $this->nominais[$linha];
        }

        return $this->omitidaPorZero($linha) ? 0 : null;
    }

    /** Percentual da linha neste mês: o da linha omitida por zero é 0%, se o mês tem faturamento. */
    public function percentual(string $linha): ?string
    {
        if (array_key_exists($linha, $this->percentuais)) {
            return $this->percentuais[$linha];
        }
        $ancora = $this->codigoAncora();

        return $this->omitidaPorZero($linha) && $ancora !== null && ($this->percentuais[$ancora] ?? null) !== null ? '0.0000' : null;
    }

    private function omitidaPorZero(string $linha): bool
    {
        return $linha === 'L7b' && ($this->campos['D41'] ?? null) === 0 && ($this->campos['D42'] ?? null) === 0;
    }

    /** O resultado final: a última linha de nível 1 (DDR-004 item 3), nos dois modos. */
    public function codigoFinal(): ?string
    {
        return $this->nivel1 === [] ? null : $this->nivel1[array_key_last($this->nivel1)];
    }

    /** A âncora de 100%: a primeira linha de nível 1 (a receita). */
    public function codigoAncora(): ?string
    {
        return $this->nivel1[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int|null>
     */
    public static function camposDoPayload(array $payload): array
    {
        $campos = [];
        foreach (Lancamento::todosOsCampos() as $campo) {
            $valor = $payload[$campo] ?? null;
            $campos[$campo] = is_string($valor) && preg_match('/^-?\d+(\.\d+)?$/', $valor) === 1 ? Dinheiro::deDecimal($valor) : null;
        }

        return $campos;
    }
}
