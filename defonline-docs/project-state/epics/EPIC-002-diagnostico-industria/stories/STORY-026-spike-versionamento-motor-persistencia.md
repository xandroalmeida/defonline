---
story_id: STORY-026
slug: spike-versionamento-motor-persistencia
title: SPIKE — Estratégia de versionamento do motor + persistência idempotente do diagnóstico
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: spike
target_role: arquiteto
status: done
owner_agent: claude-arquiteto
created_at: 2026-05-25
updated_at: 2026-05-25
closed_at: 2026-05-25
related_idr: IDR-010
estimated_session_size: S-M
---

# STORY-026 — SPIKE: versionamento do motor + persistência idempotente

> **Para o agente arquiteto:** esta é a primeira estória do EPIC-002. É um **spike de decisão** que destrava as estórias subsequentes (especialmente STORY-027 quiz, STORY-028 motor e STORY-029 relatório). O entregável **não é código de produção** — é uma **IDR aceita pelo PO** com decisão clara sobre versionamento e persistência, mais um esqueleto de migração e modelo pronto para a próxima estória consumir.

## Contexto (por que esta estória existe)

A `epic.md` lista como decisão arquitetural pendente: *"estratégia de versionamento do motor de cálculo (a matriz e as fórmulas vão evoluir — espec I.1.2); padrão de geração/renderização do relatório; idempotência do cálculo (mesmo input → mesmo output, para reprocessamento); persistência de diagnóstico para o EPIC-003."*

A matriz de recomendações DEZ/2025 (Anexo F) é explicitamente datada e a `epic.md` registra que faixas e fórmulas evoluirão. Se persistirmos o diagnóstico sem amarrar **versão do motor** + **versão da matriz**, perdemos a capacidade de:

- Reproduzir um relatório antigo idêntico ao que Roberto viu no dia (requisito do EPIC-003).
- Distinguir "mudança de input" de "mudança de motor" quando um indicador muda de cor entre dois diagnósticos.
- Auditar parecer externo do Validador (NRF §9.3) — qual versão exata do motor foi aprovada?

## O quê (objetivo do spike)

Entregar uma **IDR** (Implementation Decision Record) em `defonline-docs/project-state/decisions/IDR-XXX-versionamento-motor.md` com:

1. **Esquema de versionamento adotado** — semver (`1.0.0`), date-version (`2025-12`), hash de fórmulas, ou outro. Recomendação inicial do PO: `motor_version` em semver inteiro (`"1.0.0"`) + `matrix_version` em formato datado (`"dez-2025"`). Arquiteto pode contra-propor.
2. **Modelo de dados de persistência** do diagnóstico — tabela `diagnosticos` com pelo menos: `id`, `empresa_id`, `usuario_id`, `motor_version`, `matrix_version`, `quiz_payload` (JSON imutável), `indicadores_calculados` (JSON), `resumo_executivo` (texto + estrutura), `created_at`. Definir índices.
3. **Garantia de idempotência** — protocolo formal: dado o mesmo `quiz_payload` + mesma `motor_version` + mesma `matrix_version`, o resultado deve ser bit-exato. Definir como testar (golden files? hash de saída?).
4. **Política de evolução** — quando uma fórmula muda, o `motor_version` sobe. O diagnóstico antigo continua reproduzível porque o código antigo é mantido como branch versionada (estratégia: módulo de cálculo isolado com `MotorV1`, `MotorV2`, dispatcher por `motor_version`) OU snapshot dos resultados está congelado em `indicadores_calculados` (estratégia: motor único + snapshot — recomendação do PO por simplicidade no MVP).
5. **Tratamento de casos extremos** — divisão por zero, valores nulos no quiz, vendas = 0, EBITDA negativo. Catálogo dos casos críticos por indicador + política de retorno (`null` + mensagem "indisponível para este perfil").

## Por quê (valor)

- Sem essa decisão tomada na semana 1, STORY-028 (motor) e STORY-029 (relatório) começam com hipótese implícita e geram retrabalho no Checkpoint 2.
- Destrava o EPIC-003 (histórico) sem refactor de schema futuro.
- Permite que a validação externa (STORY-036) referencie versão exata aprovada.

## Critérios de aceite

