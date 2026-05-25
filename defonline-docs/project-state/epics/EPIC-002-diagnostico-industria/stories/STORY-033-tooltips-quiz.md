---
story_id: STORY-033
slug: tooltips-quiz
title: Tooltips/box de explicação por indicador no quiz (§6.8)
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: draft
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: S
---

# STORY-033 — Tooltips no quiz

> Adiciona explicação curta por campo no quiz para reduzir erro de entrada. Spec §6.8 + Anexo A §A.6.

## Contexto

A epic.md inclui como entregável: *"Tooltip/box de explicação por indicador no quiz para reduzir erro de entrada (espec §6.8 e Anexo A §A.6)"*. Roberto, sem contador ao lado, pode confundir "PMR" (recebimento) com "PMC" (compras). Tooltip resolve.

## O quê

1. **Conteúdo:** 23 textos curtos (~50 palavras cada), um por campo do quiz. PO consolida texto na semana 1; programador integra na semana 3.
2. **Componente `<x-help>`** acoplado ao label do campo, mostra ícone de interrogação. Em desktop: hover ou click abre tooltip flutuante. Em mobile: click abre bottom-sheet ou expande inline.
3. **Acessibilidade:** `aria-describedby` ligando label ao texto. Tooltip alcançável por teclado (Tab + Enter).
4. **Textos em config externa** (`config/quiz/help-industria.php`) — facilita ajuste sem refactor.

## Critérios de aceite

- [ ] **CA-1:** Todos os 23 campos do quiz têm tooltip funcional.
- [ ] **CA-2:** Texto pode ser editado em config sem mudar código.
- [ ] **CA-3:** Mobile: bottom-sheet ou expand inline (escolha do programador).
- [ ] **CA-4:** Desktop: tooltip ou popover flutuante.
- [ ] **CA-5 (acessibilidade):** `aria-describedby`, Tab/Enter funcionam, contraste AA.
- [ ] **CA-6 (testes):** Pest feature — view contém os textos esperados. Dusk: hover/click revela tooltip.
- [ ] **CA-7:** Textos consolidados pelo PO entregues antes do Dia 1 da Semana 3.

## Fora de escopo

- Vídeos explicativos — roadmap.
- Calculadora inline para campos derivados — roadmap.
- Tooltip também no relatório — fora desta estória (glossário já cumpre).

## Dependências

- **Bloqueada por:** STORY-027 (campos existem para receber tooltip), PO entregar textos.
- **Bloqueia:** nada (parte da V3).

## Decisões já tomadas

- Conteúdo em config externa, não hardcoded.
- Indústria-only.
- Spec V2 §6.8 + Anexo A §A.6 são fontes da verdade.

## DoD

CA-1 a CA-7 + tag `rc.W25S3.2`. `index.json` atualizado.

## Protocolo do agente

Padrão.

## Notas do agente

*(A preencher.)*
