# DEFOnline

Plataforma SaaS de Diagnóstico Econômico-Financeiro para Micro e Pequenas Empresas.

> **STORY-007 Phase 1 (2026-05-22):** este repositório agora contém o app Laravel funcional rodando localmente. As próximas fases adicionarão CI/CD, Ansible e deploy em homologação real (conforme ADRs do EPIC-000).

## Estrutura

```
~/Projetos/DEFOnline/
├── README.md                       este arquivo
├── docker-compose.yml              topologia local (ADR-002): web + worker + scheduler + db + mailpit
├── up.sh                           bootstrap "1 comando" (CA-4)
│
├── app/                            ★ código Laravel 13 + Livewire 4
│   ├── app/                            código-fonte PHP
│   ├── database/migrations/            migrations Laravel (ADR-003 + ADR-004)
│   ├── routes/                         rotas web e console
│   └── tests/                          Pest Unit/Feature + Dusk E2E
│
├── infra/
│   ├── docker/Dockerfile               imagem única para web/worker/scheduler (ADR-002)
│   └── postgres/initdb/                roles e GRANTs (ADR-005 §7.4)
│
└── defonline-docs/                 documentação do projeto
    ├── project-state/                  estado vivo (épicos, estórias, ADRs, PDRs)
    ├── especificacao/V2/               regras de negócio
    └── skills/                         papéis do projeto (PO, arquiteto, programador, validador)
```

## Subindo o ambiente local — 1 comando

```bash
./up.sh
```

O script é idempotente (primeira execução: ~3-6 min; depois: ~10s). Sobe:

- **web** (`http://localhost:8090`) — Laravel + Livewire, `artisan serve`.
- **worker** — `php artisan queue:work` consumindo `pdf,emails,lgpd,default`.
- **scheduler** — `php artisan schedule:work` (long-running).
- **db** — PostgreSQL 18 com extensões `pgcrypto`, `pg_trgm`, `citext`, `pg_stat_statements` (ADR-005 §7.3) e roles separados `defonline_app` / `defonline_backup` (ADR-005 §7.4).
- **mailpit** — SMTP local em `:1025`, UI em `http://localhost:8025`.

### Verificações rápidas

- **Página viva:** http://localhost:8090 — mostra nome do produto, versão, status do healthcheck.
- **Liveness:** `curl http://localhost:8090/health` → 200 + JSON.
- **Readiness:** `curl http://localhost:8090/ready` → 200 + checks de DB/cache/queue.
- **Mailpit:** após clicar "Disparar e-mail de teste" no hello world, ver em http://localhost:8025.

## Rodando testes

```bash
# Suíte unitária + feature (Pest 4)
docker compose exec web php artisan test

# E2E em browser real (Laravel Dusk 8) — exige container web rodando
docker compose exec web php artisan dusk
```

## 3 ambientes (estado atual da STORY-007)

| Ambiente | Status | Como acessar |
|---|---|---|
| **local** | ✅ funcional (esta fase) | `./up.sh` → http://localhost:8090 |
| **homologação** | 🛠️ Phase 2 — Ansible + CI/CD + VPS BR | (em breve) `https://homolog.defonline.com.br` |
| **produção** | 📦 código pronto, não provisionada | (gatilho de promoção em ADR-005 §Decisão 6) |

## Documentação canônica

Antes de mexer em código, ler os documentos a seguir:

- **ADRs aceitas** em `defonline-docs/project-state/decisions/adr/`:
  - ADR-001 — Stack (Laravel 13 + Livewire 4 + PG 18 + Pest 4 + Dusk 8).
  - ADR-002 — Topologia (monolito modular + web/worker/scheduler + request_id UUID v7).
  - ADR-003 — Persistência (Eloquent + multi-tenancy + audit log + soft delete + LGPD).
  - ADR-004 — Observabilidade (logs JSON + métricas no PG + Telegram + 6 eventos de produto).
  - ADR-005 — Infra (VPS BR + Ansible + Caddy + Docker Compose + Backblaze B2).
  - ADR-006 — CI/CD (GHA + pre-push hook + tag-based dual + Pennant).
- **Spec funcional** em `defonline-docs/especificacao/V2/especificacao-funcional.md`.
- **RNFs** em `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md`.

## Próximos passos

1. **STORY-007 Phase 2** — CI/CD (GHA workflows + pre-push hook + Pennant).
2. **STORY-007 Phase 3** — Ansible playbooks + contratar VPS BR + DNS Cloudflare + deploy real em homologação.
3. **STORY-008** — validação do EPIC-000 (ver `epics/EPIC-000-foundation/validation/`).
