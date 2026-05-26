<?php

declare(strict_types=1);

/**
 * Versão de release do questionário de diagnóstico (ADR-004 §Decisão 2.2).
 *
 * Gravada em `evento_produto.quiz_iniciado.propriedades.quiz_versao` para
 * segmentar o funil por versão do questionário. Distinta de:
 *   - `motor.version` / `motor.matrix_version` (versionamento do motor de cálculo);
 *   - os `version` em `quiz/help-industria.php` e `quiz/validacoes-cruzadas.php`
 *     (versões de conteúdo por arquivo).
 *
 * Formato `AAAA.N`. Suba ao publicar uma revisão do conjunto/ordem de campos.
 */
return '2026.1';
