---
story_id: STORY-035
slug: eventos-analiticos-quiz-diagnostico
title: Eventos analíticos — `quiz_iniciado` e `diagnostico_concluido`
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: done
owner_agent: claude-programador
approved_by: Alexandro
approved_at: 2026-05-25
closed_at: 2026-05-25
reconciled_at: 2026-05-26
created_at: 2026-05-25
updated_at: 2026-05-26
estimated_session_size: S
related_adrs: ["ADR-002", "ADR-004"]
---

# STORY-035 — Eventos analíticos do EPIC-002

> Instrumenta o produto para o north star (D2 — Ativação). Emite os 2 eventos exigidos pela epic.md **conforme contrato fixado em ADR-004 §Decisão 2**.

## Contexto

Epic.md cita os eventos `quiz_iniciado` e `diagnostico_concluido`; o **contrato técnico** (payload obrigatório, helper, tabela, política PII) está fixado em **ADR-004 §Decisão 2** ("Eventos de produto") e ADR-002 (request_id UUID v7 cross-process). Esta estória só **consome** esse pipeline — não decide nada novo.

> **Importante (correção 2026-05-25):** a redação original desta estória listava payload `{empresa_id, usuario_id, motor_version, matrix_version, timestamp_iso8601, sessao_id}` e tabela `eventos` com sink externo assíncrono. Ambos divergiam do ADR-004 já aprovado em 2026-05-21 (síncrono dentro da transação, tabela `evento_produto`, propriedades canônicas por evento). A estória foi realinhada ao ADR.

## O quê

1. **`quiz_iniciado`** — disparado no **primeiro POST de resposta de Q01** que muda o status do rascunho de `null → rascunho` (definição literal do ADR-004 §2.2). Implementado no fluxo do quiz da STORY-027.
2. **`diagnostico_concluido`** — disparado no service `diagnostico/CalcularDiagnostico` **após** a transição `rascunho → enviado` + motor calculado + `Diagnostico` persistido (definição literal do ADR-004 §2.2).
3. **Helper canônico:** chamadas exclusivamente via `EventLogger::emit($nome, array $propriedades, ?Usuario $usuario, ?EmpresaAnalisada $empresa)` (ADR-004 §Decisão 2.3). Síncrono dentro da transação do agregado — **sem job assíncrono e sem sink externo** (ADR-004: *"sem fila intermediária; tornar async só se >100 eventos/segundo sustentado"*).
4. **Tabela `evento_produto`** (ADR-004 §2.1 — schema dedicado, append-only). Colunas: `id`, `nome_evento`, `ocorrido_em`, `usuario_id`, `empresa_id`, `propriedades jsonb`, `request_id`. **Não criar tabela nova** — a migration já é responsabilidade de EPIC-000/STORY do ADR-004; verificar que está rodada em homol antes de começar.
5. **Payload obrigatório por evento (ADR-004 §2.2 — copiar exatamente):**
    - **`quiz_iniciado`:** `usuario_id` (obrigatório), `empresa_id` (obrigatório), `propriedades` = `{ quiz_id (uuid interno), quiz_versao (ex.: "2026.1") }`.
    - **`diagnostico_concluido`:** `usuario_id` (obrigatório), `empresa_id` (obrigatório), `propriedades` = `{ quiz_id, diagnostico_id, duracao_preenchimento_seg (int), setor, porte }`.
    - **`request_id`** (UUID v7 do middleware HTTP — ADR-002) é gravado pelo próprio `EventLogger::emit` lendo do contexto da request; **não passar manualmente**.
    - **Sem PII em `propriedades`** (ADR-004 §2.4) — não logar CNPJ, e-mail, telefone, razão social. Apenas IDs internos e enums.
6. **Idempotência:**
    - `quiz_iniciado` — só emite na **primeira** transição `null → rascunho` (não em saves subsequentes). Garantia: gatilho no model `Quiz` quando o status muda para `rascunho`, observer não dispara em re-saves.
    - `diagnostico_concluido` — chave de idempotência = `diagnostico_id`. Re-submissão idempotente do mesmo `payload_hash` (IDR-010 §Sub-decisão 3) retorna o `Diagnostico` existente e **não** re-emite o evento.
7. **Captura confirmável em homol:**
    ```sql
    SELECT nome_evento, ocorrido_em, usuario_id, empresa_id, propriedades, request_id
    FROM evento_produto
    WHERE nome_evento IN ('quiz_iniciado','diagnostico_concluido')
    ORDER BY ocorrido_em DESC LIMIT 10;
    ```

## Critérios de aceite

- [x] **CA-1:** `quiz_iniciado` emitido **uma única vez** na transição `null → rascunho` (Bloco 1 → 2). Re-saves do mesmo rascunho não duplicam.
- [x] **CA-2:** `diagnostico_concluido` emitido **uma única vez** por `diagnostico_id` após `CalcularDiagnostico::execute` retornar sucesso e o registro ser persistido. Re-submissão do mesmo `payload_hash` (idempotência IDR-010) **não** re-emite.
- [x] **CA-3 (payload conforme ADR-004 §2.2):**
    - `quiz_iniciado.propriedades` contém **exatamente** `quiz_id`, `quiz_versao`.
    - `diagnostico_concluido.propriedades` contém **exatamente** `quiz_id`, `diagnostico_id`, `duracao_preenchimento_seg`, `setor`, `porte`.
    - Ambos os eventos gravam `usuario_id`, `empresa_id`, `request_id` (UUID v7) e `ocorrido_em` (timestamptz).
