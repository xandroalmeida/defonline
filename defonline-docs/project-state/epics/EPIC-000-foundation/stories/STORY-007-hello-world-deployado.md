---
story_id: STORY-007
slug: hello-world-deployado
title: Hello world deployado em homologação via pipeline
epic_id: EPIC-000
sprint_id: null
type: implementation
target_role: programador
status: draft
owner_agent: null
created_at: 2026-05-20
updated_at: 2026-05-20
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
(preenchido durante/após execução)

### Decisões tomadas
- <data> — <decisão local>

### Descobertas
- <data> — <descoberta>

### Bloqueios encontrados
- <data> — <bloqueio>

### IDRs criados
- <id> — <título>

### Cobertura final
- Unitários: <%>
- E2E: <cenários>

### Links de evidência
- PR: <url>
- Pipeline: <url>
- Deploy de homologação: <url>
