# DEFOnline

Plataforma SaaS de Diagnóstico Econômico-Financeiro para Micro e Pequenas Empresas.

> **Deploy (ADR-009):** o ambiente `defonline-prod` roda no ZalkVPS em `https://defonline.zalk.com.br`. Contrato da plataforma em [`ZALKVPS.md`](ZALKVPS.md).

## Estrutura

```
~/Projetos/DEFOnline/
├── README.md                       este arquivo
├── docker-compose.yml              topologia local (ADR-002): web + worker + scheduler + db + mailpit
├── up.sh                           bootstrap "1 comando" (CA-4)
├── ZALKVPS.md                      contrato da plataforma de deploy (gerado pelo kit do ZalkVPS — não editar)
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
│   ├── docker/php/security.ini         hardening do PHP (PHP2Shell, limites de corpo)
│   ├── postgres/initdb/                roles e extensões do Postgres LOCAL (dev)
│   ├── pgadmin/                        pgAdmin — dev local apenas (STORY-009)
│   └── zalk/docker-compose.yml         compose do servidor ZalkVPS (ADR-009)
│
├── scripts/
│   ├── git-hooks/pre-push.sh           gates locais (Pint + Larastan + Pest + Dusk) — ADR-006 §4
│   ├── install-hooks.sh                instala pre-push (rodado por up.sh)
│   ├── check-no-pgadmin-in-deploy.sh   STORY-009 CA-10 — pgAdmin nunca no compose do servidor
│   └── segredos.sh                     segredos cifrados com SOPS em secrets/ (ver "Segredos versionados")
│
├── .github/workflows/              CI/CD GitHub Actions (ADR-006)
│   ├── pr.yml                          lint + segurança + Unit puros (~2-3 min)
│   ├── main.yml                        re-valida o SHA mergeado em main
│   ├── bump-rc.yml                     sugere a próxima tag -rc.N
│   └── zalk-deploy.yml                 tag v* → build GHCR → zalk-deploy no ZalkVPS → smoke
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

### Acessando o banco em dev

PhpPgAdmin (na verdade `dpage/pgadmin4`, image oficial e ativa) sobe junto com o `./up.sh`:

- **URL:** http://localhost:8091 (escuta apenas em `127.0.0.1` — não acessível de fora da máquina).
- **Login da UI:** `admin@defonline.local` / `dev` — credenciais fixas de desenvolvimento, sem segredo.
- **Servidor `db` pré-registrado** em `infra/pgadmin/servers.json` (grupo "DEFOnline" → "defonline (dev local)"). Ao abrir pela primeira vez ele pede a senha do Postgres:
  - `postgres` / `postgres_secret_local` — superuser, ignora os GRANTs (use para inspeção ampla em dev).
  - ou `defonline_app` / `defonline_app_secret_local` — role runtime com os GRANTs restritivos da ADR-003/ADR-004 (use para conferir o que o app realmente enxerga).

> **PhpPgAdmin é exclusivo do ambiente de desenvolvimento local. Não existe no servidor.** (STORY-009, ADR-009.) No ZalkVPS, inspeção de banco é via `ssh defonline-prod@<host>` + `psql` dentro do container `web` — não há UI web administrativa.

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

### Cobertura de testes

A imagem `web` traz **PCOV** habilitado (driver leve de cobertura — ~10-20% de overhead). O gate prescrito em `quality-standards.md` §1.1+§2.2 e em ADR-006 §Decisão 4 é aplicado em DOIS pontos (STORY-010):

- **No pre-push hook local** — falha o `git push` se a cobertura geral cair abaixo de 80%, ou se `app/Domain/**` (a nascer no EPIC-001) ficar abaixo de 98%.
- **No PR (job `test-coverage` do `pr.yml`)** — falha o check do PR pelas mesmas regras + publica `coverage-summary.txt` como artefato baixável.

Para medir cobertura local sob demanda:

```bash
# Geral (testsuite All contra Postgres real):
docker compose exec web ./vendor/bin/pest --testsuite=All --coverage --min=80

# Núcleo de domínio (≥98%, quando app/Domain existir):
docker compose exec web ./vendor/bin/pest --configuration=phpunit-domain.xml --coverage --min=98
```

`./vendor/bin/pest` é preferido a `php artisan test` aqui porque o wrapper artisan engole o exit code do gate (Pest 4.7 reporta FAIL mas retorna 0 via artisan).

## Feature flags (Laravel Pennant)

```bash
# Declarar uma flag em app/Features/<NomeFlag>.php com @owner + @cleanup_due
# Resolver/ativar/desativar:
docker compose exec web php artisan pennant:feature:check App\\Features\\<NomeFlag>
docker compose exec web php artisan pennant:list-overdue --fail-on-overdue
```

## Ambientes (ADR-009)

| Ambiente | Onde | Como acessar |
|---|---|---|
| **local** | Docker Compose da raiz | `./up.sh` → http://localhost:8090 |
| **prod** (ZalkVPS) | usuário `defonline-prod`, `/srv/zalk/projects/defonline-prod`, Postgres do host (`defonline_prod`) | https://defonline.zalk.com.br |

Deploy: push de tag `vX.Y.Z-rc.N` ou `vX.Y.Z` dispara `zalk-deploy.yml`. Rollback sem rebuild: `ssh defonline-prod@<host>` e `zalk-deploy --rollback`. Backup do Postgres é da plataforma (pgBackRest + `pg_dump` diário em S3 e R2); restauração é pedida ao administrador do ZalkVPS.

## Segredos versionados

O `.env.prod` (segredos do `defonline-prod` na ZalkVPS, vira o secret `APP_ENV_FILE`) e o `app/.env`
(dev local) ficam em claro só na máquina, fora do git. As cópias cifradas com SOPS ficam em `secrets/`
e vão para o git; só as chaves listadas em `.sops.yaml` abrem (hoje: `~/.ssh/id_rsa`, a mesma do
ZalkVPS e do LeilaoFacil).

```bash
scripts/segredos.sh instalar   # uma vez por clone: sops fixado em .tools/bin + hooks do git
scripts/segredos.sh pull       # decifra (os hooks também, após pull/checkout/rebase, só na main)
scripts/segredos.sh push       # depois de alterar qualquer um deles; em seguida, commit de secrets/
scripts/segredos.sh status     # sincronizado / mudança local não enviada / cópia cifrada mais nova / conflito
```

O `pull` nunca sobrescreve edição local: se os dois lados mudaram, a versão do git fica em
`<arquivo>.incoming` para juntar à mão. O hook de pre-commit recusa commit com segredo em claro ou com
arquivo local alterado e não enviado (`SKIP_SECRETS_CHECK=1` ignora). Perdeu a `id_rsa`, perdeu o
acesso: ela fica no gerenciador de senhas.

## Documentação canônica

Antes de mexer em código, ler os documentos a seguir:

- **ADRs aceitas** em `defonline-docs/project-state/decisions/adr/`:
  - ADR-001 — Stack (Laravel 13 + Livewire 4 + PG 18 + Pest 4 + Dusk 8).
  - ADR-002 — Topologia (monolito modular + web/worker/scheduler + request_id UUID v7).
  - ADR-003 — Persistência (Eloquent + multi-tenancy + audit log + soft delete + LGPD).
  - ADR-004 — Observabilidade (logs JSON + métricas no PG + Telegram + 6 eventos de produto).
  - ADR-005 — Infra (VPS BR + Ansible + Caddy) — **substituída pela ADR-009**.
  - ADR-006 — CI/CD (GHA + pre-push hook + tag-based dual + Pennant).
  - ADR-009 — Infra no ZalkVPS (ambiente `defonline-prod`, Postgres e backup da plataforma).
- **Spec funcional** em `defonline-docs/especificacao/V2/especificacao-funcional.md`.
- **RNFs** em `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md`.

## Próximos passos

1. **Ativar o ambiente no ZalkVPS** (administrador da plataforma): ver ADR-009 §Ativação.
2. **STORY-008** — validação do EPIC-000 (ver `epics/EPIC-000-foundation/validation/`).
