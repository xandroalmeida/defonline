<?php

declare(strict_types=1);

/**
 * Versionamento e caminhos do módulo DRM — "Raio X do Negócio" na interface.
 *
 * **Três contadores independentes, e confundi-los produz recálculo indevido**
 * (`PDR-009` §2 item 5, `ADR-007`, `IDR-010`):
 *
 *   - `motor_drm_version` — comportamento de cálculo do DRM (fórmula de linha,
 *     nova linha, correção que muda output). Carimbado em cada lançamento.
 *     **Nada aqui neste arquivo o faz subir.**
 *   - `conteudo_version` — o pacote editorial: rótulos, tooltips, alertas,
 *     faixas. Sobe quando conteúdo muda — inclusive quando uma entrada é
 *     promovida de `provisorio/` para o arquivo validado.
 *   - `version` dentro de cada arquivo de conteúdo — granularidade fina, para
 *     saber qual arquivo mudou sem ler o diff.
 *
 * **Promover conteúdo nunca recalcula lançamento fechado.** O snapshot do
 * lançamento carrega o texto com que nasceu, como o Diagnóstico faz desde o
 * `IDR-010`. Conteúdo novo vale para lançamento novo.
 *
 * Nomenclatura: "DRM" é o nome técnico — tabelas, config, rotas, classes. O
 * usuário lê **"Raio X do Negócio"**, que vive em `drm/rotulos.php` como
 * conteúdo, não como constante de código (`PDR-009` §3).
 */
return [
    // 1.0.0 — STORY-048: motor de cálculo (`App\Domain\Drm\MotorDrm`). Sobe quando
    // fórmula, ordem ou existência de linha muda (MAJOR: estrutura; MINOR: linha nova
    // compatível; PATCH: correção que muda output). Lançamento fechado nunca é recalculado.
    'motor_drm_version' => '1.0.0',

    // 1.0.0 — STORY-052: pacote inicial. Rótulos e nome de interface validados;
    // tooltips e alertas provisórios; faixas de farol todas `nao_calibrada`.
    'conteudo_version' => '1.0.0',

    /*
     * Inventário de lacunas (PDR-009 §2 item 4). Toda chave servida a partir de
     * `provisorio/` é registrada aqui, para que o PO responda "o que ainda não
     * passou pela EBC?" abrindo um arquivo, sem ler código.
     *
     * Vive em `storage/` (gravável em dev e em produção; `defonline-docs/` fica
     * fora do container — STORY-062). `scripts/sincronizar-lacunas.sh` traz o
     * conteúdo para a cópia versionada em `defonline-docs/`. Mesmo padrão de
     * `motor.matriz_lacunas_path`. String vazia desliga o I/O — é o que os
     * testes usam (`.env.testing`).
     */
    'lacunas_path' => env('DRM_LACUNAS_PATH', storage_path('app/private/lacunas/drm-conteudo-lacunas.md')),
];
