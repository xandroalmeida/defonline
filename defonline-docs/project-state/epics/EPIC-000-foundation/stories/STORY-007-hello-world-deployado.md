---
story_id: STORY-007
slug: hello-world-deployado
title: Hello world deployado em homologação via pipeline
epic_id: EPIC-000
sprint_id: null
type: implementation
target_role: programador
status: in_progress
owner_agent: programador (claude-opus-4-7)
created_at: 2026-05-20
updated_at: 2026-05-22
estimated_session_size: L
---

# STORY-007 — Hello world deployado em homologação

> **Para o agente Programador que vai executar:** leia esta estória por inteiro e leia as ADRs listadas em "Decisões já tomadas" ANTES de codificar. Esta estória implementa o **mínimo absoluto** que materializa as decisões arquiteturais — não vá além do que está nos CAs.

## Contexto

EPIC-000 só está `done` quando "merge na main faz deploy automático de uma página viva em homologação". Esta estória entrega isso: uma página mínima — exibindo nome do produto, versão deployada e healthcheck — rodando em URL pública de homologação, via pipeline automatizado, sobre a stack definida nas ADRs do EPIC-000.

Não é uma feature de produto. É a prova viva de que a fundação técnica funciona: stack, topologia, persistência (com migration inicial), infra, CI/CD e observabilidade estão **realmente em pé**, não só descritos em ADR.

- Épico: `epics/EPIC-000-foundation/epic.md`
- PDR de escopo: `decisions/pdr/PDR-001-escopo-wave-2026-01.md`
- Documentos canônicos a ler ANTES de codificar:
  - **Todas as ADRs aceitas** do EPIC-000 (Stack, Topologia, Persistência, Infra, CI/CD, Observabilidade) — citadas em "Decisões já tomadas".
  - `defonline-docs/skills/programador/SKILL.md`
  - `defonline-docs/skills/programador/references/coding-principles.md`
  - `defonline-docs/skills/programador/references/testing-discipline.md`
  - `defonline-docs/skills/programador/references/observability-discipline.md`
  - `defonline-docs/skills/po/references/quality-standards.md`
  - `defonline-docs/skills/po/references/agent-task-format.md`

## O quê

Materializar a fundação técnica: criar o repositório de código no estado descrito pelas ADRs aceitas; rodar uma migration vazia ou trivial via a ferramenta decidida; subir o pipeline CI/CD; fazer com que um merge na branch principal dispare deploy automático de uma página viva ("hello DEFOnline") em homologação, acessível por URL pública, com healthcheck e log estruturado emitido na inicialização.

## Por quê

Sem esta estória, EPIC-000 fica abstrato — ADRs no papel, nada rodando. Esta é a primeira pegada visível do projeto pós-reset; é o que destrava o EPIC-001 (cadastro do Roberto) e todas as estórias seguintes.

## Critérios de aceite

