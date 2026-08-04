---
story_id: STORY-057
slug: validacao-final-drm
title: "Validação final do EPIC-010"
epic_id: EPIC-010
type: validation
target_role: validador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: L
---

# STORY-057 — Validação final do EPIC-010

> **Para o agente que vai executar:** você é o **Validador**. Execute o checklist em ordem, registre `pass | fail | n/a` com evidência primária para cada item, e produza o relatório. **Não conserte nada** — encontrou falha, registra e devolve ao PO.

## Contexto

Fecha o EPIC-010. O épico só é dado por concluído com relatório `approved`.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Checklist: `epics/EPIC-010-*/validation/checklist.md`
- Relatório a produzir: `epics/EPIC-010-*/validation/report.md`

## O quê

Executar a bateria completa de validação do módulo DRM e emitir veredito.

## Critérios de aceite

- [ ] **CA-1:** todos os itens do `validation/checklist.md` executados, com status e evidência primária. Evidência é log, screenshot, saída de pipeline ou consulta — não é afirmação.
- [ ] **CA-2:** os **sete itens do entregável visível** do `epic.md` demonstrados em homologação, incluindo pelo menos um lançamento real em cada setor.
- [ ] **CA-3:** conferência independente da aritmética — para ao menos um lançamento por setor, o valor exibido na tela bate com o cálculo manual a partir de `design/estrutura-drm.md` §1. **Divergência aqui é falha bloqueante.**
- [ ] **CA-4:** verificação de que as três métricas do épico são efetivamente calculáveis a partir dos eventos emitidos.
- [ ] **CA-5:** relatório produzido conforme `defonline-docs/skills/validador/templates/validation-report.md`, com veredito explícito.
- [ ] **CA-6:** limitações de acesso do validador declaradas onde existirem, em vez de convertidas em falha ou em aprovação silenciosa.

## Fora de escopo

- Corrigir defeitos encontrados.
- Reabrir decisões de produto ou de arquitetura.
- Avaliar o mérito comercial do módulo — isso é do PO, depois dos dados.

## Dependências

- **Bloqueada por:** todas as demais estórias do épico.

## Definição de Pronto

- [ ] CA-1 a CA-6 atendidos.
- [ ] `validation/report.md` publicado com veredito.
- [ ] `index.json` atualizado.
- [ ] PO notificado.

## Protocolo do agente

Siga `defonline-docs/skills/validador/SKILL.md` e `references/evidence-discipline.md`.

## Notas do agente

### Evidências coletadas
### Falhas encontradas
### Limitações de acesso
### Veredito
