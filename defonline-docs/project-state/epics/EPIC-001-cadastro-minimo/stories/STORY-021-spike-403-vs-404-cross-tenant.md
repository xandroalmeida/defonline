---
story_id: STORY-021
slug: spike-403-vs-404-cross-tenant
title: SPIKE — Decidir 403 vs 404 cross-tenant (alinhar STORY-014 com ADR-003/NRF)
epic_id: EPIC-001
sprint_id: null
type: spike
target_role: arquiteto
requires_design: false
status: ready
owner_agent: null
created_at: 2026-05-24
updated_at: 2026-05-24
estimated_session_size: S
---

# STORY-021 — SPIKE: 403 vs 404 cross-tenant

> **Para o agente Arquiteto:** esta é uma estória de **spike curto** (sessão S, ~30 min) para fechar uma divergência arquitetural conhecida e registrá-la em IDR formal. Você **decide** entre dois comportamentos já implementados ou propostos, e **registra a decisão** — não escreve código novo. Se sua decisão exigir mudança em código existente, o seu output é apenas a IDR; a estória de implementação é separada (próximo sprint).

## Contexto (por que esta estória existe)

A STORY-014 (cadastro de Empresa Analisada — done) prescreve em CA-4/CA-6/DoD que acesso cross-tenant à rota `GET /empresas/{id}` retorne **HTTP 403** + entrada em `audit_logs` com `action: 'empresa.acesso_negado'`. O programador implementou exatamente isso ([app/app/Http/Controllers/EmpresaController.php:31-57](app/app/Http/Controllers/EmpresaController.php)).

Porém, **ADR-003 §Decisão 1** (multi-tenancy) e **`requisitos-nao-funcionais-e-juridicos.md` §4.3** prescrevem **HTTP 404** (silencioso, sem audit) para a mesma situação, sob o argumento de "não vazar existência" — princípio de `security-discipline.md`.

A divergência foi:
- Declarada pelo Programador nas Notas da STORY-014 (linha "403 vs 404 não bloqueante; sigo a estória").
- Listada pelo PO na nota do `index.json` da STORY-014.
- Aceita pelo Validador no item 4.6 do `validation/checklist.md` ("Para esta validação, **403 é o critério vigente**").
- Identificada novamente como Observação no `validation/report.md` (item 4.6).

Sem IDR formal escolhendo entre as duas semânticas, **a próxima rota autenticada com objeto pertencente a tenant** (EPIC-002 vai ter várias: `/diagnosticos/{id}`, `/relatorios/{id}`) vai herdar a inconsistência ou decidir por omissão. Esta estória existe para fechar isso **antes** do EPIC-002.

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md` (origem da divergência)
- Documentos canônicos a ler ANTES de decidir:
  - `defonline-docs/project-state/decisions/adr/ADR-003-persistencia.md` (§Decisão 1)
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` (§4.3)
  - `defonline-docs/skills/programador/references/security-discipline.md` (anti-enumeração)
  - `defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/stories/STORY-014-cadastro-empresa-analisada-manual.md` (Notas do agente — argumentação local do programador)
  - [EmpresaController.php:31-57](app/app/Http/Controllers/EmpresaController.php) (implementação atual: 403 + audit)
  - [BelongsToUsuarioScope.php](app/app/Models/Scopes/BelongsToUsuarioScope.php) (Global Scope que cobre listagem)

## O quê (objetivo desta estória)

Registrar **IDR-008** (ou número próximo disponível) escolhendo entre **três opções** para acesso cross-tenant a rotas detail/edit/delete autenticadas com objeto pertencente a outro tenant:

- **Opção A — 404 silencioso** (alinha com ADR-003 + NRF §4.3 + security-discipline anti-enumeração). Exige refactor de `EmpresaController::show` + remoção da action `empresa.acesso_negado` em audit_logs (ou rebaixar para log-only).
- **Opção B — 403 + audit log** (status quo da implementação atual; exige aditivo formal em ADR-003 + NRF). Mantém código atual.
- **Opção C — Híbrido** (404 silencioso para rotas GET; 403 + audit para rotas POST/PUT/DELETE quando o ator de fato conhece o ID alvo). Mais nuançado, exige justificativa de UX/segurança caso a caso.

Você decide. A IDR justifica e prescreve o caminho.

## Por quê (valor para o usuário)

Para o produto: consistência. Roberto não percebe diretamente, mas se duas rotas autenticadas respondem diferente para a mesma situação ("essa empresa não é sua"), bug-hunting de segurança fica mais difícil e o time gasta tempo discutindo no PR. Para a EBP: evita decisão por omissão no EPIC-002 onde 6+ novas rotas autenticadas vão nascer (`/diagnosticos`, `/relatorios`, etc).

