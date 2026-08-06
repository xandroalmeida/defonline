<?php

declare(strict_types=1);

/**
 * Tooltips do lançamento do DRM — **validados pela EBC**.
 *
 * Está vazio de propósito, e isso não é pendência esquecida: é o estado
 * correto enquanto a EBC não revisou nenhum texto. Os tooltips que o produto
 * exibe hoje vivem em `provisorio/help.php`, redigidos pela equipe de produto
 * (`PDR-009` §1, CA-4 da `STORY-052`).
 *
 * **Promover uma entrada é mover a linha** de `provisorio/help.php` para cá —
 * não editar código de aplicação, não abrir estória, não fazer deploy de
 * classe nenhuma. O resolvedor procura primeiro aqui e só cai no provisório se
 * não achar (`App\Domain\Drm\ConteudoDrm`).
 *
 * **A mesma chave não pode existir nos dois arquivos.** Duplicata faz o
 * produto exibir o texto antigo achando que exibe o novo; há teste
 * arquitetural que reprova (`ConteudoDrmArchTest`).
 *
 * Ao promover: subir `version` abaixo e `conteudo_version` em `config/drm.php`.
 */
return [
    'version' => '1.0.0', // 2026-08-06 — STORY-052: nenhuma entrada validada ainda.

    'campos' => [
        // Vazio. Ver `provisorio/help.php`.
    ],
];
