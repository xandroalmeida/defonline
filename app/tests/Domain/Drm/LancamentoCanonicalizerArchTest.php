<?php

declare(strict_types=1);

/*
|-------------------------------------------------------------------------
| STORY-047 / ADR-007 pergunta 3 e 8 — o DRM não pode ser capaz de alterar
| `diagnosticos.payload_hash`. O mecanismo (IDR-016) é não compartilhar código
| de canonicalização: o DRM não importa nada do motor do Diagnóstico.
|-------------------------------------------------------------------------
*/

it('nada em App\\Domain\\Drm referencia o motor ou o canonicalizador do Diagnóstico', function () {
    $arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Domain/Drm')));
    $achados = [];

    foreach ($arquivos as $arquivo) {
        if ($arquivo->getExtension() !== 'php') {
            continue;
        }
        if (preg_match('/App\\\\(Domain\\\\Motor|Actions\\\\CalcularDiagnostico|Models\\\\Diagnostico|Models\\\\QuizRascunho)\b/', (string) file_get_contents($arquivo->getPathname())) === 1) {
            $achados[] = $arquivo->getFilename();
        }
    }

    expect($achados)->toBe([]);
});

it('o canonicalizador do Diagnóstico não conhece o DRM', function () {
    expect((string) file_get_contents(app_path('Domain/Motor/QuizPayloadCanonicalizer.php')))
        ->not->toContain('Domain\\Drm');
});
