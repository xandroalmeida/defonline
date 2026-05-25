---
artifact: story-briefing
target_role: programador
story_id: STORY-032
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
written_by: po (alexandro)
written_at: 2026-05-25
status: ready-for-pickup
---

# Briefing — STORY-032 (Matriz DEZ/2025 Indústria)

## Por que esta estória existe

Hoje, abaixo de cada indicador do relatório, o Roberto lê **"Faixa verde / amarela / vermelha"** — placeholder genérico que não orienta ação. Esta estória substitui esses textos pelas recomendações editoriais da EBC (Anexo F, versão dez/2025, coluna Indústria), entregando o valor real do diagnóstico: orientação acionável por farol.

## Fonte autoritativa

- **Texto:** `defonline-docs/especificacao/V2/anexos/anexo-F-matriz-recomendacoes-dez2025.md` (autoria EB Parcerias). Transcrever literalmente; não reescrever.
- **Schema/persistência:** IDR-010. O snapshot do diagnóstico é imutável; diagnósticos antigos não recalculam quando a matriz evoluir.

## Decisões de produto já tomadas

- **Filtro Indústria** — Comércio e Serviços ficam fora desta sprint (Onda 2).
- **`matrix_version`** continua `"dez-2025"` (não muda nesta estória).

## Decisões abertas — preciso escalar para mim (PO) antes ou durante a execução

1. **NCG abs (F.13).** O Anexo F tem 2 cenários (positiva/negativa), mas o motor hoje devolve 3 mensagens semânticas (folga / moderado / alto). Default: manter as 3 mensagens atuais e mapear as 2 do Anexo F nos extremos, preservando granularidade. Se julgar que vale alinhar 100% ao Anexo F, me chama antes.
2. **Prefixo `(até 20%)`, `(20,01–25%)`, etc.** que aparece em cada texto do Anexo F. Default: **remover** — a faixa já é visível pelo valor + farol exibidos na linha. Se preferir manter, registre e seja consistente.

## Critério de sucesso visível

- Em homologação, ao calcular um diagnóstico novo, cada indicador exibe o texto da matriz EBC (não mais placeholder genérico).
- Diagnósticos persistidos antes desta estória continuam abrindo sem erro, com o texto da época.
- Se faltar texto para algum par (indicador × farol) no momento do cálculo, o relatório não quebra — exibe placeholder visível de "recomendação em revisão" e o time é avisado.

## Fora de escopo

- Comércio e Serviços (Onda 2).
- UI para o PO editar a matriz (roadmap §6 — backoffice).
- Feedback 👍/👎 por recomendação (roadmap §1.4).
- Snapshot retroativo de diagnósticos antigos.

## Hand-off

Decisões de arquitetura, naming, bump de versão, paths e estratégia de teste ficam com o Programador (e Arquiteto, se julgar necessário abrir IDR). Restrições que já existem na base — IDR-010 (snapshot imutável), faroes-industria do motor, view do relatório entregue pela STORY-029, golden hashes da IDR-010 — são limites do espaço de design, não orientações de implementação.

Para dúvidas de domínio, texto ou ambiguidade da spec: me chama.

— PO (Alexandro)