- [x] **CA-1 (IDR escrita):** `defonline-docs/project-state/decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md` existe, segue o padrão dos IDRs do projeto, inclui as 5 decisões acima com rationale curto.
- [x] **CA-2 (Aceite do PO):** PO (Alexandro) confirmou no chat em 2026-05-25 (*"sim, confirmo. Siga em frente"*); IDR-010 movida para `accepted` com `approved_by: Alexandro`.
- [x] **CA-3 (Migration esqueleto):** migration `app/database/migrations/2026_05_25_000010_create_diagnosticos_table.php` criada com o schema decidido (não rodada — STORY-028 vai consumir). Inclui `down()` reversível.
- [x] **CA-4 (Model Eloquent):** classe `App\Models\Diagnostico` com casts (`AsArrayObject` para os 3 JSON), `belongsTo(EmpresaAnalisada)` e `belongsTo(Usuario)` (alinhado ao domínio existente), e helper `hasSameInputsAs(Diagnostico $other): bool` para teste de idempotência.
- [x] **CA-5 (Catálogo de casos extremos):** documento `defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/casos-extremos.md` lista 34 entradas (≥ 28 exigidas) cobrindo os 14 indicadores.
- [x] **CA-6 (Protocolo de idempotência):** documento `design/idempotencia.md` descreve canonicalização, função SHA-256, layout dos golden files em `app/tests/Domain/Motor/`, política de bump e fontes de não-determinismo proibidas.
- [x] **CA-7 (Sem código de produção rodando ainda):** spike não introduziu endpoint, view, livewire component, nem rodou migration. Apenas artefatos de decisão + esqueletos PHP. STORY-027 e STORY-028 consomem.

## Fora de escopo

- Implementação efetiva do motor (STORY-028, STORY-030).
- Implementação do relatório (STORY-029).
- Decisão sobre formato de renderização do relatório (HTML server-side vs SPA — fica para STORY-029).
- Decisão sobre cache do motor em produção — não é problema do MVP; pode entrar como follow-up se p95 ≤ 3s exigir.
- Decisão sobre exportação em PDF — EPIC-007 (onda 2).

## Dependências

- **Bloqueada por:** EPIC-001 `done` (precisa do modelo `Empresa`), EPIC-004 `done` (chassi de app pronto), EPIC-000 `done` (Postgres + Eloquent funcionando).
- **Bloqueia:** STORY-027 (precisa saber se quiz salva rascunho no mesmo schema ou tabela à parte), STORY-028 (consome o esqueleto), STORY-029 (consome o esqueleto).

## Decisões já tomadas pelo PO (defaults se IDR ficar travada)

- `motor_version` em **semver inteiro** (`"1.0.0"`); incrementa major a cada mudança de fórmula, minor a cada novo indicador.
- `matrix_version` em **formato datado** (`"dez-2025"`, `"mar-2026"`, etc.) — alinhado ao naming dos anexos da spec.
- **Snapshot dos resultados** no momento da geração (`indicadores_calculados` JSON) — não recalcula relatório antigo on-the-fly. Trade-off aceito: ganha simplicidade + reprodutibilidade trivial; perde a capacidade de "atualizar" um diagnóstico antigo se a matriz mudar (mas isso é semantically incorreto mesmo — diagnóstico antigo retrata o que Roberto viu naquele dia).
- **Casos extremos retornam `null` no valor numérico + mensagem semântica** ("indisponível para este perfil porque vendas=0") em vez de erro/exception. Relatório exibe linha cinza para esses casos.

## DoD

- [x] CA-1 a CA-7 atendidos.
- [x] IDR-010 escrita e linkada no `index.json` em `decisions.idrs[]`.
- [x] Migration + model commitados (sem rodar migration em homol — STORY-028 roda).
- [x] PO marcou STORY-026 `done` em 2026-05-25 e desbloqueou STORY-027/028/029.

## Protocolo do agente

Padrão `agent-task-format.md`. Spike — entregável é IDR + esqueletos. Avisar PO no chat quando IDR estiver pronta para revisão (≤ 4h de resposta do PO).

## Notas do agente

### 2026-05-25 — Execução do SPIKE (Arquiteto, claude-opus-4-7)

**Entregáveis produzidos (status `in_review`, aguardando aceite do PO no chat):**

1. **IDR-010** — `defonline-docs/project-state/decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md`, status `proposed`. Cobre as 5 decisões pedidas pela story:
   - Versionamento: `motor_version` semver inteiro (`"1.0.0"`) + `matrix_version` datada (`"dez-2025"`).
   - Modelo de dados: tabela `diagnosticos` com snapshot JSON imutável (`quiz_payload`, `indicadores_calculados`, `resumo_executivo`) + `payload_hash` SHA-256 + `gerado_em`. Multi-tenancy por `usuario_id` denormalizado (Global Scope ADR-003 sem JOIN).
   - Idempotência: contrato bit-exato; canonicalização do `quiz_payload` (chaves ordenadas, decimais como string, NFC); golden hashes em `app/tests/Domain/Motor/GoldenHashesTest.php`; mudança de hash = bump obrigatório.
   - Política de evolução: **motor único + snapshot** (default do PO confirmado). Rejeitada Opção B (dispatcher MotorV1/V2) por custo de manutenção sem ganho real no MVP. Opções C e D também consideradas e rejeitadas com justificativa.
   - Casos extremos: retorno `null` + `motivo` semântico (nunca exception para input degenerado conhecido).

