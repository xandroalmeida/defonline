---
artifact: story-briefing
target_role: programador
story_id: STORY-039
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
written_by: po (Alexandro)
written_at: 2026-06-02
status: ready-for-pickup
---

# Briefing — STORY-039 (Inadimplência 2 casas decimais)

## Por que esta estória existe

Bug funcional reportado pelo Edvan: campo Inadimplência rejeita `3,46%` (valor real de balanço) com mensagem "só admite entre 0 e 100". A máscara mostra 2 casas decimais, mas o validador trata o input como inteiro/erra o parsing da vírgula.

## Pista inicial

Procure por algum `between:0,100` ou `numeric|max:100` em FormRequest ou rule Livewire do quiz aplicado ao Q22 — provável que a comparação acontece antes da conversão `,` → `.` ou que o validator recebe a string mascarada `"3,46%"` direto.

## Decisão tomada

- **Range:** `[0,00 ; 100,00]` inclusivo.
- **3+ decimais:** truncar silenciosamente para 2 (default; pode rejeitar se preferir purismo — registre IDR se desviar).
- **Auditar outros campos %** para garantir que a mesma classe de bug não existe em Margem Bruta, Margem EBITDA, Faixa de inadimplência, etc.

## Fora de escopo

- Mudança de máscara (mantém 2 decimais).

## Hand-off

Bug pequeno, mas pode revelar gotcha de locale. Se descobrir um padrão de parsing inconsistente no projeto, **registre IDR** ao invés de só consertar o ponto. Se for trivial e isolado, conserta e segue.

— PO (Alexandro)
