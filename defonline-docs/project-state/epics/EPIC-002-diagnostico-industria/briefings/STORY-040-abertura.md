---
artifact: story-briefing
target_role: programador
story_id: STORY-040
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
written_by: po (Alexandro)
written_at: 2026-06-02
status: ready-for-pickup
---

# Briefing — STORY-040 (Card do Resumo Executivo clicável)

## Por que esta estória existe

Edvan reportou (áudio 2026-05-27) que os cards de destaque do Resumo Executivo truncam o texto e **não são clicáveis** — o usuário esbarra num dead-end. O texto completo aparece logo abaixo, na tabela detalhada, gerando redundância visual percebida. Queremos transformar o card em uma "porta de entrada" para o detalhe.

## Decisão de UX (PO)

- **Scroll-to + highlight de ~1s** na linha alvo da tabela detalhada (não expand inline).
- Por quê: mobile (perfil do Roberto) — expand inline gera scroll duplo e quebra o ritmo do resumo executivo enxuto. Scroll-to economiza altura visual.

## Decisões abertas — me chame se precisar

1. **Token de highlight novo no design system.** Se precisar, registre como `--color-highlight-pulse` (ou equivalente) e adicione em `defonline-docs/especificacao/V2/design-system.md`. Default: tentar usar `--color-accent-soft` se existir.
2. **CSS-only com `:target` vs JS leve.** Default: **CSS-only** se der pra atingir o highlight + smooth. Se precisar JS, mantenha sem dependência externa.

## Pista técnica

- Componente do card: `<x-relatorio.resumo-executivo>` (STORY-031).
- Componente da linha/card alvo: `<x-relatorio.tabela-indicadores>` ou `<x-relatorio.cards-indicadores>` (STORY-029).
- Os destaques têm `codigo` do indicador (ex: `ncg_vendas`) — use `id="indicador-ncg_vendas"` no item da tabela e `href="#indicador-ncg_vendas"` no card.

## Fora de escopo

- Não mexa no algoritmo dos destaques (§4.7.1 STORY-031). É a próxima discussão com Edvan + validador externo.

## Hand-off

A11y é importante aqui: usuário com teclado precisa conseguir tabular para o card, dar Enter e ver o scroll. Auditar com Lighthouse ≥95.

— PO (Alexandro)
