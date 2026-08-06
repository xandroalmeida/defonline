<?php

declare(strict_types=1);

/**
 * Textos de leitura em linguagem simples (`STORY-054`) — **validados pela EBC**.
 *
 * Vazio, e **sem contrapartida em `provisorio/`**. Este é o único item do
 * pacote que não tem default redigido, e a ausência é a decisão, não a
 * consequência de faltar tempo (`PDR-009` §1, CA-6).
 *
 * **Onde o farol cala, a leitura cala.** A leitura é interpretação de farol:
 * é aqui que o produto diria *"sua margem está apertada"*. Sem faixa
 * calibrada não existe farol, e sem farol não há o que interpretar. Escrever
 * texto provisório de leitura seria o mesmo erro do farol não calibrado,
 * vestido de prosa — e mais convincente, porque prosa soa mais autoral que
 * um pontinho colorido.
 *
 * Este arquivo passa a ser preenchido quando as faixas forem calibradas, e
 * não antes. Se um dia ele tiver entrada com faixa ainda `nao_calibrada`,
 * alguém pulou a ordem.
 */
return [
    'version' => '1.0.0', // 2026-08-06 — STORY-052: ausente por decisão, ver PDR-009 §1.

    'por_linha_e_farol' => [
        // Vazio, e permanece vazio enquanto as faixas não forem calibradas.
    ],
];