## Critérios de aceite

- [ ] **CA-1:** IDR criada em `defonline-docs/project-state/decisions/idr/IDR-XXX-cross-tenant-403-vs-404.md` (número conforme `references/indexing.md`) com a estrutura padrão (contexto, decisão, alternativas consideradas, consequências, como verificar). Vinculada a `related_adrs: ["ADR-003"]` e `related_story: "STORY-014"`.
- [ ] **CA-2:** Decisão entre as 3 opções (A | B | C) **com justificativa em prosa** considerando: (a) princípio anti-enumeração da security-discipline, (b) custo de mudança no código atual, (c) precedente para EPIC-002, (d) auditoria jurídica do acesso negado vs. minimização de ruído.
- [ ] **CA-3:** Caso a decisão **não seja** "manter 403" (opção B), a IDR lista as estórias de correção sugeridas: pelo menos uma estória de refactor em `EmpresaController::show` + revisão da action `empresa.acesso_negado` em `audit_logs`. **Não implementa**; só propõe ao PO.
- [ ] **CA-4:** Caso a decisão **seja** "manter 403" (opção B), a IDR ratifica formalmente e **registra um aditivo** explícito em ADR-003 §Decisão 1 e/ou NRF §4.3 explicando por que a defesa em profundidade (audit + anti-enumeração) vence o argumento puro de anti-enumeração — caso contrário a próxima leitura cruzada do ADR vai gerar ruído de novo.
- [ ] **CA-5:** `index.json` atualizado: `decisions.idr` ganha a nova entrada com `status: accepted`; STORY-021 vai para `done`.

## Fora de escopo

- **Implementação** da mudança escolhida (caso opção A ou C) — vira estória de correção separada, escrita pelo PO após ler a IDR.
- **Revisão de outras rotas** que possam ter o mesmo padrão (já não há nenhuma além de `EmpresaController::show` neste momento — a IDR pode estabelecer o princípio para uso futuro no EPIC-002).
- **Mudança no Global Scope** (já cobre listagem; não há divergência ali).

## Padrões de qualidade exigidos

- IDR segue o template em `defonline-docs/skills/arquiteto/templates/` (se existir) ou o padrão observável das IDRs existentes em `decisions/idr/`.
- Justificativa em prosa **considera honestamente** os argumentos de ambos lados — não decisão puramente por preferência.
- Output **rastreável** — qualquer agente futuro lendo a IDR entende **por que** sem precisar reler esta estória.

## Dependências

- **Bloqueada por:** nenhuma (decisão pronta para ser tomada — todos os inputs já existem).
- **Bloqueia:** abertura efetiva do EPIC-002 (recomendação do PO — não bloqueia formalmente o `status: ready` do EPIC-002, mas vai poupar retrabalho se decidido antes).
- **Pré-requisitos:** acesso ao repo e aos documentos referenciados.

## Decisões já tomadas (não as reabra)

- **Multi-tenancy via FK + Global Scope** (ADR-003 §Decisão 1) — não está em questão.
- **Que existe `audit_logs` jurídico append-only** (ADR-003 + STORY-007 EPIC-000) — não está em questão.
- **Cross-tenant não pode resultar em sucesso** — não está em questão; o objeto não deve ser servido.

## Liberdade do agente Arquiteto

Você decide:
- Qual das 3 opções (A | B | C) é a escolhida.
- Se cria uma 4ª opção que ninguém pensou — desde que justifique e registre.
- Como formatar a IDR (seguindo o padrão observável).

Você NÃO decide:
- Que existe multi-tenancy (ADR-003).
- Que cross-tenant não retorna o objeto (segurança básica).
- Critério de aprovação (PO + checklist da estória).

## Definição de Pronto

- [ ] IDR criada e referenciada em `index.json`.
- [ ] Decisão em prosa com justificativa.
- [ ] Frontmatter da IDR completo (`status: accepted`, `decided_at`, `decided_by`, `related_adrs`, `related_story`).
- [ ] STORY-021 `status: done` no frontmatter + `index.json`.
- [ ] Notas do agente preenchidas.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`. Como é spike de decisão, não há commits de código — só de docs. Atualize `index.json` na mesma operação que cria a IDR.

## Notas do agente

### Tempo investido
- <horas>

### Decisões tomadas
- <data> — <decisão>

### IDR criada
- IDR-XXX em `decisions/idr/IDR-XXX-cross-tenant-403-vs-404.md` — veredito: <Opção A | B | C | outra>.

### Observações úteis ao PO
- <data> — <observação>