- [x] **CA-4 (helper):** todas as emissões passam por `EventLogger::emit(...)` — busca de `evento_produto::create()` direto no código de aplicação retorna **zero ocorrências fora do `EventLogger`** (assert por teste arquitetural Pest).
- [x] **CA-5 (síncrono na transação):** evento é gravado **na mesma transação** do agregado que o originou (sem job, sem fila). Falha do `INSERT` em `evento_produto` faz **rollback** do quiz/diagnóstico — não há "tolerância" para evento perdido no MVP.
- [x] **CA-6 (sem PII em `propriedades`):** teste arquitetural assegura que `propriedades` JSONB não carrega chaves `cnpj`, `email`, `telefone`, `razao_social`, `nome_completo`, ou qualquer string que case com regex de CNPJ/CPF/e-mail/telefone.
- [x] **CA-7 (testes):** Pest unit ≥ 4 (cada evento × emite + idempotência). Pest feature: fluxo completo do quiz simulado, asserta exatamente 1 linha por evento no banco com payload conforme CA-3. Suíte cobre também o evento que **não** dispara (re-save de rascunho, re-submissão idempotente).
- [x] **CA-8 (cobertura):** ≥ 80% no pacote, ≥ 95% no `EventLogger` (helper hot path).
- [x] **CA-9 (homol):** PO consegue confirmar registros via SQL acima após smoke E2E da STORY-037 — esperado ≥ 1 linha por evento, com `request_id` igual ao da request que originou (rastreável via logs).

## Fora de escopo

- Dashboards de métricas — roadmap §6.
- Eventos de Recorrência (D3 — `diagnostico_visualizado`, `comparativo_aberto`) — EPIC-003.
- Eventos `quiz_abandonado` — roadmap (sinal de drop-off, pós-V1).
- Sink externo (PostHog, Mixpanel) — supersede de ADR-004 quando volume passar de 100 eventos/segundo sustentado.

## Dependências

- **Bloqueada por:**
    - STORY-027 (quiz existe, transição `null → rascunho` é observável) — **`done`** ✓.
    - STORY-028 (motor persiste `Diagnostico`) — **`done`** ✓.
    - **ADR-004 §Decisão 2** (contrato dos eventos) — **`accepted`** em 2026-05-21 ✓.
    - Migration da tabela `evento_produto` rodada em homol — confirmar no Dia 1.
- **Bloqueia:** nada na sprint W25.

## Decisões já tomadas

- **Contrato dos eventos = ADR-004 §2.2** (payload, propriedades, helper, política PII).
- **Síncrono na transação** (ADR-004 §Decisão 2.3) — sem fila, sem job.
- **`EventLogger::emit(...)` é a única porta de entrada** — sem chamadas diretas a `evento_produto::create()`.
- **Idempotência:** `quiz_iniciado` por transição de status, `diagnostico_concluido` por `diagnostico_id`.
- **`request_id`** gravado automaticamente pelo helper (não passar manualmente).

## DoD

CA-1 a CA-8 + tag `rc.W25S4.2`. `index.json` atualizado.

## Protocolo do agente

Padrão.

## Notas do agente

### 2026-05-25 — Implementação (claude-programador, commit `1249195`)

Entregue:
- `quiz_iniciado` emitido no primeiro `INSERT` do `QuizRascunho` (`wasRecentlyCreated`), com `quiz_id` = id do rascunho e `quiz_versao` = `config('quiz.versao')` = `"2026.1"`.
- `diagnostico_concluido` emitido na mesma transação do `Diagnostico`, com `porte` derivado de Q09×12 (LC 123/155 categorizado, sem PII).
- Remove `event QuizIniciado` obsoleto (payload `motor_version/matrix_version` divergia do ADR-004).
- 7 arquivos / +346 linhas / +6 testes.

Testes (verdes):
- Schema (CA-3), idempotência por `diagnostico_id` (CA-2), não-reemissão (CA-1), rollback (CA-5), funil ponta-a-ponta (CA-7), arch `EventoProduto::create` só no `EventLogger` (CA-4).

### 2026-05-26 — Reconciliação documental (PO)

Front-matter estava `draft` mas commit `1249195` (25/mai 22:03) já marcou a estória como `done` no título. Validador identificou a defasagem ao tentar abrir a STORY-037 (pré-condição "todas done no index"). Reconciliação aplicada:

- `status: draft → done`.
- `closed_at: 2026-05-25` (data real do commit).
- `approved_by/at: Alexandro / 2026-05-25` (aprovação retroativa após validação independente do épico em 26/mai cobrir CA-1 a CA-9 via Bloco B-10, D-5, D-6, D-7).
- 9 CAs marcados `[x]`.
- `reconciled_at: 2026-05-26`.

Substância da estória 100% verde na validação independente — ver `validation/report.md` §Bloco B-10 (eventos com payload ADR-004 §2.2 confirmados em SQL), §D-5 (`EmissaoEventoArchTest` único caminho), §D-6 (zero PII), §D-7 (`request_id` UUID v7).

Sinal de processo para a retro da sprint: o `done-checklist.md` do Programador precisa cobrir a atualização do arquivo da estória, não só do commit. (Registrado no F-NB-6 do relatório do Validador.)