- [ ] **CA-1:** Há uma página viva acessível em URL pública de homologação (URL decidida na ADR de Infra). A página exibe pelo menos: "hello DEFOnline", número da versão deployada (commit SHA curto ou semver), e indicador `OK` de healthcheck.
- [ ] **CA-2:** Um commit aprovado em PR na branch principal dispara o pipeline; em pipeline verde, o deploy em homologação acontece automaticamente em ≤ 10 minutos, sem intervenção manual.
- [ ] **CA-3:** O pipeline executa, na ordem definida pela ADR de CI/CD: lint + testes unitários + testes E2E mínimo (smoke contra a página viva) + build + deploy. Pipeline vermelho bloqueia o merge.
- [ ] **CA-4:** Ambiente local sobe com **um comando** documentado no README do repositório (princípio #6 do Arquiteto). Esse comando inclui PostgreSQL via Docker (princípio #8).
- [ ] **CA-5:** A migration inicial é aplicada automaticamente no deploy em homologação (mesmo que vazia ou só com a tabela mínima necessária para o `evento_produto` definido na ADR de Observabilidade).
- [ ] **CA-6:** Log estruturado no formato definido pela ADR de Observabilidade é emitido na inicialização; healthcheck (`/health` ou path equivalente) responde 200 OK + JSON com status + versão.
- [ ] **CA-7:** Testes: ≥ 1 teste unitário ilustrativo (mesmo que trivial — exemplo de "função soma" ou similar) cobrindo o setup de testes do projeto. Cobertura geral ≥ 80% no código novo (com hello world trivial, cobertura tende a 100%). ≥ 1 teste E2E que valida a página viva em homologação real (não mock).
- [ ] **CA-8:** README do repositório explica: como subir ambiente local, como rodar testes (unit + E2E), como funcionam os 3 ambientes, onde estão as ADRs.
- [ ] **CA-9:** PR mergeado, pipeline verde, deploy realizado, URL pública acessível e verificada. Esta estória só é `done` após o validador (STORY-008) abrir e confirmar.

## Fora de escopo

- Qualquer funcionalidade voltada a usuário final (cadastro, quiz, relatório). EPIC-001 a EPIC-003.
- Setup de produção real (mas ambientes IaC já provisionando, conforme ADR de Infra).
- Backup/DR formais.
- Termo de Adesão / LGPD (entra em EPIC-001).

## Padrões de qualidade exigidos

Esta estória segue os padrões em `defonline-docs/skills/po/references/quality-standards.md`. Resumo:

- **Cobertura unitária ≥ 80%** no código novo (com hello world trivial, fácil de atender — mas verifique que o exercício do framework de testes está funcional).
- **Pelo menos 1 teste E2E ativo em homologação real** validando a página viva.
- **Sem código não testado** ao final.
- **Toda automação rodando como automação** — nada de "depois eu configuro o deploy via clique".

## Dependências

- **Bloqueada por:** TODAS as 6 spikes (STORY-001 a STORY-006) precisam ter ADRs `accepted` antes desta estória sair de `draft` para `ready`. Quando todas as ADRs estiverem aceitas, o PO promove esta estória para `ready` e atualiza o `index.json`.
- **Bloqueia:** STORY-008 (validação) e todo o EPIC-001 em diante.
- **Pré-requisitos de ambiente:** repositório criado conforme a ADR de Infra; conta no provedor cloud com permissões adequadas.

## Decisões já tomadas (não as reabra)

- PDR-001 — escopo da WAVE-2026-01.
- **As 6 ADRs do EPIC-000** (números reais a confirmar conforme aceite — provavelmente ADR-001 a ADR-006):
  - Stack — ratifica linguagem, framework, runtime, ORM, testes, auth padrão, PostgreSQL, TDD+E2E.
  - Topologia — define componentes e comunicação.
  - Persistência — define modelo de migrations, multi-tenancy, audit, soft delete + LGPD.
  - Infra — define cloud, IaC, 3 ambientes, Docker, custo.
  - CI/CD — define pipeline, branching, deploy, rollback, gates de cobertura.
  - Observabilidade — define logs/métricas/tracing e captura de eventos do north star.

## Liberdade técnica do agente Programador

Você decide:
- Estrutura concreta de pastas e módulos respeitando as ADRs.
- Estrutura específica do `.gitlab-ci.yml` ou `.github/workflows/*.yml`.
- Como organizar os scripts de Docker.
- Naming local de função/variável (snake_case ou camelCase conforme a ADR de Stack).

Você NÃO decide:
- Stack (ADR de Stack).
- Topologia (ADR de Topologia).
- Multi-tenancy / audit / migrations padrão (ADR de Persistência).
- Provedor cloud / IaC (ADR de Infra).
- Pipeline (ADR de CI/CD).
- Stack de observabilidade (ADR de Observabilidade).

**Se durante a execução você perceber que uma decisão arquitetural está faltando ou ambígua, pare e registre** em "Notas do agente". Nunca decida sozinho — escale para o PO/Arquiteto.

## Definição de Pronto (DoD)

- [ ] CA-1 a CA-9 satisfeitos.
- [ ] Testes unitários ≥ 80% no código novo. Testes E2E rodando contra homologação real.
- [ ] Pipeline de CI verde no PR de merge.
- [ ] Deploy automatizado para homologação verificado (URL respondendo).
- [ ] README explicativo no repositório.
- [ ] `index.json` atualizado: `status: in_review` (validador vai confirmar).
- [ ] "Notas do agente" preenchidas com decisões locais, descobertas, links de evidência.

## Protocolo do agente (obrigatório)

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Em resumo:

1. **Ao iniciar:** edite o frontmatter — `status: in_progress`, `owner_agent: <id>`, `updated_at: <hoje>`. Atualize `index.json`.
2. **Durante:** TaskList interna; commits pequenos; nunca pule teste por "pressa".
3. **Se travar:** `status: blocked`, descreva o bloqueio.
4. **Decisões técnicas de baixo nível** com impacto futuro vão em IDR.
5. **Ao terminar:** preencha "Notas do agente", `status: in_review`, atualize `index.json`, abra PR. Validador (STORY-008) confirma.

## Notas do agente

### Estado da execução (2026-05-22)

A execução está **em curso** e foi quebrada em três fases por alinhamento com o PO no início da sessão (estória `L`, escopo absorvendo 6 ADRs simultâneas).

- **Phase 1 — skeleton local rodando (concluída em 2026-05-22):** app Laravel + Livewire + Docker Compose com 5 containers (web/worker/scheduler/db/mailpit), migrations base, observability cross-process, página viva, /health, /ready, suíte Pest + Dusk verde, README + `up.sh`. **Status atual da estória após Phase 1: `in_progress` ainda** — a estória só vai para `in_review` após Phase 3.
- **Phase 2 — CI/CD (pendente):** GitHub Actions workflows (pr.yml + main.yml + release-homolog.yml + release-production.yml) + pre-push hook + Laravel Pennant + lints (Pint, Larastan, ansible-lint, commitlint, trivy, gitleaks, composer audit).
- **Phase 3 — Ansible + deploy real (pendente):** playbooks Ansible (bootstrap, docker, app, deploy, backup, restore), contratar VPS BR, configurar DNS Cloudflare, criar bot Telegram, gerar GPG key, criar bucket Backblaze B2, deploy automatizado em `homolog.defonline.com.br` com TLS via Caddy.

### Decisões tomadas

- **2026-05-22 — Reaproveitar `spike-stack/app/` como base.** Movido para `app/` na raiz; mantém versões já validadas no spike (Laravel 13.11 + Livewire 4.3 + PG 18 + Pest 4.7 + Dusk 8.6 + PHP 8.5.6). Stub do `Counter` removido.
- **2026-05-22 — Imagem Docker única para web/worker/scheduler** (ADR-002). PHP 8.5-cli-alpine + extensões `pdo_pgsql`, `zip`, `bcmath` + Composer + Chromium/ChromeDriver para Dusk. Em local, `php artisan serve` para `web`; em produção (Phase 3) evolui para `nginx + php-fpm`.
- **2026-05-22 — `request_id` UUID v7 (ADR-002) implementado** via `App\Support\RequestId` (singleton de processo) + helper global `request_id()` (autoload `composer.json:autoload.files`) + middleware `AssignRequestId` + middleware `EnrichLogContext` (injeta `Log::withContext`).
- **2026-05-22 — `BaseJob` propaga `meta.request_id`** no construtor e re-injeta `RequestId::set()` + `Log::withContext()` no `handle()` — preserva trace cross-process worker → web (ADR-002).
- **2026-05-22 — Métricas via middleware/listener nativos** (não SaaS): `MeasureRequest` middleware insere em `request_metrics` no `terminate()` (usa `$request->attributes` para sobreviver entre instâncias do middleware); `CollectJobMetrics` listener escuta `JobProcessing/JobProcessed/JobFailed` e insere em `job_metrics`. `business_metrics` é gravada pelos próprios jobs (`HelloWorldEmail` exemplifica).
- **2026-05-22 — `LogSanitizer` único** aplicado via `Log::tap` nos canais `stdout` (JSON) e `daily` (90d) — ADR-003 §LogSanitizer + ADR-004 §1.1. Lista de chaves: credencial → REDACTED total; CPF/CNPJ/email/telefone → máscara parcial; PII derivada/financeiro → REDACTED total.
- **2026-05-22 — `EventLogger::emit()` valida PII na origem** lançando `PiiEmEventoException` quando recebe chave proibida (ADR-004 §2.4). Validação recursiva em arrays aninhados. Defesa em camadas: model `EventoProduto` também rejeita update/delete + GRANT `INSERT, SELECT only` no Postgres.
- **2026-05-22 — `AuditLog` model rejeita update/delete** + GRANT restritivo Postgres (ADR-003 §Decisão 4 + ADR-005 §7.5).
- **2026-05-22 — Migration `usuarios` usa `DB::statement` para coluna CITEXT** porque Laravel Schema Grammar não conhece o tipo. Coluna criada como `ALTER TABLE ADD` após o `Schema::create` Eloquent.
- **2026-05-22 — Extensões Postgres habilitadas no init script** (não em migration). Diverge de ADR-005 §7.3 — ver **IDR-001** (motivo: `pg_stat_statements` exige superuser; conceder `CREATE ON DATABASE` ao `defonline_app` quebra princípio do menor privilégio).

### Descobertas

- **2026-05-22 — Docblock com `*/` literal quebra parse.** Comentário "faturamento_*/balanco_*" fechou o docblock prematuramente, causando ParseError em `LogSanitizer`. Reescrito sem `*/` no texto.
- **2026-05-22 — `composer install` no Dockerfile não é o suficiente** quando se adiciona `autoload.files` (helpers) depois do install. Precisa `composer dump-autoload` após mudança.
- **2026-05-22 — `MeasureRequest` middleware: instâncias separadas entre `handle()` e `terminate()`.** Propriedade tipada `private float $startedAt` falhava com "must not be accessed before initialization". Resolvido armazenando o timestamp em `$request->attributes`.
- **2026-05-22 — `$event->job->getJobId()` retorna `int` em Laravel queue driver `database`.** O type-hint do listener (`string`) explodia. Resolvido com cast `(string)`.
- **2026-05-22 — `.env.dusk.local` precisa de `APP_NAME=DEFOnline`** explícito; sem isso, Dusk pega default `'Laravel'` do `config/app.php` e quebra `assertSee('hello DEFOnline')`. Descoberto via screenshot de Dusk.

### Bloqueios encontrados
- Nenhum bloqueio escalonado. Todos resolvidos no fluxo.

### IDRs criados
- **IDR-001** — Extensões Postgres habilitadas via init script do container, não via migration Laravel.

### Cobertura final (Phase 1)

- **Pest:** 40 testes, 82 asserções, 0,77s — todos verdes.
  - Unit: `LogSanitizer` (8 testes), `RequestId` (5 testes).
  - Feature: `/health` + `/ready` + propagação `X-Request-Id` (4); HelloWorld Livewire (3); `AuditLogger` (3); `EventLogger` + validação PII (16); `RequestIdPropagation` cross-process (2).
- **Dusk E2E:** 2 testes, 6 asserções, 1,02s — browser real Chromium 148 contra `localhost:8000`.
  - Visitor sees hello page with version and OK status.
  - Visitor can dispatch the demo email (web → fila → worker → Mailpit).
- **Validação ao vivo:** `docker compose ps` mostra 5 containers up; `/health` e `/ready` 200; e-mail real chega ao Mailpit com `request_id` no subject; tabelas `evento_produto`, `request_metrics`, `job_metrics`, `business_metrics` populadas.

### Links de evidência (Phase 1)

- PR: pendente (entra após Phase 3 — estória só fecha quando deploy em homologação estiver vivo).
- Pipeline: pendente (Phase 2).
- Deploy de homologação: pendente (Phase 3).
- Página viva local: http://localhost:8090 (após `./up.sh`).
- Mailpit UI: http://localhost:8025.
