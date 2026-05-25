---
story_id: STORY-036
slug: validacao-externa-motor
title: Validação externa do motor (NRF §9.3) — parecer técnico de especialista financeiro
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: validation
target_role: validador-externo
status: draft
owner_agent: especialista-externo
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: M-L
---

# STORY-036 — Validação externa do motor (NRF §9.3)

> **Estória atípica — NÃO é hand-off para o programador.** Não há código a escrever. É gate técnico de qualidade exigido pela spec (NRF §9.3) antes de liberar o produto para uso por usuário real. Executada por **especialista financeiro contratado externamente** (não membro do time tech). **PO coordena a contratação pessoalmente — agente Claude não pode contratar terceiros.**

## Contexto

NRF §9.3 (`requisitos-nao-funcionais-e-juridicos.md`) exige validação externa pré-go-live do motor de cálculo. Sem o parecer aprovado, o produto não pode ser oferecido para uso por usuário real — mesmo em beta fechado.

Validador externo é especialista em finanças corporativas (CFA, auditor sênior, professor de finanças com experiência em PME). PO inicia processo de contratação na Semana 1 com 3 candidatos shortlisted; contrato fechado até Checkpoint 2 (2026-06-05).

> **Risco materializado em 2026-05-25 (abertura da sprint):** PO ainda **não** tem os 3 candidatos shortlisted. Janela = 7 dias úteis até Checkpoint 2. Status atualizado nos riscos da sprint W25.
>
> **Plano B (se até 2026-06-05 não houver contrato assinado):** PO solicita parecer de **conselheiro informal** (ex.: contador parceiro, professor de finanças conhecido) registrado como `approved_with_pending` + follow-up contratual para sprint pós-EPIC-002. Não substitui o §9.3 formalmente, mas destrava o beta fechado com ressalva documentada no handoff.

## O quê

1. **Pacote para o Validador externo:**
   - Acesso à homologação `https://defonline.xandrix.com.br` com conta de teste.
   - Acesso a `defonline-docs/especificacao/V2/especificacao-funcional.md §4.5` (fórmulas), `anexo-F-matriz-recomendacoes-dez2025.md` (matriz), `config/motor/faroes-industria.php` (faixas) e código do motor (`app/Domain/Motor/`).
   - Fixtures canônicas em `tests/fixtures/quiz/industria/` (≥ 140 casos) com input + valor calculado + farol.
   - Briefing: o que validar (fórmulas + faixas + recomendações da matriz × farol × Indústria) e o que **não** está em escopo (Comércio/Serviços, UX/UI, segurança).
2. **Sessão de revisão (≥ 8h):**
   - Validador roda os 140 casos canônicos, confirma fórmulas, faixas e textos da matriz.
   - Levanta inconsistências e propõe correções (ajuste de faixa, troca de texto da matriz, etc.).
3. **Parecer escrito** em `epics/EPIC-002-diagnostico-industria/validation/external-review.md` com veredito:
   - `approved` — pode liberar uso real sem ajustes;
   - `approved_with_pending` — pode liberar com correções não-críticas registradas como follow-up;
   - `blocked` — não pode liberar; lista o que corrigir.
4. **Tratamento de pendências:** se `approved_with_pending`, PO triagem cada item: ou ajusta antes do handoff (entra na sprint), ou registra como follow-up pós-handoff (assumido para sprint pós-EPIC-002).

## Critérios de aceite

- [ ] **CA-0 (shortlist — pré-condição):** PO tem **3 candidatos shortlisted** até **2026-05-29 (Checkpoint 1)**. Sem shortlist nesta data, abre PDR de mitigação e ativa Plano B já no Checkpoint 1 (não no Checkpoint 2).
- [ ] **CA-1 (contrato):** Validador externo contratado, contrato assinado até 2026-06-05. Se falhar → Plano B do Contexto ativado.
- [ ] **CA-2 (pacote entregue):** Validador recebe acesso + briefing + fixtures + código motor + matriz até 2026-06-19 (Checkpoint 4).
- [ ] **CA-3 (revisão concluída):** Validador entrega parecer escrito até 2026-07-01.
- [ ] **CA-4 (veredito registrado):** `validation/external-review.md` existe com veredito e justificativas.
- [ ] **CA-5 (PO triagem):** se `approved_with_pending`, PO classifica cada item em "ajustar antes do handoff" / "follow-up pós-handoff" + cria backlog.
- [ ] **CA-6 (gate):** STORY-037 só promove o épico a `done` se esta estória for `approved` ou `approved_with_pending` com follow-ups documentados como não-críticos.

## Fora de escopo

- Validação UX/UI (cobertura interna).
- Validação de segurança/LGPD (cobertura interna).
- Validação dos setores Comércio/Serviços — onda 2.
- Validação do hotsite — EPIC-009.

## Dependências

- **Bloqueada por:** STORY-030 (motor completo dos 14 indicadores), STORY-032 (matriz integrada).
- **Bloqueia:** STORY-037 (validação final + handoff).

## Decisões já tomadas

- Especialista financeiro externo (não time tech).
- Parecer escrito em arquivo versionado, com veredito formal.
- Follow-ups pós-handoff aceitáveis para itens não-críticos.

## DoD

- CA-1 a CA-6 atendidos.
- Parecer commitado em `validation/external-review.md`.
- PO confirma com Validador a publicação do parecer.

## Protocolo do agente

Esta estória **não tem agente Claude executando** — é coordenação humana (PO contrata + Validador entrega). O fluxo de status updates no `index.json` é manual. Agente Claude **pode auxiliar** o PO em: (a) redigir briefing técnico para o Validador, (b) consolidar fixtures, (c) revisar o parecer recebido contra os 140 casos canônicos. **Não pode**: contratar, assinar contrato, ou emitir o parecer.

## Notas do agente

*(N/A — estória conduzida pelo PO.)*
