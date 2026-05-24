---
story_id: STORY-022
slug: alinhar-nomenclatura-business-metrics
title: Alinhar nomenclatura `kind` ↔ `tipo` em `business_metrics` (checklist vs schema)
epic_id: EPIC-001
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-05-24
updated_at: 2026-05-24
estimated_session_size: S
---

# STORY-022 — Alinhar `kind` ↔ `tipo` em `business_metrics`

> **Para o agente programador:** estória **XS de cosmética** — meia hora, talvez menos. O `validation/report.md` do EPIC-001 (item 4.8) registrou uma divergência nominal entre o checklist do PO (`kind`) e o schema do banco (`tipo`). O PO já reconheceu que **a falha está no checklist** dele, não no código. Aqui você só precisa decidir entre duas escolhas pequenas e executar.

## Contexto (por que esta estória existe)

A coluna canônica em `business_metrics` é `tipo` (`string('tipo')->index()` em [app/database/migrations/2026_05_22_000050_create_metrics_tables.php](app/database/migrations/2026_05_22_000050_create_metrics_tables.php)). O código usa `tipo` consistentemente ([RfbConsultarCnpj.php](app/app/Services/Rfb/RfbConsultarCnpj.php), [MonitorarRfbErrorRate.php](app/app/Console/Commands/MonitorarRfbErrorRate.php), [BaseJob](app/app/Jobs/BaseJob.php)).

Mas:
- O `validation/checklist.md` do EPIC-001 (item 4.8) escreveu `kind = 'rfb_consulta'`.
- A ADR-004 §1.2 (`business_metrics`) também pode usar `kind` em algum trecho — confirmar.

A divergência é **puramente nominal** — zero risco funcional. Mas se ficar, vai gerar atrito de leitura toda vez que alguém cruzar o checklist com o código.

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md` (origem do débito)
- Documentos canônicos:
  - [defonline-docs/project-state/decisions/adr/ADR-004-observabilidade.md](defonline-docs/project-state/decisions/adr/ADR-004-observabilidade.md) (§1.2 — verificar qual nome usa)
  - [defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/validation/checklist.md](defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/validation/checklist.md) (item 4.8)
  - [app/database/migrations/2026_05_22_000050_create_metrics_tables.php](app/database/migrations/2026_05_22_000050_create_metrics_tables.php) (schema atual)

## O quê (objetivo desta estória)

Escolher **uma** das duas direções abaixo e executar:

### Direção A (recomendada — barata) — manter `tipo`, ajustar docs

- **Atualizar o `checklist.md`** do EPIC-001 trocando `kind` por `tipo` no item 4.8 (1 linha) — não é "reabertura de validação", é correção de documentação.
- **Auditar ADR-004 §1.2** e qualquer outro doc onde `kind` apareça no contexto de `business_metrics`; trocar por `tipo`.
- **Atualizar futuros templates** de checklist do PO para usar `tipo` (vai ajudar o próximo épico).
- Nenhuma mudança em código de produção, nenhuma migration, nenhum teste novo.

### Direção B — renomear coluna no banco para `kind`

- Migration para renomear coluna `tipo → kind` em `business_metrics`.
- Atualizar todos os call sites em PHP (`tipo` → `kind`): jobs, services, monitor, etc.
- Atualizar índices se necessário (`idx_business_metrics_tipo_inserido_em` → `..._kind_...`).
- Atualizar testes que asseguram a query.
- Mais trabalho, mais risco (precisa deploy + manter cobertura de testes).

**Recomendação do PO**: Direção A. A documentação seguiu errado o código; correção certa é em docs, não em código que está funcionando bem.

## Por quê (valor para o usuário)

Zero valor direto para Roberto. Para o time: consistência de leitura entre `checklist` (sua bateria de validação) e código. Evita perder 30 segundos por mês buscando "será que é `kind` ou `tipo`?". Higiene de processo.

## Critérios de aceite

### Se Direção A (recomendada)

- [ ] **CA-1:** [checklist.md](defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/validation/checklist.md) item 4.8 troca `kind` por `tipo` na descrição "Como verificar" e onde mais aparecer. Mesmo arquivo, 1-2 edits.
- [ ] **CA-2:** ADR-004 §1.2 auditada (grep `kind` no escopo de `business_metrics`); divergências corrigidas para `tipo` (ou justificada a manutenção em prosa).
- [ ] **CA-3:** Audit em outros checklists / templates do PO (`defonline-docs/skills/po/templates/`) — se algum cita `kind` no contexto de `business_metrics`, trocar.
- [ ] **CA-4:** **Sem mudança em código de produção** (`app/`, `database/`, `tests/`).

### Se Direção B (cara — apenas se Arquiteto decidir mudança vale)

- [ ] **CA-1:** Migration `2026_XX_XX_rename_business_metrics_tipo_to_kind.php` cria coluna `kind`, copia valores, dropa `tipo`, recria índices (transação simples no Postgres).
- [ ] **CA-2:** Substituir todos os 12+ matches de `'tipo' =>` / `where('tipo'` / `meta->>'tipo'` em PHP por `kind`.
- [ ] **CA-3:** Suíte Pest verde com cobertura mantida ≥ 80% geral e ≥ 98% domain.
- [ ] **CA-4:** Pipeline release-homolog verde end-to-end (validate + build + deploy + smoke).
- [ ] **CA-5:** Documentação atualizada (mesmos itens do A, mas agora confirmando que código bate).

## Fora de escopo

- Renomear `business_metrics` inteiro ou repensar schema.
- Adicionar novos campos.
- Mudar a estratégia de `meta` JSONB.

## Padrões de qualidade exigidos

Direção A: zero risco. Direção B: cobertura ≥ 80% mantida, pipeline verde end-to-end. Sem regressão em consultas existentes.

## Dependências

- **Bloqueada por:** nenhuma.
- **Bloqueia:** nenhuma. (Pode entrar em qualquer sprint; vale pegar em janela ociosa.)

## Decisões já tomadas

- **PO escolhe a Direção** ao incluir a estória em sprint — recomendação registrada acima (Direção A).
- **Sem reabertura de validação do EPIC-001** — esta é correção de processo, não regressão técnica.

## Liberdade técnica do agente

Você decide:
- Quais arquivos auditar além dos listados (sugiro `grep -RE "kind.*business_metrics\|business_metrics.*kind" defonline-docs/ app/`).
- Wording exato da correção no checklist.

Você NÃO decide:
- Trocar `tipo` por `kind` no código sem o PO ter declarado Direção B explicitamente.

## Definição de Pronto

- [ ] CAs da direção escolhida cumpridos.
- [ ] (Direção B somente) pre-push verde + CI verde + deploy validado.
- [ ] STORY-022 `status: done` no frontmatter + `index.json`.
- [ ] Notas do agente preenchidas com a Direção executada e os arquivos tocados.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`.

## Notas do agente

### Tempo investido
- <horas>

### Direção escolhida
- <A | B> — <justificativa em 1 linha>

### Arquivos tocados
- <lista>

### Observações úteis ao PO
- <opcional>
