<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\IndicadorResultado;

/**
 * Contrato dos indicadores do motor (espec V2.5 §4.5; Anexo D).
 *
 * Cada implementação corresponde a uma linha do Anexo D (Margem Bruta, etc.).
 * O motor descobre as implementações via lista explícita em {@see App\Domain\Motor\Motor}
 * — sem auto-discovery, porque a ordem das chaves no array de saída precisa ser
 * bit-exata para idempotência (IDR-010 §sub-decisão 3).
 *
 * **Pureza.** A implementação NUNCA chama `now()`, `Auth::id()`, `Str::random()`,
 * lê do banco ou faz I/O. Só lê do `$payload` canonicalizado e da {@see DreAdaptada}.
 * Excepcionalmente lê de `config('motor.faroes_industria.*')` por meio do classificador
 * (`FarolIndustria`) — config é parte do snapshot lógico do motor.
 */
interface Indicador
{
    /**
     * Chave estável usada em `diagnosticos.indicadores_calculados[<chave>]`.
     *
     * Convenção snake_case alinhada ao Anexo D (ex.: `margem_bruta`, `ncg_vendas`).
     */
    public function chave(): string;

    /**
     * @param  array<string, mixed>  $payload  quiz_payload já canonicalizado
     */
    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado;
}
