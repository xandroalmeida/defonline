# Deploy no ZalkVPS

Este repositório é implantado no ZalkVPS. Arquivos do kit:

- `docker-compose.yml` — usado em produção/staging; segue o contrato abaixo.
- `.github/workflows/zalk-deploy.yml` — build → GHCR → SSH → `zalk-deploy`.
- Este arquivo — contrato da plataforma. **Não edite**; atualize com `make kit` no repo ZalkVPS.

Para agentes: adicione ao `AGENTS.md`/`CLAUDE.md` do repo a linha
`Deploy/infra: siga ZALKVPS.md.` Desenvolvimento local pode usar um
`compose.dev.yml` próprio (com Postgres local, build etc.) — as regras abaixo
valem só para `docker-compose.yml`.

---

# Contrato de projeto — ZalkVPS

Regras para qualquer agente ou pessoa que configure um projeto para rodar no
ZalkVPS. O `zalk-check` valida as regras marcadas como **obrigatório**; o deploy
é recusado se alguma falhar.

## Modelo da plataforma

- **Um ambiente = um usuário Linux** `<projeto>-<env>` (ex.: `loja-prod`,
  `loja-staging`), com **Docker rootless próprio**. Um ambiente não enxerga
  containers, volumes nem arquivos de outro.
- **Diretório do ambiente:** `/srv/zalk/projects/<projeto>-<env>/`. O
  `docker-compose.yml` e os `.env` ficam aqui.
- **Postgres 18 compartilhado no host**, em `10.254.0.1:5432`. Cada ambiente tem
  sua própria base e role (`<projeto>_<env>`), sem acesso às outras.
  **Não suba Postgres no compose.**
- **HTTPS e domínio:** o Caddy do host recebe `https://<domínio>` e encaminha
  para `127.0.0.1:${APP_PORT}`. O certificado é automático. A aplicação fala
  HTTP puro e confia nos headers `X-Forwarded-*`. O Caddy não adiciona
  cabeçalhos de segurança: HSTS, CSP, `X-Frame-Options`, `Permissions-Policy`
  etc. são definidos pela própria aplicação.
- **Limites por ambiente:** RAM, CPU e número de processos via cgroup. Se a
  memória estourar, o kernel mata processos do ambiente.
- **Backup automático** do Postgres: full semanal, incremental diário, WAL
  contínuo (PITR) e `pg_dump` diário por base, em AWS S3 e Cloudflare R2.
  **Volumes Docker NÃO entram no backup.**

## Arquivos no diretório do ambiente

| Arquivo | Quem escreve | Conteúdo |
|---|---|---|
| `.env.zalk` | Ansible (não editar) | `APP_PORT`, `APP_URL`, `DATABASE_URL`, `DB_*`, `COMPOSE_PROJECT_NAME` |
| `.env` | o projeto (o deploy grava a partir de um segredo do GitHub) | segredos e configurações da aplicação |
| `.deploy.env` | `zalk-deploy` | `IMAGE` em uso. `.deploy.env.prev` guarda a anterior, para rollback |
| `docker-compose.yml` | o projeto (copiado do repo no deploy) | definição dos serviços |
| `AGENTS.md` / `CLAUDE.md` | Ansible | dados deste ambiente + este contrato |

Ordem de precedência na interpolação do compose: `.env.zalk` → `.env` →
`.deploy.env`.

## Regras do `docker-compose.yml`

**Obrigatório** (o `zalk-check` reprova):

1. Publicar **somente** `127.0.0.1:${APP_PORT}`. É a única porta exposta e o
   bind tem que ser em `127.0.0.1`.
2. Proibido: `privileged`, `network_mode: host`, `pid: host`, `ipc: host`,
   `cap_add`, `devices` e montar `docker.sock`.
3. Bind mounts só de caminhos dentro do diretório do ambiente. Para o resto, use
   volumes nomeados.

**Esperado** (o `zalk-check` avisa):

- Imagem pronta `${IMAGE}` vinda do GHCR, sem `build:` no servidor.
- `restart: unless-stopped` em todos os serviços.
- `healthcheck` no serviço web. O deploy espera ele ficar `healthy` antes de
  terminar.
- Nada de container `postgres`.

**Também observe:**

- Docker rootless não abre portas abaixo de 1024 no host. Não há necessidade:
  o Caddy faz o 80/443.
- A VPS tem **1 vCPU**: `cpus` (ou `deploy.resources.limits.cpus`) acima de
  `1.0` faz o Docker recusar o container.
- Dentro do container a aplicação pode escutar em qualquer porta (ex.: `8080`).
- Saída para a internet funciona normalmente. Para falar com outro projeto, use
  o domínio público dele.
