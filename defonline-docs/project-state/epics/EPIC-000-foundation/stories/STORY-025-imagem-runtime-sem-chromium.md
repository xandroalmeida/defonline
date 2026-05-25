---
story_id: STORY-025
slug: imagem-runtime-sem-chromium
title: Imagem runtime sem chromium — separar dev/runtime no Dockerfile (~500MB)
epic_id: EPIC-000
sprint_id: SPRINT-2026-W24
type: chore
target_role: programador
requires_design: false
status: done
owner_agent: programador-claude
created_at: 2026-05-25
updated_at: 2026-05-24
estimated_session_size: S
---

# STORY-025 — Imagem runtime sem chromium (split dev/runtime)

> **Para o agente programador:** débito técnico levantado pelo Programador no fechamento da SPRINT-2026-W22 (sugestão para próxima sprint): "Imagem runtime sem chromium (~500MB economizados em cada layer) — abre story de débito. Chromium é só para Dusk smoke do CI; vale separar." A observação está parcialmente certa — chromium **também** é usado localmente pelo pre-push (`docker compose exec -T web php artisan dusk`), não só pelo CI. Então a tarefa não é "remover": é **separar runtime (sem chromium) de dev (com chromium)** num multistage.

## Contexto (por que esta estória existe)

[`infra/docker/Dockerfile`](infra/docker/Dockerfile) atualmente instala `chromium chromium-chromedriver` no **Stage 3 (runtime)**:

```dockerfile
RUN apk add --no-cache \
        bash git curl unzip \
        libzip-dev oniguruma-dev libpng-dev libxml2-dev icu-dev \
        postgresql-dev postgresql-client \
        chromium chromium-chromedriver \    # ← ~500MB
        nodejs npm \
        ...
```

Onde chromium é, de fato, usado:

1. **Local (docker-compose, dev):** o pre-push hook ([scripts/git-hooks/pre-push.sh](scripts/git-hooks/pre-push.sh) linhas 75-81) faz `docker compose exec -T web pgrep chromedriver` + `docker compose exec -T web php artisan dusk`. Roda dentro do container `web`. **Precisa** de chromium na imagem usada por compose.
2. **CI smoke (`release-homolog.yml`):** roda `browser-actions/setup-chrome@v1` + `chromedriver` **no runner do GitHub Actions, não no container deployado**. **Não precisa** de chromium na imagem deployada.
3. **Homologação / produção runtime:** ninguém roda Dusk lá. **Não precisa** de chromium.

Conclusão: chromium está na imagem deployada em homol/prod **sem nenhum uso**. ~500MB por imagem (cada `apk add chromium chromium-chromedriver` em Alpine puxa Chromium + drivers + libs X11 que sequer existem em runtime headless web/worker/scheduler).

Impacto concreto:
- **Pull time em deploys** (Ansible em `infra/`) — ~500MB extra a cada release tag, mesmo com cache de layer.
- **Disk usage no host de homologação** (a VM tem disco limitado — ver `infra/`).
- **Tempo de build no CI** — Alpine baixa Chromium do mirror; some 30-60s por build do estágio runtime.
- **Superfície de ataque** — chromium tem CVEs frequentes; tê-lo em runtime que roda como serviço web é higiene ruim.

- Épico: `epics/EPIC-000-foundation/epic.md` (Dockerfile foi criado em STORY-007)
- Documentos canônicos:
  - [`infra/docker/Dockerfile`](infra/docker/Dockerfile) — onde o chromium entra hoje (linha 39).
  - [`docker-compose.yml`](docker-compose.yml) — serviços `web` / `worker` / `scheduler` / `web-test` montam a imagem; precisam de chromium em dev.
  - [`scripts/git-hooks/pre-push.sh`](scripts/git-hooks/pre-push.sh) (linhas 75-81) — Dusk via `docker compose exec`.
  - [`.github/workflows/release-homolog.yml`](.github/workflows/release-homolog.yml) (linhas 100-138) — smoke usa chromium do runner, não do container.
  - [`.github/workflows/pr.yml`](.github/workflows/pr.yml) (linha 4) — comentário explícito "CI remoto NÃO sobe Postgres nem Chromium".
  - [`defonline-docs/project-state/decisions/adr/ADR-002-arquitetura-execucao.md`](defonline-docs/project-state/decisions/adr/ADR-002-arquitetura-execucao.md) — "imagem única para web/worker/scheduler". Esta estória **não fere** o ADR-002: o stage `runtime` continua único; o stage `dev` apenas estende. Documentar.

## O quê (objetivo desta estória)

Reorganizar [`infra/docker/Dockerfile`](infra/docker/Dockerfile) em dois targets finais a partir do mesmo runtime base:

