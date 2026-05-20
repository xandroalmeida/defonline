---
story_id: STORY-001
slug: spike-stack
title: SPIKE — Decisão de stack (linguagem, framework, runtime, ORM, testes, auth padrão)
epic_id: EPIC-000
sprint_id: null
type: spike
target_role: arquiteto
status: ready
owner_agent: null
created_at: 2026-05-20
updated_at: 2026-05-20
estimated_session_size: L
---

# STORY-001 — SPIKE de stack

> **Para o agente Arquiteto que vai executar:** leia esta estória por inteiro antes de começar. Esta é uma spike — você produz uma ADR `accepted` ao final, não código de produção. Se algo estiver ambíguo, registre dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto (por que esta estória existe)

O reset técnico de 19/05/2026 zerou as decisões de stack do projeto pré-reset. A regra de negócio (especificação V2.5) permanece válida; a fundação técnica precisa ser refeita a partir do zero. O EPIC-000 não fecha sem stack ratificada por ADR, e todos os demais épicos da WAVE-2026-01 dependem desta decisão para começar.

A herança técnica que persiste do projeto pré-reset: PostgreSQL como banco principal (cobertura desta estória — ratificação) e TDD+E2E como exigência inegociável (cobertura desta estória — ratificação como padrão da stack). Sem ADR formal, a herança não tem rastreabilidade técnica.