- Logs vão para stdout/stderr. O Docker faz a rotação (20 MB × 5).
- Uploads e arquivos de usuário: prefira S3/R2. Volume local não tem backup.
- Workers e filas: são outros serviços no mesmo compose, com a mesma imagem e
  outro `command`. Não publique portas para eles.
- Cron: use um serviço com `supercronic` ou um loop. Não há crontab por usuário.

### Modelo

```yaml
services:
  web:
    image: ${IMAGE:?defina IMAGE}
    restart: unless-stopped
    env_file:
      - path: .env.zalk
      - path: .env
        required: false
    environment:
      PORT: "8080"
    ports:
      - "127.0.0.1:${APP_PORT}:8080"
    healthcheck:
      test: ["CMD-SHELL", "wget -qO- http://127.0.0.1:8080/health || exit 1"]
      interval: 10s
      timeout: 3s
      retries: 5
      start_period: 30s

  worker:                       # opcional
    image: ${IMAGE:?defina IMAGE}
    restart: unless-stopped
    command: ["node", "worker.js"]
    env_file:
      - path: .env.zalk
      - path: .env
        required: false
```

## Banco de dados

- Use `DATABASE_URL`, ou `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASSWORD`,
  vindos do `.env.zalk`. Não grave a senha em outro lugar.
- A role é **dona da base e do schema `public`**: pode criar tabelas, schemas e
  extensões *trusted* (`pgcrypto`, `uuid-ossp`, `citext`, `pg_trgm`, `hstore`,
  `unaccent`, ...).
- A role **não** é superuser. Extensões não-trusted (PostGIS, pgvector,
  `pg_stat_statements` etc.) precisam ser pedidas ao administrador.
- Limite de 20 conexões por role, por padrão. Configure o pool da aplicação
  abaixo disso, contando todas as réplicas e workers.
- **Migrations:** ponha num serviço com `profiles: [predeploy]`. O `zalk-deploy`
  roda esses serviços com a imagem nova (`run --rm`) antes do `up`; se falharem,
  a versão em execução continua no ar. Precisam ser idempotentes e compatíveis
  com a versão anterior: o `--rollback` volta o código, nunca o banco.
- Não há backup por agente: é automático. Para restaurar, peça ao administrador
  (`zalk-backup restore-dump`); a restauração cria uma base nova e nunca
  sobrescreve a atual.

## Deploy

Fluxo padrão, pelo GitHub Actions do repositório do projeto (o kit está em
`templates/project-kit` no repo ZalkVPS):

1. **Build:** gerar a imagem e publicar em `ghcr.io/<owner>/<repo>:<sha>`.
2. **Envio:** por SSH, como o usuário do ambiente (chave em
   `secrets.ZALK_SSH_KEY`), copiar o `docker-compose.yml` e gravar o `.env` a
   partir de `secrets.APP_ENV_FILE`.
3. **Login no registry:** `docker login ghcr.io` com o `GITHUB_TOKEN`.
4. **Subida:** `zalk-deploy ghcr.io/<owner>/<repo>:<sha>`. Ele roda o
   `zalk-check`, faz `pull`, roda o `predeploy` e `up --wait` e, se falhar,
   mostra os logs.

Os ambientes são separados por GitHub Environment (`prod`, `staging`, ...). Cada
um tem seus próprios secrets e aponta para o usuário `<projeto>-<env>`.

Deploy manual é igual: entre por SSH como `<projeto>-<env>` e rode os mesmos
comandos dentro do diretório do ambiente.

## O que um agente NÃO deve fazer

- **Mexer no projeto ZalkVPS.** Agente de outro projeto não altera nada no
  repositório ZalkVPS (nem num clone local dele): nada de editar arquivos,
  commitar, abrir PR, rodar `make`, Ansible ou Terraform, ou mexer em segredos.
  Isso vale mesmo que o repositório esteja acessível e mesmo que a mudança
  pareça pequena. Precisa de algo da plataforma? Descreva o pedido ao
  administrador (veja "Pedidos ao administrador").
- Usar `sudo` ou tentar virar root. O usuário do ambiente não tem esse acesso,
  por desenho.
- Editar `.env.zalk`, `AGENTS.md` ou `CLAUDE.md`. Eles são regenerados.
- Instalar pacotes no host, mexer no Caddy ou no Postgres, abrir portas no
  firewall. Tudo isso é feito pelo administrador, via IaC (repositório ZalkVPS,
  arquivo `ansible/projects.yml`).
- Subir serviços fora do compose do ambiente, por exemplo `docker run` solto.
- Trocar domínio, porta ou limites. Para isso, peça ao administrador.

## Pedidos ao administrador

Tudo o que é de plataforma vira uma alteração em `ansible/projects.yml`
seguida de `make apply`:

- novo ambiente;
- domínio próprio (o DNS do domínio precisa apontar para o IP da VPS antes);
- mais RAM ou CPU;
- extensão do Postgres;
- mais conexões.