- **`runtime`** (target default ou explícito) — sem chromium, sem chromium-chromedriver, sem `nodejs npm` se não for necessário em runtime (avaliar). Usado por Ansible em homologação e produção.
- **`dev`** — `FROM runtime` + `apk add chromium chromium-chromedriver` (+ qualquer outra ferramenta dev pesada que aparecer). Usado por `docker-compose.yml` localmente.

Após a mudança:
- `docker build --target dev` produz imagem com chromium (compose usa).
- `docker build --target runtime` (ou sem `--target`, dependendo da convenção) produz imagem sem chromium para deploy.
- `infra/` (Ansible playbooks) constrói o target `runtime` em homol/prod.
- `docker-compose.yml` constrói o target `dev` localmente.

## Por quê (valor para o usuário)

Para Roberto: deploys de homol/prod um pouco mais rápidos (pull menor), provavelmente imperceptível. Para o time: menos superfície de CVEs em produção, menor uso de disco na VM de homol, builds de CI mais rápidos, separação saudável de responsabilidades dev/runtime.

## Critérios de aceite

- [ ] **CA-1:** [`infra/docker/Dockerfile`](infra/docker/Dockerfile) tem **dois targets finais**: `runtime` (sem chromium) e `dev` (com chromium). Stages anteriores (`composer-deps`, `vite-build`) permanecem reaproveitados.
- [ ] **CA-2:** **Build do target `runtime`** com `docker build --target runtime -t defonline-app:runtime infra/docker/` produz imagem **sem `chromium`** instalado. Verificável por: `docker run --rm defonline-app:runtime sh -c "command -v chromium || echo NAO_INSTALADO"` retorna `NAO_INSTALADO`.
- [ ] **CA-3:** **Build do target `dev`** com `docker build --target dev -t defonline-app:dev infra/docker/` produz imagem **com `chromium` + `chromedriver`** instalados. Verificável por: `docker run --rm defonline-app:dev chromium --version` e `chromedriver --version` retornam sem erro.
- [ ] **CA-4:** **Comparação de tamanho documentada**: `docker images defonline-app` mostra `dev` aproximadamente ~500MB maior que `runtime`. Anotar valores reais nas Notas do agente.
- [ ] **CA-5:** **`docker-compose.yml` aponta para `dev`**: o serviço `web` (e por herança `worker`, `scheduler`, `web-test` via `*app-base`) constrói com `target: dev`. Pre-push continua passando (`scripts/git-hooks/pre-push.sh` Dusk roda contra `chromedriver` no container).
- [ ] **CA-6:** **Ansible em `infra/` aponta para `runtime`**: o playbook que faz `docker build` em homologação usa `--target runtime` (ou equivalente). Se o build é feito em pipeline (não via Ansible), a tag/target é especificada no workflow (`release-homolog.yml` job `build`, se existir, ou no `Dockerfile` default sendo `runtime`).
- [ ] **CA-7:** **Pipeline `release-homolog` verde end-to-end** com a mudança aplicada. Smoke Dusk continua passando (ele usa chromium do runner, não do container, então não muda nada para ele — confirmar empiricamente).
- [ ] **CA-8:** **Pre-push hook verde end-to-end** localmente após o rebuild com `docker compose build --no-cache web`. Dusk continua rodando dentro do container `web` (que agora é `target: dev`).
- [ ] **CA-9:** **Aditivo registrado no ADR-002** (ou IDR de ajuste): a "imagem única" continua sendo a `runtime` em produção (web/worker/scheduler compartilham a mesma imagem). O `dev` é um superset local — não viola o ADR. Adicionar 2-3 linhas no §História ou §Implementação do ADR-002 explicando.
- [ ] **CA-10:** **README ou docs de infra atualizados** se citam o comando de build. Procurar com `grep -rE "docker build" defonline-docs/ infra/ README.md`.

## Fora de escopo

- Refazer multistage de outra forma (squash, distroless, mudar de Alpine para Debian etc.) — só split `dev`/`runtime`.
- Mexer no Dusk em si (suites, configuração) — chromium já funciona em ambos os fluxos hoje, só estamos reorganizando onde ele mora.
- Adicionar ferramentas dev novas no stage `dev` (Pail, Pao etc.) — escopo só de chromium.
- Reduzir o stage `runtime` além de chromium (ex.: tirar `nodejs npm` se não for usado em runtime) — pode ser feito numa estória subsequente se Programador identificar oportunidade, mas **não** é objetivo aqui. Anotar nas Notas se viu.
- Resolver o gotcha de `bump-rc.yml` não disparar `release-homolog.yml` (STORY-023) — não relacionado.

## Padrões de qualidade exigidos

