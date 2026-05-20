---
story_id: STORY-XXX
slug: nome-curto-da-estoria
title: Título humano da estória
epic_id: EPIC-XXX
sprint_id: SPRINT-YYYY-WNN  # opcional até a estória entrar em sprint
type: implementation  # implementation | spike | validation | enablement | bugfix
target_role: programador  # programador | arquiteto | validador
status: ready  # ready | in_progress | blocked | in_review | done | abandoned
owner_agent: null  # preenchido quando um agente assume
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
estimated_session_size: M  # S (até ~30min) | M (até ~2h) | L (até ~4h, considere quebrar)
---

# STORY-XXX — <título>

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Ela contém tudo o que você precisa. Se algo estiver ambíguo, registre a dúvida na seção "Notas do agente" no final e pause em vez de adivinhar.

## Contexto (por que esta estória existe)

<1–2 parágrafos. Por que isso é importante para o usuário e como se encaixa no épico.>

- Épico: `epics/EPIC-XXX-<slug>/epic.md`
- Documentos canônicos a ler ANTES de codificar:
  - `defonline-docs/especificacao/V2/<arquivo>.md` (seções <X.Y>)
  - <outros>

## O quê (objetivo desta estória)

<Frase única: "implementar/criar/expor <recurso> de modo que <comportamento observável>".>

## Por quê (valor para o usuário)

<Conecte com a métrica do épico. Evite "para destravar a próxima estória" como única justificativa.>

## Critérios de aceite

Cada item é uma asserção testável. O agente DEVE escrever testes que cubram cada um.

- [ ] **CA-1:** Dado <pré-condição>, quando <ação>, então <resultado observável>.
- [ ] **CA-2:** ...
- [ ] **CA-3:** ...

## Fora de escopo

<O que NÃO fazer nesta estória — para evitar inflar a sessão.>

## Padrões de qualidade exigidos

Esta estória segue os padrões em `defonline-docs/skills/po/references/quality-standards.md`. Resumo aplicável:

- **Cobertura de testes unitários:** ≥ 80% no código novo desta estória; ≥ 98% no que tocar regras de negócio ou núcleo do sistema.
- **Testes E2E:** se a estória entrega ou modifica um fluxo de usuário, inclua teste E2E que percorre o fluxo no browser (FE web) ou via API (backend).
- **Sem código não testado** entregue ao final.
- **Automação:** qualquer setup novo (ambiente, migração, deploy) precisa estar automatizado, não documentado como "passo manual".

## Dependências

- **Bloqueada por:** <story_ids ou ADRs>
- **Bloqueia:** <story_ids>
- **Pré-requisitos de ambiente:** <ex: ambiente de homologação operante, banco PostgreSQL provisionado>

## Decisões já tomadas (não as reabra)

<Liste PDRs/ADRs que esta estória deve respeitar. Ex:>
- ADR-003: <decisão arquitetural> → `decisions/adr/ADR-003-<slug>.md`
- PDR-002: <decisão de produto> → `decisions/pdr/PDR-002-<slug>.md`

## Liberdade técnica do agente

Você (agente programador) decide:
- Como estruturar o código (pastas, módulos, classes).
- Quais bibliotecas internas usar dentro das decisões já tomadas.
- Refatorações locais necessárias para deixar o código limpo.
- O design dos testes unitários e E2E.

Você (agente programador) NÃO decide:
- Linguagem, framework principal, banco — definidos em ADRs.
- Padrões de qualidade — definidos pelo PO.
- Critérios de aceite — definidos pelo PO.

Se durante a execução você perceber que uma decisão arquitetural é necessária e não há ADR cobrindo, **pare e registre** na seção "Notas do agente" — não decida sozinho.

## Definição de Pronto (DoD)

- [ ] Todos os critérios de aceite passam.
- [ ] Testes unitários escritos e passando, atingindo as coberturas exigidas.
- [ ] Teste E2E (se aplicável) escrito e passando em homologação.
- [ ] Pipeline de CI verde no PR/branch.
- [ ] Deploy automatizado para homologação realizado e verificado.
- [ ] Documentação atualizada (se aplicável): README do componente, OpenAPI, etc.
- [ ] IDR registrado se houve descoberta técnica relevante ou decisão de baixo nível com impacto futuro.
- [ ] `index.json` atualizado: status = `done`.
- [ ] Esta estória atualizada com a seção "Notas do agente" preenchida.

## Protocolo do agente (obrigatório)

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Em resumo:

1. **Ao iniciar:** edite o frontmatter desta estória: `status: in_progress`, `owner_agent: <seu identificador/sessão>`, `updated_at: <hoje>`. Atualize `index.json` também.
2. **Durante:** mantenha uma TaskList interna; commits pequenos e nomeados; nunca pule testes.
3. **Se travar:** edite frontmatter para `status: blocked` e descreva o bloqueio em "Notas do agente". Não invente decisão de produto/arquitetura.
4. **Decisões técnicas de baixo nível** com impacto futuro vão em um IDR (`decisions/idr/IDR-XXX-<slug>.md`).
5. **Ao terminar:** preencha "Notas do agente" abaixo, marque `status: in_review`, atualize `index.json`, abra PR. O épico só fecha após o validador.

## Notas do agente (preenchido durante/após execução)

> Esta seção é a memória da estória. Preencha conforme executa. Não apague o que você escreveu — adicione.

### Decisões tomadas
- <data> — <decisão local, ex: "escolhi a estrutura de pastas X porque Y">

### Descobertas
- <data> — <surpresa técnica, gotcha, item para o PO/Arquiteto saberem>

### Bloqueios encontrados
- <data> — <bloqueio> — <como foi resolvido ou está aberto>

### IDRs criados
- IDR-XXX — <título> — `decisions/idr/IDR-XXX-<slug>.md`

### Cobertura final
- Unitários: <%>
- E2E: <quantos cenários, link para evidência>

### Links de evidência
- PR: <url>
- Pipeline: <url>
- Deploy de homologação: <url>
