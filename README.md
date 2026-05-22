# DEFOnline

Plataforma SaaS de Diagnóstico Econômico-Financeiro para Micro e Pequenas Empresas.

> **STORY-007 Phase 3 (2026-05-22):** código de Ansible + workflows de deploy + runbook prontos. Para ativar homologação ao vivo, siga `defonline-docs/project-state/epics/EPIC-000-foundation/RUNBOOK-homolog-phase3.md`.

## Estrutura

```
~/Projetos/DEFOnline/
├── README.md                       este arquivo
├── docker-compose.yml              topologia local (ADR-002): web + worker + scheduler + db + mailpit
├── up.sh                           bootstrap "1 comando" (CA-4)
├── .commitlintrc.json              Conventional Commits (ADR-006 §1.3)
│
├── app/                            ★ código Laravel 13 + Livewire 4
│   ├── app/                            código-fonte PHP (Features/, Jobs/, Livewire/, Observabilidade/, ...)
│   ├── database/migrations/            migrations Laravel (ADR-003 + ADR-004)
│   ├── routes/                         rotas web e console
│   ├── tests/                          Pest UnitPure/Feature + Dusk E2E
│   ├── pint.json                       lint Pint (ADR-006 §3.1)
│   └── phpstan.neon                    Larastan nível 6 (ADR-006 §3.1)
│
├── infra/
│   ├── docker/Dockerfile               imagem única para web/worker/scheduler (ADR-002)
│   ├── postgres/initdb/                roles e GRANTs (ADR-005 §7.4)
│   └── ansible/                        playbooks deploy/backup (ADR-005 §2 — Phase 3)
│       ├── ansible.cfg
│       ├── requirements.yml            community.docker/general/crypto + ansible.posix
│       ├── inventories/homolog/        hosts.yml + group_vars/all.yml + vault.yml.example
│       └── playbooks/                  site / bootstrap / docker / app / deploy / backup / restore
│
├── scripts/
│   ├── git-hooks/pre-push.sh           gates locais (Pint + Larastan + Pest + Dusk) — ADR-006 §4
│   ├── install-hooks.sh                instala pre-push (rodado por up.sh)
│   ├── gen-deploy-ssh-key.sh           gera par SSH dedicado ao deploy (Phase 3)
│   └── gen-backup-gpg-key.sh           gera passphrase GPG para backup (Phase 3)
│
├── .github/workflows/              CI/CD GitHub Actions (ADR-006)
│   ├── pr.yml                          lint + segurança + Unit puros (~2-3 min)
│   ├── main.yml                        build + push GHCR
│   ├── bump-rc.yml                     incrementa próxima tag -rc.N
│   ├── release-homolog.yml             deploy homol (Phase 3: Ansible)
│   ├── release-production.yml          deploy prod com gate humano (Phase 3)
│   └── deploy-by-tag.yml               rollback / re-deploy explícito
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

## Rodando testes e lints

```bash
# Lints
docker compose exec web ./vendor/bin/pint --test         # estilo (Pint)
docker compose exec web ./vendor/bin/phpstan analyse     # análise estática (Larastan nível 6)

# Pest 4
docker compose exec web php artisan test --testsuite=UnitPure   # sem DB, rápido
docker compose exec web php artisan test --testsuite=All        # com Postgres (RefreshDatabase)

# E2E browser real (Laravel Dusk 8) — exige container web rodando
docker compose exec web php artisan dusk

# Pre-push hook completo (Pint + Larastan + Pest + Pennant + Dusk)
./scripts/git-hooks/pre-push.sh
```

O hook **roda automaticamente** antes de cada `git push` (instalado por `./up.sh`).
Bypass com `--no-verify` é **proibido por política** (ADR-006).

## Feature flags (Laravel Pennant)

```bash
# Declarar uma flag em app/Features/<NomeFlag>.php com @owner + @cleanup_due
# Resolver/ativar/desativar:
docker compose exec web php artisan pennant:feature:check App\\Features\\HelloWorldEmailHabilitado
docker compose exec web php artisan pennant:list-overdue --fail-on-overdue
```

## 3 ambientes (estado atual da STORY-007)

| Ambiente | Status | Como acessar |
|---|---|---|
| **local** | ✅ funcional | `./up.sh` → http://localhost:8090 |
| **homologação** | 🛠️ código pronto, aguardando provisionamento humano (VPS + DNS + Secrets) — siga RUNBOOK | `https://defonline.xandrix.com.br` (após Phase 3) |
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

1. **STORY-007 Phase 3 — provisionamento humano** (PO): siga `defonline-docs/project-state/epics/EPIC-000-foundation/RUNBOOK-homolog-phase3.md` (60-90 min): contratar VPS, configurar DNS DigitalOcean, criar bot Telegram, criar bucket B2, gerar chaves, preencher Ansible Vault, adicionar GitHub Secrets, disparar primeira tag `v0.1.0-rc.1`.
2. **STORY-008** — validação do EPIC-000 (ver `epics/EPIC-000-foundation/validation/`).
