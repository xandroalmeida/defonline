---
artifact: backlog-item
parent_epic: EPIC-002
type: tech-debt
priority: baixa
size: S
owner: programador
created_at: 2026-05-26
status: open
related_fnb: F-NB-5 (validation/report.md)
related_idr: IDR-010
---

# Débito — Teste arquitetural de imutabilidade do snapshot

## O que é

Criar `tests/Architectural/SnapshotImutabilidadeTest` (Pest) que valida em regressão automatizada que as colunas de snapshot da tabela `diagnosticos` nunca são atualizadas após `INSERT`.

## Por que existe

`validation/checklist.md` (item D-2) referenciava `Tests\Architectural\SnapshotImutabilidadeTest` como "existente", mas ele não existe. A imutabilidade do snapshot hoje é garantida por:

- Schema `NOT NULL` nas colunas (`indicadores_calculados`, `resumo_executivo`, `payload_hash`, `motor_version`, `matrix_version`).
- Ausência de caminho de `update` em `diagnosticos` na aplicação.
- Criação-única via `CalcularDiagnostico::execute`.

**Funciona, mas não há trava automatizada** contra uma futura introdução acidental de `update` ou `save` nessas colunas — convenção repousa em revisão de PR.

## Critério de pronto

1. Teste arquitetural criado em `tests/Architectural/SnapshotImutabilidadeTest.php`.
2. Assert: nenhum arquivo em `app/` (exceto `tests/`) chama `update`/`save`/`fill` em qualquer Model `Diagnostico` mutando colunas de snapshot.
3. (Opcional) Trigger no Postgres que falha qualquer `UPDATE` em `diagnosticos.indicadores_calculados`, `resumo_executivo`, `payload_hash`, `motor_version`, `matrix_version` — defesa em profundidade.
4. Test verde + Pint + Larastan.

## Janela e responsável

- **Janela:** entra na próxima sprint de débito (pós-EPIC-002), prioridade baixa — sem deadline.
- **Responsável:** Programador.
- **Estimativa:** S (≤ 30 minutos para a versão Pest sem trigger; +30 min se incluir trigger Postgres).

## Referências

- IDR-010 §Sub-decisão 2 (snapshot imutável)
- `validation/report.md` D-2 / F-NB-5
- `validation/checklist.md` D-2
