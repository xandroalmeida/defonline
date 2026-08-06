<?php

declare(strict_types=1);

/**
 * Mensagens de bloqueio e alerta do DRM — **validadas pela EBC**.
 *
 * Vazio pelo mesmo motivo de `help.php`: a EBC ainda não revisou. A diferença
 * é que aqui a ausência **não** chega ao usuário — os textos de
 * `provisorio/alertas.php` estão em uso desde o dia um, e é a única exceção
 * declarada no `PDR-009` §1.
 *
 * O motivo da exceção: os demais itens do pacote são conteúdo **explicativo**,
 * e explicação pode faltar. Bloqueio é **função**. Uma tela que recusa o
 * avanço sem dizer por quê não é uma lacuna editorial — é um defeito.
 *
 * Promoção: mover a linha para cá, subir `version` e `conteudo_version`.
 */
return [
    'version' => '1.0.0', // 2026-08-06 — STORY-052: nenhuma entrada validada ainda.

    'bloqueios' => [
        // Vazio. Ver `provisorio/alertas.php`.
    ],

    'alertas' => [
        // Vazio. Ver `provisorio/alertas.php`.
    ],
];
