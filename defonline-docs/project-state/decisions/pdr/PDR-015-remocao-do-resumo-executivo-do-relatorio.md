---
pdr_id: PDR-015
slug: remocao-do-resumo-executivo-do-relatorio
title: Retirada do Resumo Executivo do relatório, na tela e no PDF
status: accepted
decided_at: 2026-11-04
decided_by: PO (Alexandro / Claude), a pedido da EB Parcerias
supersedes: null
superseded_by: null
related_epics: [EPIC-002]
related_adrs: []
related_idrs: [IDR-010]
---

# PDR-015 — Retirada do Resumo Executivo do relatório

## Contexto

A EB Parcerias (Edvan) revisou o relatório da Drogaria Martins e pediu a exclusão do Resumo Executivo. O DEF já é sucinto, com poucos indicadores calculados sobre um balanço perguntado, não contábil. E não há métrica clara para o status geral: dos 13 indicadores, 11 foram calculados, com 7 verdes, 2 amarelos e 2 vermelhos, e o resumo saiu amarelo. Eleger 2 ou 3 indicadores para definir o status também não tem critério óbvio.

Confirmado com a EB em 2026-11-04: vale para todos os casos, na tela e no PDF.

## Decisão

1. O relatório deixa de exibir o Resumo Executivo na tela e no PDF, em qualquer caso, inclusive a mensagem de fallback.
2. A capa do PDF também deixa de exibir o veredito, que vinha do mesmo resumo.
3. Os destaques clicáveis da STORY-040 saem junto, com o scroll e o realce que os atendiam.
4. A regra vale também para diagnósticos já emitidos: o relatório é renderizado na hora.
5. O motor continua calculando e gravando `resumo_executivo`. A mudança é só de apresentação, sem subir a versão do motor (IDR-010) e sem migração.

## Consequências

- O relatório passa a abrir direto na tabela de indicadores.
- Os dados continuam no snapshot, sem exibição. Remover o cálculo e a coluna exigiria nova versão do motor e nova decisão.
- Altera a especificação funcional (§4.7).
