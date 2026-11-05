<?php

declare(strict_types=1);

/**
 * STORY-071 CA-9 / PDR-020 — o catálogo é dado, não código. Nenhum código de aplicação
 * pode comparar com o código de um plano, pacote ou trial específico, nem escolher
 * comportamento por eles: a regra vem dos campos do catálogo (cota, preço, validade...).
 *
 * Fica fora da varredura só a migration que faz a carga inicial.
 */
function _fontesDaAplicacao(): array
{
    $raiz = dirname(__DIR__, 3);
    $arquivos = [];

    foreach (['app', 'routes', 'resources/views', 'config'] as $pasta) {
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$raiz}/{$pasta}", FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $arquivo) {
            if (preg_match('/\.php$/', $arquivo->getFilename()) === 1) {
                $arquivos[$arquivo->getPathname()] = (string) file_get_contents($arquivo->getPathname());
            }
        }
    }

    return $arquivos;
}

/** @return array<string, string> regex => motivo */
function _regrasCatalogo(): array
{
    $codigos = '(basico|pro|trial|mini|plus|max)';

    return [
        '/(===?|!==?)\s*[\'"]'.$codigos.'[\'"]/i' => 'comparação com código de plano ou pacote',
        '/[\'"]'.$codigos.'[\'"]\s*(===?|!==?)/i' => 'comparação com código de plano ou pacote',
        '/\bcase\s+[\'"]'.$codigos.'[\'"]\s*:/i' => 'case com código de plano ou pacote',
        '/in_array\([^;]*\[[^\]]*[\'"]'.$codigos.'[\'"]/i' => 'in_array com código de plano ou pacote',
        '/=>\s*[\'"]'.$codigos.'[\'"]\s*,?\s*\n?\s*\}/i' => 'match devolvendo código',
        '/match\s*\([^)]*->codigo[^)]*\)/i' => 'match sobre ->codigo',
        '/where(Not)?(In)?\(\s*[\'"]codigo[\'"]\s*,\s*\[?\s*[\'"]/' => 'filtro por codigo literal',
        '/->codigo\s*[!=]==?\s*[\'"\$]/' => 'comparação de ->codigo',
        '/\[[\'"]codigo[\'"]\]\s*[!=]==?/' => "comparação de ['codigo']",
    ];
}

it('não compara nem filtra por código de plano ou pacote específico', function () {
    $achados = [];

    foreach (_fontesDaAplicacao() as $caminho => $codigo) {
        foreach (_regrasCatalogo() as $regex => $motivo) {
            // No DRM, `->codigo` é a rubrica da demonstração, não o catálogo. Os literais de
            // plano e pacote continuam vetados lá pelas demais regras.
            if ($motivo === 'comparação de ->codigo' && preg_match('#/(Drm|drm)/#', $caminho) === 1) {
                continue;
            }

            if (preg_match($regex, $codigo, $m) === 1) {
                $achados[] = basename($caminho).": {$motivo} ({$m[0]})";
            }
        }
    }

    expect($achados)->toBe([]);
});

it('o detector reconhece as infrações (teste do teste)', function (string $trecho) {
    $casou = array_filter(array_keys(_regrasCatalogo()), fn (string $r): bool => preg_match($r, $trecho) === 1);

    expect($casou)->not->toBeEmpty();
})->with([
    '$p->codigo === "pro"',
    "if (\$plano->codigo == 'basico')",
    "if ('mini' === \$pacote->codigo)",
    "Plano::where('codigo', 'pro')",
    "Plano::whereIn('codigo', ['pro', 'basico'])",
    "case 'trial':",
    "in_array(\$x, ['pro', 'basico'], true)",
    'match ($plano->codigo) { default => 1 }',
    "\$p['codigo'] === \$outro",
]);

it('não acusa o uso legítimo de campos do catálogo', function () {
    $legitimos = "\$p->cota_mensal >= 5; 'plus' => '<path/>'; \$trial['dias']; Plano::query()->where('codigo', \$codigo)->exists();";

    foreach (array_keys(_regrasCatalogo()) as $regex) {
        expect(preg_match($regex, $legitimos))->toBe(0);
    }
});
