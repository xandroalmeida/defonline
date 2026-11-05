<?php

declare(strict_types=1);

/**
 * Parâmetros dos eventos analíticos do DRM (STORY-056). Os eventos em si estão em
 * `App\Observabilidade\EventosDrm`; aqui só o que se pode recalibrar sem mexer em código.
 */
return [
    'version' => '1.0.0', // 2026-11-05 — STORY-056.

    /*
     * Voltar a um rascunho só conta como "retomado" depois desta pausa desde o último salvamento.
     * Atualizar a página logo após salvar é a mesma sessão, não uma volta (PDR-003: o mecanismo
     * de rascunho existe para quem interrompe e retorna).
     */
    'retomada_apos_minutos' => 30,
];
