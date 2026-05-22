---
idr_id: IDR-001
slug: postgres-extensions-via-initdb
title: Extensões Postgres habilitadas via init script do container, não via migration Laravel
status: accepted
decided_at: 2026-05-22
decided_by: programador
owner_agent: programador (claude-opus-4-7)
related_story: STORY-007
related_adrs: ["ADR-003", "ADR-005"]
related_idrs: []
supersedes: null
superseded_by: null
created_at: 2026-05-22
updated_at: 2026-05-22
---

# IDR-001 — Extensões Postgres via init script (não via migration Laravel)

## Contexto

ADR-005 §7.3 prevê extensões Postgres (`pgcrypto`, `pg_trgm`, `citext`, `pg_stat_statements`) habilitadas via **migration Laravel**, com a justificativa de versionamento + testabilidade + auditabilidade.

Na implementação da STORY-007, descobri dois problemas concretos:

1. **`pg_stat_statements` exige superuser** para `CREATE EXTENSION` — não é uma "trusted extension". O role `defonline_app` (princípio do menor privilégio, ADR-005 §7.4) **não** pode criar essa extensão.
2. Mesmo as três trusted extensions (`pgcrypto`, `pg_trgm`, `citext`) **exigem** privilégio `CREATE ON DATABASE` para serem instaladas. O role `defonline_app` por padrão tem apenas `CONNECT` na database e `USAGE`+`CREATE` no schema `public`. Conceder `CREATE ON DATABASE` ao app role amplia o blast radius (permite criar schemas e potencialmente outras extensões trusted) sem ganho proporcional vs. o status quo de extensions estáveis.

Resultado: tentar rodar `php artisan migrate` como `defonline_app` falhava com `permission denied to create extension`.

## Decisão

> **Decidi mover a habilitação das 4 extensões para o init script `infra/postgres/initdb/00-roles.sh`, que roda como `postgres` superuser no first boot do volume `pgdata`. Removi a migration `enable_postgres_extensions`.**

## Por quê

- **Extensões são decisão de infra, não de domínio.** Versionamento via migration agrega mais cerimônia que valor: a lista de extensões muda raramente (uma vez por ADR de supersede), e a migration efetiva ficou no Git de qualquer forma (no init script).
- **Princípio do menor privilégio (ADR-005 §7.4) é mais importante que "tudo via Laravel migration".** Manter `defonline_app` sem `CREATE ON DATABASE` reduz a superfície de exploit em caso de SQL injection que escape do Eloquent.
- **`pg_stat_statements` não tem alternativa via migration** — exige superuser. Manter parte das extensões em migration e parte em init script seria pior (inconsistência).
- **Idempotência preservada.** `CREATE EXTENSION IF NOT EXISTS` no init script é seguro de rodar uma vez (no first boot do volume); execuções subsequentes nem chegam a rodar o script (entrypoint Postgres só executa `/docker-entrypoint-initdb.d/*` quando o data dir está vazio).

## Alternativas consideradas

- **Conceder `GRANT CREATE ON DATABASE defonline TO defonline_app`** — permitiria as 3 trusted extensions via migration mas não resolve `pg_stat_statements`. E amplia privilégio do role sem necessidade.
- **Rodar migrations como superuser `postgres`** — alternativa viável mas: (a) exige reconfigurar `.env` antes de cada deploy de migration; (b) cria duas conexões diferentes na infra (uma com superuser, outra com app); (c) Postgres do Ansible nem sempre vai expor o superuser ao runner de CI.
- **Manter status quo da ADR (migration Laravel)** — impossível para `pg_stat_statements` e exige relaxar o role.

## Consequências

### Para outros agentes
- **Adicionar nova extensão Postgres** = editar `infra/postgres/initdb/00-roles.sh` + recriar o volume `pgdata` em local/homol (ou rodar `CREATE EXTENSION` manual como superuser em produção).
- Versionamento da extensão fica em `git log` do init script (não na ordem de migration). É menos ergonômico para timeline mas é o trade-off aceito.
- Em ambiente já provisionado (sem recriar volume), aplicar extensão nova exige operação manual via `psql -U postgres -c 'CREATE EXTENSION ...'` — documentar no runbook da ADR-005 quando a próxima extensão entrar.

### Para o projeto
- ADR-005 §7.3 fica parcialmente divergente do código real. Atualizar a ADR (não esta IDR) quando outra extensão entrar no MVP — anotar o histórico na ADR.
- Init script é executado **apenas no first boot do volume**; após isso, mudanças no script não tomam efeito até `docker compose down -v`.

### Trade-offs aceitos
- Perdemos rastreabilidade ordenada por migration de "quando entrou cada extensão".
- Recriar o volume para mudar extensão é destrutivo em ambientes vivos (mitigação: comando manual + plano de operação).

## Como verificar

- `\dx` no `psql` em qualquer ambiente lista as extensões instaladas; deve coincidir com a lista no init script.
- Em CI de deploy futuro (Ansible playbook): step de `psql -U postgres -c '\dx'` que falha se faltar uma das 4 extensões esperadas.
