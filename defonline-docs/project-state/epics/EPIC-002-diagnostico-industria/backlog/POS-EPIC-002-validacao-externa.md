---
artifact: backlog-item
parent_epic: EPIC-002
type: tech-debt
priority: critico_pre_beta
size: M-L
owner: po (alexandro)
created_at: 2026-05-26
status: open
related_story: STORY-036
related_fnb: F-NB-8 (validation/report.md)
---

# Débito — Validação externa do motor (NRF §9.3)

## O que é

Conduzir a contratação de especialista financeiro externo (CFA, auditor sênior, professor de finanças com experiência em PME) para emitir parecer formal sobre o motor de cálculo do EPIC-002, conforme exigência da NRF §9.3.

## Por que existe (e não está no épico que fechou)

Decisão consciente do PO em 2026-05-26 de adiar a contratação para depois do fechamento técnico do épico — registrado em:
- `stories/STORY-036-validacao-externa-motor.md` §"Decisão do PO em 2026-05-26"
- `validation/report.md` F-NB-8
- `sprints/SPRINT-2026-W25.md` (tabela de mudanças, 2026-05-26)

EPIC-002 fechou como `done_under_review`. **Beta fechado não pode rodar** com Roberto real até este débito ser quitado com veredito `approved` ou `approved_with_pending`.

## Critério de pronto

1. Validador externo contratado (contrato assinado).
2. Pacote técnico entregue: acesso à homol + briefing + 140+ fixtures + código do motor + Anexo F.
3. Parecer escrito em `validation/external-review.md` com veredito formal.
4. Se `approved_with_pending`: triagem pelo PO (ajustar antes do beta vs follow-up pós-beta).
5. STORY-036 promovida a `done` no `index.json`.
6. EPIC-002 promovido de `done_under_review` para `done`.
7. Comunicação ao stakeholder com confirmação de que o beta pode rodar.

## Janela e responsável

- **Janela target:** abrir trabalho em até 30 dias após o fechamento do EPIC-002 (ou seja, até **2026-06-25**). Sem este prazo, o beta fica parado.
- **Responsável:** Alexandro (PO) — coordena contratação. Agente Claude pode auxiliar em (a) redigir briefing técnico, (b) consolidar fixtures, (c) revisar parecer contra os 140 casos canônicos.

## Alternativa registrada (Plano B)

Se a contratação formal não fechar até 2026-06-25, ativar **Plano B documentado na STORY-036**: parecer de conselheiro informal (contador parceiro, professor amigo) com **ressalva no handoff** (não substitui §9.3 formalmente, mas destrava beta com restrição). Trade-off explícito: aceitar risco regulatório por prazo de produto.

## Custo estimado

- Tempo: 3–5 semanas (contratação + revisão + parecer).
- Financeiro: a estimar (3 candidatos shortlistados pelo PO antes da contratação).
- Bloqueio de beta enquanto pendente: até esta estória virar `done`.

## Referências

- NRF `requisitos-nao-funcionais-e-juridicos.md` §9.3
- `stories/STORY-036-validacao-externa-motor.md`
- `validation/report.md` §G-3 / F-NB-8
