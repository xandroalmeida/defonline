---
story_id: STORY-053
slug: spike-exportacao
title: "Spike — motor de exportação: planilha e PDF"
epic_id: EPIC-010
type: spike
target_role: arquiteto
requires_design: false
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: M
checkpoint: CP3
---

# STORY-053 — Spike: motor de exportação

> **Para o agente que vai executar:** você é o **Arquiteto**. Esta decisão extrapola o EPIC-010 — o EPIC-007 (exportação do relatório de Diagnóstico em PDF) depende dela. Decida uma vez, para os dois.

## Contexto

O produto ainda não gera nenhum documento. Não há geração de PDF em lugar nenhum do código, embora a fila `pdf` já exista na configuração do worker, herdada do planejamento original. Esta é uma frente técnica nova, e é a razão de a exportação estar no Checkpoint 3 e não antes.

O DRM é o primeiro caso de uso real: o dono precisa levar o resultado do mês para o contador, para o sócio ou para o gerente do banco. E precisa da planilha — que é o formato em que ele consegue mexer.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `decisions/adr/ADR-002-topologia.md` (worker e filas); `decisions/adr/ADR-005-*` (infra e imagem de runtime); `roadmap/next-wave.md` (EPIC-007); `epics/EPIC-010-*/design/estrutura-drm.md`

## O quê

Produzir um **ADR** que fixe como o produto gera documentos — planilha e PDF — servindo tanto ao DRM quanto ao futuro EPIC-007.

## Perguntas que a decisão precisa responder

1. **Síncrono ou assíncrono?** A fila `pdf` já existe e nenhum job a consome. Vale a pena a assincronia para um documento de uma página, com o custo de UX que ela traz (notificação, polling, estado "gerando")?
2. **Impacto na imagem de runtime.** Geração de PDF costuma exigir dependências pesadas. Qual o efeito no tamanho da imagem, no tempo de build e na superfície de segurança? Há caminho que evite isso?
3. **Fidelidade visual.** O PDF deve reproduzir a tela ou ter layout próprio de documento A4? Como isso interage com o design system e com os tokens?
4. **Planilha.** Formato aberto e editável, com fórmulas preservadas ou valores estáticos? O usuário vai querer mexer — e a planilha original do Key User tinha fórmulas.
5. **Retenção.** O arquivo é gerado sob demanda e descartado, ou armazenado? Se armazenado, onde, por quanto tempo, e qual o impacto de LGPD e de custo?
6. **Escopo da exportação.** Uma competência, um intervalo de competências, ou a série inteira? Recomende com base no custo.
7. **Reaproveitamento pelo EPIC-007.** A decisão precisa servir ao relatório de Diagnóstico, que é bem mais denso — 14 indicadores, semáforo, glossário, recomendações. Valide contra esse caso, não só contra o DRM.

## Critérios de aceite

- [ ] **CA-1:** ADR publicado em `decisions/adr/`, `status: accepted`, respondendo às 7 perguntas com opções, prós, contras e justificativa.
- [ ] **CA-2:** A decisão é validada explicitamente contra os **dois** casos de uso — DRM e relatório de Diagnóstico.
- [ ] **CA-3:** Impacto declarado sobre imagem de runtime, tempo de build, pipeline de CI e infraestrutura de homologação.
- [ ] **CA-4:** Impacto de LGPD declarado, caso a decisão implique armazenar documentos com dados do usuário.
- [ ] **CA-5:** Recomendação explícita de escopo de exportação para o MVP do DRM, alimentando a STORY-055.
- [ ] **CA-6:** Se houver protótipo, vive em branch `spike/STORY-053-*` e não é mergeado.
- [ ] **CA-7:** `index.json` atualizado.
- [ ] **CA-8:** Se a decisão se revelar cara demais para o MVP, **diga isso** e recomende entregar só a planilha, devolvendo o PDF ao EPIC-007. Essa é uma saída legítima e prevista nos riscos do épico.

## Fora de escopo

- Implementar a exportação (STORY-055).
- Decidir o layout do documento — é design, e vem depois desta decisão.

## Dependências

- **Bloqueada por:** nada. Pode rodar em paralelo ao Checkpoint 2.
- **Bloqueia:** STORY-055.

## Decisões já tomadas (não as reabra)

- ADR-002 (topologia: monolito com worker e scheduler); ADR-005 (infra); ADR-006 (CI/CD).

## Definição de Pronto

- [ ] CA-1 a CA-8 atendidos.
- [ ] ADR revisado com o PO antes de `accepted`.
- [ ] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### ADRs criados
