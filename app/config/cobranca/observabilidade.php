<?php

declare(strict_types=1);

/**
 * Parâmetros da observabilidade da cobrança (STORY-086). Os eventos estão em
 * `App\Observabilidade\EventosCobranca`; aqui só o que se recalibra sem mexer em código.
 */
return [
    'version' => '1.0.0', // 2026-11-05 — STORY-086.

    /*
     * Alerta P2 (NRF §6.4): taxa de falha de pagamento acima de `limite` na janela. `minimo` evita
     * alertar com amostra minúscula (1 recusa em 1 tentativa é 100%, mas não é um incidente) — mesma
     * lógica do monitor da consulta de CNPJ.
     */
    'falha_de_pagamento' => [
        'limite' => 0.10,
        'janela_minutos' => 60,
        'minimo_de_tentativas' => 5,
    ],

    /*
     * Janela de conversão do trial (NRF §6.3): o Usuário que ativou o teste converte se contratar um
     * plano em até tantos dias DEPOIS da ativação.
     */
    'conversao_trial_dias' => 30,

    /* Mesma falha (mesmo tipo e mesmos identificadores) não alerta de novo antes disto (ADR-004 §Alertas). */
    'silencio_minutos' => 30,
];
