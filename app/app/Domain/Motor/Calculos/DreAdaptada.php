<?php

declare(strict_types=1);

namespace App\Domain\Motor\Calculos;

/**
 * DRE Adaptada + Balanço Adaptado — agregados anualizados do `quiz_payload`
 * (espec V2.5 §4.5; Anexos B/C).
 *
 *   - Anualização: Q08/Q09/Q14/Q15/Q16 são multiplicados por 12.
 *   - LB = Vendas − Compras.
 *   - EBITDA = LB − Despesas fixas − Despesas variáveis.
 *   - LOL (Lucro Operacional Líquido) = EBITDA − Despesas financeiras.
 *   - PL = Ativo Total − Passivo Circulante (simplificação validada pela EBC).
 *
 * **Aritmética inteira via `bcmath`** com escala 4 (idempotencia.md §4 —
 * float arithmetic em valor monetário é proibido). Valores expostos como
 * strings bcmath; os indicadores combinam-nas via `bcdiv`/`bcmul` e só convertem
 * para `float` no resultado final exibido em `IndicadorResultado::valor`.
 *
 * **Resiliência a faltantes.** Cada accessor retorna `null` se algum input
 * requerido vier `null` no payload — o indicador chamador interpreta o `null`
 * e dispara o caso extremo correspondente (catálogo IDR-010 §sub-decisão 5).
 *
 * **Pureza.** Sem `now()`, sem leitura de banco, sem Auth. Apenas o payload
 * canonicalizado (IDR-010 §sub-decisão 3 — fontes de não-determinismo proibidas).
 */
final class DreAdaptada
{
    public const ESCALA = 4;

    /**
     * @param  array<string, mixed>  $payload  quiz_payload canonicalizado
     */
    public function __construct(private readonly array $payload) {}

    // ============================================================
    // BALANÇO (Anexo B) — todos os valores em R$, escala 4 bcmath
    // ============================================================

    /** Q02 — disponibilidades (ACF). */
    public function disponibilidades(): ?string
    {
        return self::asBc($this->payload['Q02'] ?? null);
    }

    /** Q03 — contas a receber (clientes). */
    public function clientes(): ?string
    {
        return self::asBc($this->payload['Q03'] ?? null);
    }

    /** Q04 — estoques. */
    public function estoques(): ?string
    {
        return self::asBc($this->payload['Q04'] ?? null);
    }

    /** Q05 — imobilizado (patrimônio). */
    public function imobilizado(): ?string
    {
        return self::asBc($this->payload['Q05'] ?? null);
    }

    /** Q06 — dívidas financeiras (PCF). */
    public function dividasFinanceiras(): ?string
    {
        return self::asBc($this->payload['Q06'] ?? null);
    }

    /** Q07 — fornecedores (PCC). */
    public function fornecedores(): ?string
    {
        return self::asBc($this->payload['Q07'] ?? null);
    }

    /** Ativo Total = Q02 + Q03 + Q04 + Q05. `null` se qualquer componente faltar. */
    public function ativoTotal(): ?string
    {
        return self::soma([$this->disponibilidades(), $this->clientes(), $this->estoques(), $this->imobilizado()]);
    }

    /** Passivo Circulante = Q06 + Q07. */
    public function passivoCirculante(): ?string
    {
        return self::soma([$this->dividasFinanceiras(), $this->fornecedores()]);
    }

    /** Patrimônio Líquido = AT − PC (simplificação Anexo B). */
    public function patrimonioLiquido(): ?string
    {
        $at = $this->ativoTotal();
        $pc = $this->passivoCirculante();
        if ($at === null || $pc === null) {
            return null;
        }

        return bcsub($at, $pc, self::ESCALA);
    }

    // ============================================================
    // DRE (Anexo C) — todos os valores em R$/ano, escala 4 bcmath
    // ============================================================

    /** Vendas anualizadas = Q09 × 12. */
    public function vendasAnuais(): ?string
    {
        return self::anualizar($this->payload['Q09'] ?? null);
    }

    /** Compras anualizadas = Q08 × 12. */
    public function comprasAnuais(): ?string
    {
        return self::anualizar($this->payload['Q08'] ?? null);
    }

    /** Despesas fixas anualizadas = Q14 × 12. */
    public function despesasFixasAnuais(): ?string
    {
        return self::anualizar($this->payload['Q14'] ?? null);
    }

    /** Despesas variáveis anualizadas = Q15 × 12. */
    public function despesasVariaveisAnuais(): ?string
    {
        return self::anualizar($this->payload['Q15'] ?? null);
    }

    /** Despesas financeiras anualizadas = Q16 × 12. */
    public function despesasFinanceirasAnuais(): ?string
    {
        return self::anualizar($this->payload['Q16'] ?? null);
    }

    /** Lucro Bruto = Vendas − Compras. */
    public function lucroBruto(): ?string
    {
        $v = $this->vendasAnuais();
        $c = $this->comprasAnuais();
        if ($v === null || $c === null) {
            return null;
        }

        return bcsub($v, $c, self::ESCALA);
    }

    /** EBITDA = LB − DF − DV. `null` se qualquer parcela for `null`. */
    public function ebitda(): ?string
    {
        $lb = $this->lucroBruto();
        $df = $this->despesasFixasAnuais();
        $dv = $this->despesasVariaveisAnuais();
        if ($lb === null || $df === null || $dv === null) {
            return null;
        }

        return bcsub(bcsub($lb, $df, self::ESCALA), $dv, self::ESCALA);
    }

    /** Lucro Operacional Líquido = EBITDA − Despesas Financeiras. */
    public function lucroOperacionalLiquido(): ?string
    {
        $ebitda = $this->ebitda();
        $df = $this->despesasFinanceirasAnuais();
        if ($ebitda === null || $df === null) {
            return null;
        }

        return bcsub($ebitda, $df, self::ESCALA);
    }

    // ============================================================
    // Helpers internos
    // ============================================================

    /**
     * Converte um valor escalar (int/float/string numérica) para string bcmath
     * com escala canônica. `null` permanece `null`.
     */
    private static function asBc(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        return bcadd('0', (string) $value, self::ESCALA);
    }

    /** $valor × 12, em escala canônica. */
    private static function anualizar(mixed $valor): ?string
    {
        $bc = self::asBc($valor);
        if ($bc === null) {
            return null;
        }

        return bcmul($bc, '12', self::ESCALA);
    }

    /**
     * Soma uma lista de strings bcmath; retorna `null` se qualquer parcela for `null`.
     *
     * @param  array<int, ?string>  $parcelas
     */
    private static function soma(array $parcelas): ?string
    {
        $acc = '0';
        foreach ($parcelas as $p) {
            if ($p === null) {
                return null;
            }
            $acc = bcadd($acc, $p, self::ESCALA);
        }

        return $acc;
    }
}
