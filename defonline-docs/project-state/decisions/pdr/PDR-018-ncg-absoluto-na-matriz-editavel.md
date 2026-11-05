---
pdr_id: PDR-018
slug: ncg-absoluto-na-matriz-editavel
title: Recomendações do NCG absoluto editáveis, por sinal e segmento
status: accepted
decided_at: 2026-11-05
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-011]
related_adrs: []
related_idrs: [IDR-010]
---

# PDR-018 — Recomendações do NCG absoluto editáveis

## Contexto

O PO pediu, na tela de Recomendações, campos para o indicador informativo NCG: um texto para NCG positivo e outro para negativo, nos 3 segmentos. O PDR-016 §7 deixava o NCG absoluto de fora, com três mensagens fixas no código. Campos que não chegassem ao relatório seriam a armadilha que a STORY-060 já havia removido.

## Decisão

1. A tabela `recomendacoes` ganha 6 textos do `ncg_absoluto` e passa a ter 123. A coluna `farol` guarda `positiva` ou `negativa` nessas linhas. O NCG segue sem farol no relatório.
2. O relatório passa a exibir esses textos. Eles substituem as três faixas de mensagem da espec §4.5 e da STORY-060: NCG positivo tem um único texto, seja qual for a proporção sobre as vendas.
3. NCG igual a zero conta como negativo, como já era.
4. O texto inicial vem do Anexo F §F.13, com o positivo de Indústria na redação enxugada pelo autor (STORY-042). As mensagens antigas não servem de origem porque passam do limite de 300 caracteres.
5. A aba passa a se chamar "Desp.Fin./Ebitda". O nome completo do indicador segue igual, na tela e no relatório.

## Consequências

- `motor_version` sobe para 2.0.2 e os golden hashes são reemitidos. Os diagnósticos já emitidos mantêm a mensagem da época (IDR-010).
- A orientação sobre dependência do crédito do fornecedor, pedida na revisão do PO de 06/08/2026, não está no texto de origem do NCG negativo. Se ela deve permanecer, o PO a inclui pela tela.
- Revê o PDR-016 §1 e §7, o PDR-006 §2 e a espec §4.5.