- Épico: `epics/EPIC-000-foundation/epic.md`
- PDR de escopo: `decisions/pdr/PDR-001-escopo-wave-2026-01.md`
- Documentos canônicos a ler ANTES de decidir:
  - `defonline-docs/skills/arquiteto/SKILL.md`
  - `defonline-docs/skills/arquiteto/references/architecture-principles.md` (princípios não-negociáveis)
  - `defonline-docs/skills/arquiteto/references/adr-types.md` — Tipo 1 Stack (perguntas centrais, armadilhas, mini-checklist)
  - `defonline-docs/skills/arquiteto/references/adr-lifecycle.md`
  - `defonline-docs/skills/arquiteto/templates/adr.md`
  - `defonline-docs/especificacao/V2/arquitetura-tecnica.md` (panorâmico — regras de negócio preservadas; decisões de stack a refazer)
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` (NFRs aplicáveis: §1, §9.2)
  - `defonline-docs/skills/po/references/quality-standards.md` (TDD+E2E como exigência do PO)

## O quê (objetivo desta estória)

Produzir **ADR `accepted`** definindo a stack do DEFOnline pós-reset: linguagem(s), framework opinativo principal, runtime/plataforma de execução, ORM ou query layer, ferramenta de testes (unit + E2E), gerenciador de dependências e estratégia de autenticação/sessão **padrão do framework escolhido** (sem decisões avançadas de MFA ou social — só o básico).

A ADR deve ratificar formalmente PostgreSQL como banco principal e TDD+E2E como padrões de qualidade da stack — sem ADR, a herança não tem rastreabilidade.

## Por quê (valor para o usuário)

Sem stack decidida, nenhum agente programador consegue começar EPIC-001/002/003. Esta spike destrava todas as outras estórias do EPIC-000 (com exceção das spikes heterogêneas, que rodam em paralelo) e todas as estórias dos épicos seguintes da onda.

## Critérios de aceite

Cada item é observável pelo PO ao revisar o entregável.

- [ ] **CA-1:** ADR redigida usando `defonline-docs/skills/arquiteto/templates/adr.md`, com `type: stack`, salva em `defonline-docs/project-state/decisions/adr/ADR-XXX-stack.md` (numeração sequencial — provavelmente ADR-001 se for a primeira a ser aceita).
- [ ] **CA-2:** ADR cobre integralmente o mini-checklist do `adr-types.md` Tipo 1 Stack: linguagem + framework + runtime nomeados e justificados; mínimo de 2 alternativas reais consideradas + status quo; como atende cada um dos princípios centrais do `architecture-principles.md`; como E2E em browser real funciona; como ambiente local sobe em ≤ 1 comando; estimativa de custo recorrente em ordem de magnitude; spike de "hello world" proposto ou justificativa para não ter.
- [ ] **CA-3:** ADR ratifica explicitamente: (i) PostgreSQL como banco principal (decisão herdada); (ii) TDD + E2E como exigência da stack (decisão herdada). Cada ratificação tem 1 parágrafo justificando.
- [ ] **CA-4:** ADR explicita estratégia de **autenticação padrão do framework**: sessão (cookie/JWT), hashing de senha, expiração, recuperação básica (mesmo que stub neste momento). MFA, social login e recuperação por email avançada ficam **fora do escopo** desta ADR.
- [ ] **CA-5:** ADR inclui prova de conceito mínima — script ou repositório de spike (não comitar em main; pode ser branch ou repo separado) demonstrando: framework subindo localmente em ≤ 1 comando, exemplo de teste unitário rodando, exemplo de teste E2E em browser real rodando, conexão com PostgreSQL local funcionando.
- [ ] **CA-6:** ADR submetida ao PO (Alexandro) para revisão. Status no frontmatter da ADR começa como `proposed`; mudança para `accepted` é feita pelo PO após confirmação explícita.
- [ ] **CA-7:** `index.json` atualizado com a ADR (entrada em `decisions.adr[]`).

## Fora de escopo

- Decidir topologia macro do sistema (monolito modular vs separação) — vive em STORY-002.
- Detalhes de persistência (migrations, multi-tenancy, audit log) — vivem em STORY-003.
- Decisões de cloud / IaC / 3 ambientes — vivem em STORY-004.
- Decisões de CI/CD — vivem em STORY-005.
- Decisões de observabilidade — vivem em STORY-006.
- Decisões avançadas de autenticação (MFA, SSO, social login) — roadmap pós-v1.
- Stack de frontend separada — se a decisão de framework couber em "FE + BE no mesmo framework opinativo" (princípio #4 do Arquiteto), tudo bem; se exigir FE separado, escolher também como parte desta ADR.

## Padrões de qualidade exigidos

Esta é **estória de spike** — exceção explícita aos padrões padrão do PO conforme `defonline-docs/skills/po/references/quality-standards.md`:

- **Sem exigência de cobertura unitária e E2E** sobre código de produção (não há código de produção entregue por esta estória; apenas ADR + prova de conceito local descartável).
- **Exigência mantida:** rigor de fundamentação da ADR (alternativas reais, justificativa explícita, mini-checklist completo).
- **Exigência adicional:** o spike de prova de conceito (CA-5) precisa subir em ≤ 1 comando — não vale "depois eu documento". O comando vai para a ADR.

## Dependências

- **Bloqueada por:** nada. Esta é spike de fundação que destrava o resto.
- **Bloqueia:** STORY-007 (hello world deployado depende de stack decidida).
- **Pode rodar em paralelo com:** STORY-002, STORY-003, STORY-004, STORY-005, STORY-006 (todas heterogêneas entre si, embora algumas tenham acoplamento leve — sinalizado nas próprias estórias).
- **Pré-requisitos de ambiente:** nenhum — o Arquiteto trabalha localmente para esta spike.

## Decisões já tomadas (não as reabra)

- PDR-001 — escopo da WAVE-2026-01 (cobertura: setor Indústria, sem cobrança, sem PDF nesta onda).
- **Herança técnica do projeto pré-reset (RATIFICAR formalmente nesta ADR, não revogar):**
  - PostgreSQL como banco principal.
  - TDD + E2E como exigência inegociável de qualidade.
- Visão de produto (`product/vision.md`) e north star (`product/north-star.md`) — guiam priorização, não decisão técnica.

## Liberdade técnica do agente Arquiteto

Você (Arquiteto) decide:
- Linguagem, framework, runtime — desde que atendam aos princípios do `architecture-principles.md` e ao mini-checklist do Tipo 1 Stack.
- Como dividir FE/BE (se dividir).
- Ferramentas de teste específicas.
- Como estruturar o spike de prova de conceito.

Você (Arquiteto) NÃO decide:
- Banco principal (PostgreSQL já fixado — ratifique).
- Padrões de qualidade exigidos pelo PO (TDD + E2E — ratifique).
- Decisões dos outros tipos (topologia, persistência detalhada, infra, CI/CD, observabilidade) — outras spikes.

Se durante a execução você perceber que uma decisão **invade** o escopo de outra spike, **pare e registre** em "Notas do agente". Não escreva sobre topologia/persistência aqui — deixe para a spike correta.

## Definição de Pronto (DoD)

- [ ] Todos os critérios de aceite acima satisfeitos.
- [ ] ADR `proposed` redigida em `decisions/adr/ADR-XXX-stack.md`.
- [ ] Spike de prova de conceito local funcionando (CA-5) — instruções reproduzíveis na ADR.
- [ ] `index.json` atualizado com a ADR.
- [ ] Estória atualizada com a seção "Notas do agente" preenchida.
- [ ] Estória marcada como `in_review` (não `done` — PO precisa aceitar a ADR antes).

## Protocolo do agente (obrigatório)

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Em resumo:

1. **Ao iniciar:** edite o frontmatter desta estória: `status: in_progress`, `owner_agent: <seu identificador>`, `updated_at: <hoje>`. Atualize `index.json`.
2. **Durante:** mantenha TaskList interna; redija a ADR incrementalmente; quando estiver propondo escolha, valide com spike de prova de conceito antes de marcar como `proposed`.
3. **Se travar:** edite frontmatter para `status: blocked` e descreva o bloqueio em "Notas do agente". Não invente decisão de produto — escale para o PO.
4. **Decisões técnicas de detalhe** que surgirem durante a spike (ex: padrão específico de estrutura de pastas) — não entram nesta ADR; vão para IDR depois, na execução real, conforme `decisions/idr/`.
5. **Ao terminar:** preencha "Notas do agente" abaixo, marque `status: in_review`, atualize `index.json`. A spike só fecha em `done` após PDR/PO aceitar a ADR (mudar status da ADR para `accepted`).

## Notas do agente (preenchido durante/após execução)

### Decisões tomadas
- <data> — <decisão tomada na spike, ex: "framework escolhido X porque Y">

### Descobertas
- <data> — <surpresa, gotcha, item relevante para PO/outros agentes>

### Bloqueios encontrados
- <data> — <bloqueio> — <como foi resolvido ou está aberto>

### ADR produzida
- ADR-XXX — <título> — `decisions/adr/ADR-XXX-<slug>.md`

### Links de evidência
- Spike de prova de conceito: <branch / repositório / commit>
- ADR aceita: <link após aceite do PO>
