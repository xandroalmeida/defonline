---
story_id: STORY-098
slug: alinhamento-visual
title: "Requisito de alinhamento visual e correção de Meu plano e Minha conta"
epic_id: EPIC-004
sprint_id: null
type: feature
target_role: programador
requires_design: false
status: in_review
owner_agent: claude-opus-5-5
created_at: 2026-11-06
updated_at: 2026-11-06
estimated_session_size: S
---

# STORY-098 — Requisito de alinhamento visual e correção de Meu plano e Minha conta

Origem: pedido do PO em 2026-11-06, ao ver telas entregues com problemas de alinhamento. O requisito é o RNF-UI-02 (`especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §12).

## Critérios de aceite

- [x] **CA-1:** O RNF-UI-02 está publicado e ligado aos padrões de qualidade e aos papéis de designer, programador e validador.
- [x] **CA-2:** Em Meu plano e Minha conta, cards vizinhos têm o mesmo topo e a mesma altura, com o mesmo espaço entre eles.
- [x] **CA-3:** Em Meu plano, os botões dos cards sem plano ficam na mesma altura, e os lotes de crédito ficam na borda esquerda do card.
- [x] **CA-4:** O foco por script no título não desenha contorno em nenhuma tela. O foco por teclado continua visível.
- [x] **CA-5:** `assertAlinhados` reprova cards e ações desalinhados nas duas telas.

## Fora de escopo

- Conferir e corrigir as demais telas do sistema.
