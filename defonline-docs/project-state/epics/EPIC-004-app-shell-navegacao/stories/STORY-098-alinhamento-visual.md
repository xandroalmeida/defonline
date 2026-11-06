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
- [x] **CA-6:** As demais telas foram conferidas em 1280 e 390 px e corrigidas, em 37 telas e 68 estados.

## Fora de escopo

- Tabelas do backoffice em cards no celular: seguem com rolagem dentro do card, por ser área interna.

## Não conferido

- Retorno da troca de plano e página fake de troca de cartão.
- Erros 403 e 500.
- Lançamento do DRM além do bloco 1.
- Estados de erro, como falha do provedor, só no código.
