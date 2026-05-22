---
idr_id: IDR-003
slug: pcov-vs-xdebug
title: PCOV (não Xdebug) como driver de cobertura no container web
status: accepted
decided_at: 2026-05-22
decided_by: programador
owner_agent: programador (claude-opus-4-7)
related_story: STORY-010
related_adrs: ["ADR-006"]
related_idrs: []
supersedes: null
superseded_by: null
created_at: 2026-05-22
updated_at: 2026-05-22
---

# IDR-003 — PCOV (não Xdebug) como driver de cobertura no container `web`

## Contexto

A STORY-010 implementa o gate de cobertura prescrito em ADR-006 §Decisão 4 e em `quality-standards.md` §1.1+§2.2 (80% geral, 98% em `app/Domain/**`). A ADR-006 §3.1 NÃO fixa a ferramenta de cobertura — a frase é "Pest --coverage" (deixando aberta a escolha do driver). Pest 4 só consegue medir cobertura se PHP tem um driver de coverage instalado: as opções viáveis são **PCOV** ou **Xdebug**.

A escolha entre os dois afeta:
- Tempo de pre-push (rodado antes de cada `git push`) e tempo do job `test-coverage` do PR.
- Tamanho da imagem `infra/docker/Dockerfile` (build em CI + push para GHCR + pull na VPS).
- Surface de configuração (ambos têm INI próprios) — Xdebug mais; PCOV quase nenhuma.

## Decisão

Habilitar **PCOV** como única extensão de cobertura na imagem `web` (via `pecl install pcov && docker-php-ext-enable pcov` no Dockerfile). **Xdebug NÃO é instalado.**

PCOV permanece habilitado em runtime — mas ele só consome ciclos quando o PHP é invocado em modo de cobertura (Pest `--coverage`). Em `php artisan test` sem flag, em `php artisan serve`, no worker e no scheduler, o overhead observado é desprezível.

## Por quê PCOV

- **Overhead muito menor:** PCOV adiciona ~10-20% no tempo total dos testes (e zero quando não está medindo). Xdebug em modo coverage costuma multiplicar o tempo por **4-5×**. Pre-push hook é rodado antes de cada push e o tempo extra é pago pelo dev a cada vez — escolher Xdebug aqui é puro custo.
- **Footprint mínimo:** PCOV é uma extensão minúscula (~30 KB compilado) e não exige `libxml`, IDE bridge, ou `Xdebug Step Debugger`. A camada do Docker fica menor → push/pull mais rápido (ADR-005 §custo).
- **Suficiente para o propósito:** o gate só precisa do número final de cobertura (Pest CLI + `--min=80` + `--coverage-clover` opcional). PCOV cobre 100% desse caso de uso. Não há requisito atual de step-debug, profiling, ou trace — os usos típicos onde Xdebug brilha.
- **Adoção comunidade:** projetos PHP modernos (Laravel core, Symfony, Pest) recomendam PCOV em CI por padrão. O próprio `shivammathur/setup-php@v2` aceita `coverage: pcov` como primeira sugestão.
- **Reversível:** se em algum momento o time precisar de step-debug, Xdebug pode ser instalado **em paralelo** (PCOV e Xdebug coexistem — basta não habilitar Xdebug por default, ativando via flag de ambiente apenas em sessões de debug). Esta decisão não fecha portas.

## Por quê NÃO Xdebug

- **Custo de tempo:** já descrito.
- **Conflito de modo:** Xdebug pode interferir em outros entry-points do container (web, worker) mesmo desativado, dependendo da config. PCOV é estritamente "off por default na execução padrão" — só liga quando coleta.
- **Footprint:** ~10× o tamanho de PCOV, com várias deps (`libxml`, `protocol negotiator`).

## Consequências

- **Pre-push hook** (`scripts/git-hooks/pre-push.sh`) e **job `test-coverage`** do `pr.yml` rodam `./vendor/bin/pest --coverage --min=80` confiando em PCOV.
- **`shivammathur/setup-php@v2`** no job `test-coverage` foi configurado com `coverage: pcov` (mesmo driver no remote).
- **Verificação CA-1:** `docker compose exec -T web php -m | grep -i pcov` retorna `pcov` ✅ (confirmado em 2026-05-22).
- **Sem impacto runtime:** os processos `web`, `worker` e `scheduler` continuam rodando sem perceber a presença de PCOV (overhead em produção é literalmente zero quando `--coverage` não é solicitado).

## Quando reabrir esta decisão

- Se em algum momento se quiser habilitar step-debug remoto contra o container homol (atualmente sem demanda).
- Se PCOV deixar de ser mantido (atualmente ativo, mantido por `krakjoe`).
- Se o time precisar de **branch coverage** em vez de **line coverage** — PCOV não suporta branch; Xdebug suporta. Quality-standards §1.1 fala em "cobertura ≥80%" sem qualificar — interpretado como **line coverage**, suficiente para o gate.

## Referências

- ADR-006 §3.1 e §Decisão 4 — gate prescrito.
- `quality-standards.md` §1.1 e §2.2 — métricas de qualidade.
- STORY-010 — implementação que motiva esta IDR.
- README §"Cobertura de testes" — como rodar localmente.
