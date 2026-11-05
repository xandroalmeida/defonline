<?php

declare(strict_types=1);

/*
|-------------------------------------------------------------------------
| STORY-052 CA-10 — invariantes estruturais do pacote de conteúdo do DRM.
|
| O teste que importa aqui é o da duplicata. Chave presente no validado e no
| provisório ao mesmo tempo não quebra nada em runtime — o produto exibe o
| validado e segue. É pior que quebrar: alguém edita o provisório, vê o texto
| antigo na tela, e conclui que a promoção não funcionou. Falhar cedo e alto é
| a única defesa.
|-------------------------------------------------------------------------
*/

/**
 * Achata um array aninhado em chaves com ponto, ignorando metadados de arquivo.
 *
 * @param  array<mixed>  $a
 * @return list<string>
 */
function _chavesPlanas(array $a, string $prefixo = ''): array
{
    $out = [];
    foreach ($a as $k => $v) {
        if ($prefixo === '' && $k === 'version') {
            continue; // versão de arquivo existe nos dois por desenho.
        }
        $chave = $prefixo === '' ? (string) $k : "{$prefixo}.{$k}";
        if (is_array($v) && $v !== [] && ! array_is_list($v)) {
            $out = array_merge($out, _chavesPlanas($v, $chave));
        } else {
            $out[] = $chave;
        }
    }

    return $out;
}

dataset('arquivos_com_provisorio', ['help', 'alertas']);

it('CA-10 — nenhuma chave existe no validado e no provisório ao mesmo tempo', function (string $arquivo) {
    $validado = _chavesPlanas((array) config("drm.{$arquivo}", []));
    $provisorio = _chavesPlanas((array) config("drm.provisorio.{$arquivo}", []));

    $duplicadas = array_values(array_intersect($validado, $provisorio));

    expect($duplicadas)->toBe(
        [],
        "Chave em `drm/{$arquivo}.php` E em `drm/provisorio/{$arquivo}.php`: ".implode(', ', $duplicadas).
        '. Promover é MOVER a linha, não copiar — senão o produto exibe o texto validado enquanto alguém acha que corrigiu o provisório.',
    );
})->with('arquivos_com_provisorio');

it('CA-10 — só `help` e `alertas` têm contrapartida provisória', function () {
    // `leitura` não tem de propósito (onde o farol cala, a leitura cala) e
    // `rotulos`/`faixas` nascem validados. Um arquivo novo em `provisorio/`
    // sem decisão de PO por trás é o começo do provisório virando permanente.
    $provisorios = array_keys((array) config('drm.provisorio', []));
    sort($provisorios);

    expect($provisorios)->toBe(['alertas', 'help']);
});

it('CA-10 — o diretório do provisório carrega o README que explica como promover', function () {
    $readme = config_path('drm/provisorio/README.md');

    expect(is_file($readme))->toBeTrue();
    expect((string) file_get_contents($readme))
        ->toContain('Mova a linha')
        ->toContain('conteudo_version');
});

it('CA-10 — `conteudo_version` é separada de `motor_drm_version`', function () {
    // Confundi-las produz recálculo indevido: promover texto nunca pode
    // parecer mudança de motor (IDR-010, ADR-007). São duas chaves, ambas semver.
    // Antes da STORY-048 a separação era provada por os valores diferirem (0.0.0 x 1.0.0);
    // com o motor em 1.0.0 igualar valores é coincidência de numeração, não confusão, então a
    // prova passa a ser a independência real: o motor não lê `conteudo_version`
    // (MotorDrmVersaoTest cobre o resto).
    expect(config('drm.conteudo_version'))->toMatch('/^\d+\.\d+\.\d+$/')
        ->and(config('drm.motor_drm_version'))->toMatch('/^\d+\.\d+\.\d+$/')
        ->and((string) file_get_contents(app_path('Domain/Drm/MotorDrm.php')))->not->toContain('conteudo_version');
});

it('CA-8 — cada arquivo de conteúdo carrega a própria versão', function () {
    foreach (['rotulos', 'help', 'alertas', 'leitura', 'faixas-industria', 'faixas-comercio', 'faixas-servicos'] as $arquivo) {
        expect(config("drm.{$arquivo}.version"))->toBeString()->not->toBe("Sem version em drm/{$arquivo}.php");
    }

    foreach (['help', 'alertas'] as $arquivo) {
        expect(config("drm.provisorio.{$arquivo}.version"))->toBeString();
    }
});

it('CA-5 — os três setores têm o mesmo conjunto de linhas com faixa', function () {
    // A estrutura de linhas é idêntica nos três setores (Anexo K §K.4); o que
    // varia é o valor. Divergência aqui significa linha que ganha farol num
    // setor e não em outro, sem ninguém ter decidido isso.
    $industria = array_keys((array) config('drm.faixas-industria.linhas'));
    $comercio = array_keys((array) config('drm.faixas-comercio.linhas'));
    $servicos = array_keys((array) config('drm.faixas-servicos.linhas'));

    expect($comercio)->toBe($industria)
        ->and($servicos)->toBe($industria);
});

it('CA-11 — "DRM" não vaza para nenhum texto de interface', function () {
    // "DRM" é nome técnico: tabelas, config, rotas, classes, documentação.
    // O usuário lê "Raio X do Negócio" (PDR-009 §3).
    $textos = [];
    foreach (['rotulos', 'help', 'alertas', 'provisorio.help', 'provisorio.alertas'] as $arquivo) {
        $conteudo = (array) config("drm.{$arquivo}", []);
        array_walk_recursive($conteudo, function ($valor) use (&$textos) {
            if (is_string($valor)) {
                $textos[] = $valor;
            }
        });
    }

    $vazamentos = array_values(array_filter($textos, fn (string $t) => str_contains($t, 'DRM')));

    expect($vazamentos)->toBe([], 'Texto de interface citando "DRM": '.implode(' | ', $vazamentos));
});
