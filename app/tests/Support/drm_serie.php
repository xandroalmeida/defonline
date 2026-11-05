<?php

declare(strict_types=1);

use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;

/*
 * STORY-051 — meses do DRM gravados pela factory a partir de um lançamento real (o snapshot é o do motor).
 * Compartilhado pelos testes da série, dos comparativos da demonstração e dos alertas A4/A6.
 */

/**
 * O lançamento (tudo agregado, salvo se `$detalhado`) de um mês. Receita 10.000,00, impostos 1.000,00,
 * retirada 1.000,00, financeiras 100,00, vendas 1.000,00 e administrativas 2.000,00: o lucro é
 * 4.900,00 − `$custo`. `$extra` troca ou acrescenta campos (D21 etc.).
 *
 * @param  array<string, string>  $extra
 */
function serieLancamento(string $custo = '3.900,00', array $extra = [], bool $detalhado = false): Lancamento
{
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Agregado);
    if ($detalhado) {
        $modos['administrativas'] = Modo::Detalhado;
    }
    $texto = ['D01' => '10.000,00', 'D02' => '1.000,00', 'D03' => $custo, 'D08' => '1.000,00',
        'D15' => '2.000,00', 'D37' => '100,00', 'D40' => '1.000,00', ...$extra];
    if ($detalhado) {
        unset($texto['D15']);
    }

    return Lancamento::deTexto(Setor::Comercio, $modos, $texto);
}

function serieMes(EmpresaAnalisada $empresa, string $competencia, string $custo = '3.900,00', array $extra = [], bool $detalhado = false, int $versao = 1): DrmLancamento
{
    return DrmLancamento::factory()->create([
        'empresa_analisada_id' => $empresa->id,
        'competencia' => $competencia.'-01',
        'versao' => $versao,
        'lancamento_payload' => serieLancamento($custo, $extra, $detalhado)->payload(),
        'gerado_em' => '2026-11-01 10:00:00+00',
    ]);
}
