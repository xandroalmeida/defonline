---
adr_id: ADR-009
slug: infra-zalkvps
title: Infra no ZalkVPS — ambiente defonline-prod em plataforma multi-projeto, com Postgres, Caddy e backup do host
status: accepted
decided_at: 2026-10-30
decided_by: PO (Alexandro)
approved_by: Alexandro
supersedes: ADR-005
superseded_by: null
related_adrs: ["ADR-002", "ADR-004", "ADR-005", "ADR-006"]
related_idrs: ["IDR-002", "IDR-012"]
created_at: 2026-10-30
updated_at: 2026-10-30
type: infra
---

# ADR-009 — Infra no ZalkVPS

## Contexto

A VPS de homologação da ADR-005 (Ansible próprio, Caddy e Postgres em container,
backup GPG → R2) foi desligada em 2026-10-30. O PO decidiu hospedar o DEFOnline no
**ZalkVPS**, plataforma multi-projeto que já atende outros produtos (repo
`Pessoal/ZalkVPS`). O contrato da plataforma está em [`ZALKVPS.md`](../../../../ZALKVPS.md),
gerado pelo kit do ZalkVPS — é a referência normativa; esta ADR registra só o que
muda para o DEFOnline.

## Decisão

1. **Um ambiente: `defonline-prod`**, domínio `https://defonline.zalk.com.br`
   (domínio padrão de `prod` no ZalkVPS). Usuário Linux `defonline-prod`, Docker
   rootless, diretório `/srv/zalk/projects/defonline-prod/`. O fluxo de tags rc da
   ADR-006 continua, agora publicando neste ambiente.
2. **Topologia ADR-002 mantida:** mesma imagem em `web` + `worker` + `scheduler`,
   definida em `infra/zalk/docker-compose.yml`; migrations num serviço `migrate`
   com `profiles: [predeploy]` (o `zalk-deploy` roda antes do `up`). Só o `web`
   publica porta, em `127.0.0.1:${APP_PORT}`.
3. **Postgres 18 do host** (`10.254.0.1`), base e role `defonline_prod`, dona da
   base. Some o container `db` e os roles `defonline_app` / `defonline_backup`
   da ADR-005 §7.4. Variáveis vêm do `.env.zalk` (gerado pela plataforma) e são
   mapeadas para `DB_DATABASE`/`DB_USERNAME` no compose.
4. **Caddy do host** faz TLS e proxy, mas **não** injeta cabeçalhos de segurança:
   passam para a aplicação (`App\Http\Middleware\SecurityHeaders` — HSTS, nosniff,
   `X-Frame-Options`, `Referrer-Policy`). O limite de corpo de request (antes
   `request_body` no Caddyfile) vai para o PHP (`post_max_size = 2M` em
   `infra/docker/php/security.ini`).
5. **Backup é da plataforma:** pgBackRest (full/incr/WAL, PITR) + `pg_dump` diário
   em S3 e R2. Sai o backup próprio GPG → R2 (IDR-012). Restauração é pedida ao
   administrador (`zalk-backup restore-dump`) e cria base nova.
6. **Deploy** pelo workflow `.github/workflows/zalk-deploy.yml` (adaptado do kit):
   tag `v*` → build `ghcr.io/xandroalmeida/defonline/app:<tag>` → SSH como
   `defonline-prod` → `zalk-deploy <imagem>` → smoke read-only. Segredos da app no
   secret `APP_ENV_FILE` do GitHub Environment `prod`; acesso SSH em `ZALK_*`.
   Substitui `release-homolog.yml`, `release-production.yml` e `deploy-by-tag.yml`.
7. **Ansible, inventário, vault e chaves de deploy próprios saem do repositório.**
   Mudança de plataforma (domínio, limites, extensões, conexões) é pedido ao
   administrador do ZalkVPS; agentes do DEFOnline não alteram aquele repositório.

## Consequências

- **Append-only enfraquecido.** As migrations de `audit_logs`, `evento_produto` e
  `term_acceptances` fazem `REVOKE UPDATE, DELETE` do role da aplicação. Agora esse
  role é o **dono** das tabelas: o REVOKE funciona, mas o dono pode reconcedê-lo.
  A defesa passa a depender de nenhum código fazer `GRANT` — aceitável no MVP;
  reavaliar se a auditoria exigir garantia no banco.
- **Extensões:** `pg_stat_statements` deixa de existir no servidor (não é trusted).
  `citext` (tipo de `usuarios.email`) é criada pela migration
  `2026_05_22_000015_create_citext_extension` — antes vinha do initdb; o primeiro deploy
  (v0.11.0) falhou sem ela. `pgcrypto` e `pg_trgm` não são usadas pelo código; se forem,
  mesma receita (trusted, a role dona cria).
- **Limites da plataforma:** 1 vCPU, memória por cgroup (default 768M), 20 conexões
  por role. Web + worker + scheduler cabem com folga.
- **Sem volume com backup:** nada de arquivo de usuário em volume local (o app não
  tem upload hoje).
- **Uma VPS compartilhada** com outros projetos: isolamento por usuário/rootless,
  não por máquina.

## Ativação (pedido ao administrador do ZalkVPS)

1. Em `ansible/projects.yml`: `name: defonline`, `env: prod`, `port` livre
   (ex.: 20005), `repo: xandroalmeida/defonline`; `make apply TAGS=projects,postgres,caddy`.
2. `make gh-env ID=defonline-prod REPO=xandroalmeida/defonline`.
3. `gh secret set APP_ENV_FILE --env prod --repo xandroalmeida/defonline < .env.prod`.
4. Push de uma tag rc dispara o primeiro deploy.
