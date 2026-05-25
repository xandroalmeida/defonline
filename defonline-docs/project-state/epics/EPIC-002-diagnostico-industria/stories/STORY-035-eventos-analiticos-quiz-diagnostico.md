---
story_id: STORY-035
slug: eventos-analiticos-quiz-diagnostico
title: Eventos analíticos — `quiz_iniciado` e `diagnostico_concluido`
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: draft
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: S
---

# STORY-035 — Eventos analíticos do EPIC-002

> Instrumenta o produto para o north star (D2 — Ativação). Emite os 2 eventos exigidos pela epic.md.

## Contexto

Epic.md: *"Eventos `quiz_iniciado` e `diagnostico_concluido` emitidos com `empresa_id`, `usuario_id` e timestamps."* Onda (current-wave.md): *"alimentam D2 — Ativação e o próprio north star"*.

O ADR de captura de eventos foi definido no EPIC-000. Esta estória só **consome** esse pipeline.

## O quê

1. **`quiz_iniciado`** — disparado no primeiro avanço de bloco (Bloco 1 → 2) na STORY-027.
2. **`diagnostico_concluido`** — disparado quando `CalcularDiagnostico::execute` retorna sucesso e o registro é persistido.
3. **Payload mínimo:** `{empresa_id, usuario_id, motor_version, matrix_version, timestamp_iso8601, sessao_id (opcional)}`.
4. **Emissão idempotente:** se o mesmo diagnóstico for re-submetido (CA-9 da STORY-027), `diagnostico_concluido` **não** é emitido de novo.
5. **Persistência local + sink externo:** segue o ADR de eventos do EPIC-000 (provavelmente: registro em tabela `eventos` local + dispatch async para sink externo se houver — confirmar no ADR).
6. **Captura confirmável:** PO consegue rodar `SELECT * FROM eventos WHERE tipo = 'diagnostico_concluido'` em homol e ver o registro logo após o smoke E2E da STORY-037.

## Critérios de aceite

- [ ] **CA-1:** evento `quiz_iniciado` emitido no primeiro `Próximo`. Idempotente por sessão (não duplica se Roberto recuar e avançar).
- [ ] **CA-2:** evento `diagnostico_concluido` emitido após persistência do `Diagnostico`. Idempotente por `diagnostico_id`.
- [ ] **CA-3:** payload contém todos os campos obrigatórios.
- [ ] **CA-4:** sink interno (`eventos` local) recebe o registro.
- [ ] **CA-5:** sink externo (se aplicável) recebe assíncrono — falha do sink externo **não** quebra o quiz/relatório (registrar como warning).
- [ ] **CA-6 (testes):** Pest unit ≥ 4 (cada evento × emite + idempotência). Feature: simula fluxo completo e verifica registro no banco.
- [ ] **CA-7 (cobertura):** ≥ 80%.
- [ ] **CA-8 (homol):** PO consegue confirmar registros via SQL após smoke E2E.

## Fora de escopo

- Dashboards de métricas — roadmap §6.
- Eventos de Recorrência (D3 — `diagnostico_visualizado`, `comparativo_aberto`) — EPIC-003.
- Eventos de quiz_abandonado — roadmap (sinal de drop-off).

## Dependências

- **Bloqueada por:** STORY-027 (quiz emite `quiz_iniciado`), STORY-028 (motor persiste e emite `diagnostico_concluido`), ADR de eventos do EPIC-000.
- **Bloqueia:** nada.

## Decisões já tomadas

- Emissão idempotente (não duplica).
- Falha do sink externo não quebra o fluxo do usuário.
- Payload mínimo conforme epic.md.

## DoD

CA-1 a CA-8 + tag `rc.W25S4.2`. `index.json` atualizado.

## Protocolo do agente

Padrão.

## Notas do agente

*(A preencher.)*