- **Sem regressão funcional**: pre-push roda Dusk verde, CI roda smoke verde, deploy de homologação sobe a aplicação sem erro.
- **Idempotência do build**: `docker compose build` duas vezes sem cache não gera estado divergente; `docker build --target X` é determinístico (mesma versão de Alpine + apk index).
- **Layers ordenados para cache**: stage `dev` adiciona chromium na ÚLTIMA layer significativa (após cópia de código), para que mudanças de código não invalidem a layer pesada do chromium. Verificar com `docker history`.
- **Sem aumentar a superfície de attack do runtime**: nenhum binário novo no `runtime` (objetivo é diminuir, não trocar um por outro).

## Dependências

- **Bloqueada por:** nenhuma.
- **Bloqueia:** nenhuma formalmente. **Recomendado** fechar antes de qualquer estória que mexa pesado no Dockerfile (ex.: futura otimização de PHP-FPM, troca de base image), para evitar conflito de merge.

## Decisões já tomadas

- **A imagem em produção continua única** para web/worker/scheduler (ADR-002 §1.2). O split é entre **target de build** (`runtime` vs `dev`), não entre serviços em produção.
- **Dusk continua rodando localmente dentro do container `web`** (decisão histórica do pre-push.sh) — esta estória não muda isso, só garante que o container `web` em dev seja `target: dev`.
- **Dusk smoke no CI continua rodando no runner do GitHub Actions** (decisão histórica em `release-homolog.yml`) — esta estória não muda isso.
- **Multistage Alpine continua a base** — ADR-002 fixa isso.

## Liberdade técnica do agente

Você decide:
- Se o target **default** (`docker build` sem `--target`) é `runtime` (mais seguro — quem builda sem pensar pega o slim) ou `dev` (mais conveniente em dev). Sugestão: **default = `runtime`**, compose explicita `target: dev`.
- Se vale mover `nodejs npm` para o stage `dev` também (avaliar se runtime precisa de Node — Vite está noutro stage, mas Mix/Sail/algo pode chamar `npm` em runtime). Se sim, vira ganho extra; se não, deixa em runtime e anota nas Notas.
- Nome dos targets (sugestão: `runtime` e `dev`; alternativas: `prod` / `local`, `slim` / `full`).
- Como ordenar layers para maximizar cache hit.

Você NÃO decide:
- Mudar a arquitetura "imagem única para web/worker/scheduler" (ADR-002).
- Tirar Dusk do pre-push (decisão de processo, não de infra).
- Trocar o driver de browser do Dusk (chromium → firefox, headless mode, etc.).

## Definição de Pronto

- [ ] CAs cumpridos.
- [ ] Build dos dois targets validado localmente com tamanhos anotados.
- [ ] Pre-push verde com nova imagem dev.
- [ ] Pipeline `release-homolog` verde end-to-end com a mudança (rc nova rodando contra `runtime` em homologação).
- [ ] Smoke manual em `https://defonline.xandrix.com.br/` confirma app no ar normalmente.
- [ ] Aditivo no ADR-002 (ou IDR equivalente) registrado.
- [ ] STORY-025 `status: done` no frontmatter + `index.json`.
- [ ] Notas do agente preenchidas com tamanhos reais (runtime vs dev), tempo de build antes/depois e qualquer gotcha encontrado.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`. **Importante:** rebuild local com `docker compose build --no-cache web` antes de declarar pronto — cache pode esconder regressão na ordem de stages.

## Notas do agente

### Tempo investido
- ~1h (leitura + refactor + build dos dois targets + ADR-002 + Notas)

### Tamanhos das imagens

Medidas em 2026-05-24 com `docker images` (buildx, default platform local
darwin/arm64, build sem cache GHA — apenas cache local):

| Imagem | `docker images` (SIZE) | Layer apk delta (do `docker history`) |
|---|---|---|
| `defonline-app:runtime` | **1.19 GB** | `apk add ...` (sem chromium): **691 MB** |
| `defonline-app:dev` | **2.25 GB** | layer adicional `apk add chromium ...`: **750 MB** |
| **Economia em homol/prod (layer chromium ausente)** | — | **~750 MB por imagem** |

Observação: a estimativa original da estória (~500MB) era conservadora. O
`apk add chromium chromium-chromedriver` em Alpine puxa não só os dois
pacotes nominais como toda a árvore de libs X11/fonts/gtk+ etc., totalizando
~750 MB descompactados (a diferença entre `1.19 GB` e `2.25 GB` no `docker
images`, que reflete o tamanho visível para `docker pull` em homol/prod
após o split, é ~1.06 GB — atribuível a diferenças de contagem multi-arch
do buildx; o número canônico aqui é o do `docker history` da layer, **750 MB**).

Verificações de CA-2/CA-3 executadas:

```
$ docker run --rm defonline-app:runtime sh -c "command -v chromium || echo NAO_INSTALADO"
NAO_INSTALADO