2. **Migration esqueleto (CA-3):** `app/database/migrations/2026_05_25_000010_create_diagnosticos_table.php` — `down()` reversível, CHECK constraints para `setor` (`industria|comercio|servicos`), `motor_version` (regex semver), `matrix_version` (regex `mes-aaaa`) e `payload_hash` (regex SHA-256 hex). **Não rodada** — STORY-028 roda ao implementar o motor.

3. **Model Eloquent (CA-4):** `app/app/Models/Diagnostico.php` — `HasUuids` + `SoftDeletes` + `BelongsToUsuarioScope`; casts `AsArrayObject` para os três JSON; `belongsTo(Usuario)` e `belongsTo(EmpresaAnalisada)`; helper `hasSameInputsAs(Diagnostico $other): bool` definido em termos de `motor_version` + `matrix_version` + `setor` + `payload_hash`.

4. **Catálogo de casos extremos (CA-5):** `defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/casos-extremos.md` — 34 entradas (≥ 28 exigidas), cobrindo os 14 indicadores. Códigos de motivo padronizados (`indisponivel:vendas_zero`, `indisponivel:ebitda_negativo`, etc.) com mensagens em PT-BR. NCG informativo (#9) tratado conforme espec §4.5 (sem farol; 3 mensagens semânticas por faixa).

5. **Protocolo de idempotência (CA-6):** `defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/idempotencia.md` — regras de canonicalização, função de hash, layout dos golden files + dataset, política de bump, fontes de não-determinismo proibidas (now/random/usort instável), 5 fixtures recomendados para STORY-028.

6. **Index atualizado:** entrada IDR-010 adicionada em `decisions.idrs[]`; `generated_at` bumpado.

### Divergência nominal a registrar (não bloqueante)

O `epic.md` e a redação original da STORY-026 falam em `belongsTo Empresa` e `belongsTo User`. O domínio efetivo do projeto (entregue no EPIC-001 e EPIC-004) usa **`EmpresaAnalisada`** e **`Usuario`**. A migration usa FK `empresa_analisada_id` e `usuario_id`; o model usa `EmpresaAnalisada::class` e `Usuario::class`. Mantido alinhamento com o código existente. PO confirma o ajuste implicitamente ao aceitar a IDR.

### Pendências antes de fechar a story

- [x] Aceite do PO no chat em 2026-05-25 → IDR-010 `proposed` ⟶ `accepted`; STORY-026 `in_review` ⟶ `done`; `index.json` atualizado com `approved_by: Alexandro`.
- [x] PO confirmou que **STORY-027 (quiz)** mantém rascunho em **tabela separada** (`quiz_rascunhos`), não em `diagnosticos`. Premissa entra para a redação detalhada da STORY-027.

### Checklist final dos CA (Arquiteto)

- [x] **CA-1** — IDR-010 redigida, segue padrão dos IDRs anteriores (front-matter YAML + Contexto/Decisão/Por quê/Alternativas/Consequências/Como verificar/Tipo/Histórico), 5 decisões cobertas com rationale curto.
- [x] **CA-2** — Aceite do PO (Alexandro) em 2026-05-25 no chat. IDR-010 promovida para `accepted`.
- [x] **CA-3** — Migration `2026_05_25_000010_create_diagnosticos_table.php` criada com `down()` reversível; **não** rodada em homol.
- [x] **CA-4** — Model `App\Models\Diagnostico` criado com casts `AsArrayObject`, `belongsTo` corretos e helper `hasSameInputsAs()`.
- [x] **CA-5** — `design/casos-extremos.md` criado com 34 entradas (mínimo 28).
- [x] **CA-6** — `design/idempotencia.md` criado com protocolo completo.
- [x] **CA-7** — Nenhum endpoint, view, Livewire component ou migration rodada nesta sessão. Apenas artefatos de decisão + esqueletos PHP.

### Tempo de sessão

Estimado S-M (≤ 4h). Execução real: 1 sessão concentrada de IDR + esqueletos. Nenhum código de produção rodado em homologação (CA-7 OK).

---

### 2026-05-25 — Fechamento (status `done`)

PO (Alexandro) confirmou no chat: *"sim, confirmo. Siga em frente"*. IDR-010 promovida para `accepted` com `approved_by: Alexandro`. Premissa adicional confirmada: rascunho do quiz (STORY-027) fica em tabela à parte (`quiz_rascunhos`), não em `diagnosticos`.

**STORY-026 fechada como `done`.** Destrava: STORY-027 (quiz), STORY-028 (motor V1), STORY-029 (relatório minimalista) — fatia V1 da SPRINT-2026-W25.
