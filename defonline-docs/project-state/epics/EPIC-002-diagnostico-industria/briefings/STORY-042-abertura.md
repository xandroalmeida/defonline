---
artifact: story-briefing
target_role: programador
story_id: STORY-042
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
written_by: po (Alexandro)
written_at: 2026-06-02
status: ready-for-pickup
---

# Briefing — STORY-042 (Pacote de conteúdo EBC)

## Por que esta estória existe

Devolutiva da EBC com lista coesa de ajustes textuais. Tratá-los em 1 estória evita N PRs minúsculos e mantém o histórico legível.

## Pendências de Edvan

2 itens ambíguos no `.docx`:
1. **Título do Bloco 2** — `.docx` não especificou novo título (só os subtítulos dos itens 3 e 4).
2. **Item do glossário a remover** — `.docx` diz "excluir o item que está antes do Farol/Semáforo", mas não nomeia qual item.

**Política:** se Edvan não responder até **2026-06-04**, manter os textos atuais nesses 2 pontos e registrar como pendência fechada/diferida no PR. Não bloqueia o restante.

## Decisões já tomadas (PO)

- Textos exatos do `.docx` valem como string literal (transcrever, não reescrever).
- Q17 — 2 textos auxiliares ficam **ocultos**. A regra "recebíveis ≥ venda mensal" vai para STORY-043 (validação backend).
- Matriz dez-2025 — diagnósticos legados preservam texto antigo (IDR-010).

## Hand-off

Pure conteúdo + alguns templates Blade. Aproveite a sessão para sweep de strings hard-coded próximas que mereçam mover para config externo (sem inflar escopo — máx +30min).

— PO (Alexandro)