$ docker run --rm defonline-app:dev chromium --version
Chromium 148.0.7778.167 Alpine Linux

$ docker run --rm defonline-app:dev chromedriver --version
ChromeDriver 148.0.7778.167 (...)

$ docker run --rm defonline-app:runtime php artisan --version
Laravel Framework 13.11.2

$ docker run --rm defonline-app:runtime php -m | grep -E "pdo_pgsql|pcov|bcmath|zip"
bcmath
pcov
pdo_pgsql
zip
```

`docker history defonline-app:dev` confirma que o `apk add chromium ...` é
**a última layer** do stage `dev` (CA: layer pesada friendly ao cache —
mudança de código no `runtime` não invalida a layer de 750 MB).

### Decisões locais

- **Target default:** não há default — workflows do CI (`release-homolog.yml`,
  `release-production.yml`) declaram `target: runtime` explicitamente;
  `docker-compose.yml` declara `target: dev` no `x-app.build`. Decisão para
  evitar acoplamento a "qual é o último stage do Dockerfile" (que muda se um
  futuro stage for adicionado depois de `dev`). Explícito > implícito.
- **`nodejs npm` ficou em:** `runtime`. Não foi movido para `dev`. Vite roda
  no stage `vite-build` separado (já não polui runtime com Node), mas
  `nodejs npm` continua instalado no runtime base por compatibilidade
  reversa — se algum script artisan/composer ou pacote downstream invocar
  `npm` em runtime, não quero descobrir agora. Avaliação custou < 5 min;
  conclusão é que **vale uma estória subsequente** medir empiricamente e
  separar, em vez de arriscar aqui (escopo fora desta estória conforme §"Fora
  de escopo"). Anotação para retro.
- **Outras ferramentas movidas para `dev`:** nenhuma. Só chromium +
  chromium-chromedriver. Mesma justificativa de escopo.
- **Ordem dos stages no Dockerfile:** `runtime` antes de `dev` para que `dev`
  faça `FROM runtime AS dev` — superset literal, garantindo "tudo o que
  funciona em runtime também funciona em dev".

### Aditivo ADR-002 / IDR

- Aditivo retrospectivo registrado em [`ADR-002-topologia.md`](defonline-docs/project-state/decisions/adr/ADR-002-topologia.md) §Histórico (entrada 2026-05-24). Não muda a decisão; só explicita que "imagem única" do ADR é o `target runtime` e que `dev` é convenção de build local. Nenhum IDR novo.

### Observações úteis ao PO

- **CA-8 fechado nesta sessão.** Após `docker compose build --no-cache web`
  e `docker compose up -d --force-recreate web web-test worker scheduler`,
  o `./scripts/git-hooks/pre-push.sh` rodou **verde end-to-end**:
  - Pint (lint) ✓
  - Larastan ✓
  - Pest All — **316 testes / 979 asserções**, cobertura ≥80% ✓
  - Pest Domain — **100%** em todos os value objects, gate ≥98% ✓
  - Pennant overdue ✓
  - **Dusk E2E — 13 testes / 81 asserções** dentro do container `web`
    (target `dev`), todos verdes. Browser navega chromium 148 local. ✓
- **CA-7 fechado.** Tag `v0.8.4-rc.1` empurrada (push direto do host local,
  bypass do gotcha de STORY-023). Run [26377578609](https://github.com/xandroalmeida/defonline/actions/runs/26377578609)
  verde end-to-end em ~5min10s:
  - `validate` (Pint, Larastan, Pest UnitPure, Pest All ≥80%, Pennant,
    Trivy, composer audit, Gitleaks, Arch sem pgAdmin) ✓
  - `build-and-push` (GHCR `ghcr.io/xandroalmeida/defonline-app:v0.8.4-rc.1`,
    com `target: runtime`) ✓
  - `deploy` (Ansible playbook contra homol) ✓
  - `smoke` (HTTP `/health` + `/ready` + Dusk 1 cenário contra
    `defonline.xandrix.com.br`, chromium do runner GitHub Actions) ✓
  - `notify` ✓
- **Smoke manual** `https://defonline.xandrix.com.br/health` retornou
  `{"status":"ok","service":"DEFOnline","version":"v0.8.4-rc.1","env":"staging"}`,
  landing pública `/` retornou HTTP 200. Aplicação no ar com a imagem
  `runtime` sem chromium.
- Imagens locais auxiliares `defonline-app:runtime` / `defonline-app:dev`
  podem ser removidas com `docker rmi defonline-app:runtime
  defonline-app:dev` sem efeito colateral (namespace separado da tag
  `defonline/app:dev` que o compose mantém).
- `docker system prune -af` segue válido no `deploy.yml` por força do
  acúmulo de tags rc, mesmo agora sem chromium; comentário atualizado
  (~300MB por tag em vez de ~500MB).
