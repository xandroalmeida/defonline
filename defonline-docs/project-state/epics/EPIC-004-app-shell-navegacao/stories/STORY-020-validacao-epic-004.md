---
story_id: STORY-020
slug: validacao-epic-004
title: Validação final do EPIC-004 — App shell + design system v1 + landing pública
epic_id: EPIC-004
sprint_id: SPRINT-2026-W23
type: validation
target_role: validador
status: draft
owner_agent: null
created_at: 2026-05-24
updated_at: 2026-05-24
estimated_session_size: M
---

# STORY-020 — Validação final do EPIC-004

> **Para o agente Validador:** carregue a skill `validador` e leia esta estória + o checklist por inteiro antes de começar. Você **não conserta** nada — apenas executa o checklist, registra evidências e produz o relatório. Em caso de falha, devolve para o PO sem tentar corrigir. Mesmo padrão da STORY-008 (EPIC-000) e da STORY-017 (EPIC-001).
>
> **Esta story está em `draft`.** Sai de `draft` para `ready` quando STORY-019 e STORY-024 estiverem **ambas em `in_review`**. O PO confirma a promoção no chat.

## Contexto

Última estória do EPIC-004. O épico só pode ser marcado como `done` no `index.json` depois que esta validação produzir um `report.md` aprovado.

- Épico: `epics/EPIC-004-app-shell-navegacao/epic.md`
- Estórias de implementação validadas aqui: **STORY-019** (app shell + design system + refactor) e **STORY-024** (landing pública simples + remoção da página de debug).
- Checklist: `epics/EPIC-004-app-shell-navegacao/validation/checklist.md` — **já criado** pelo PO em 2026-05-24, com seções dedicadas para cada CA da STORY-019 e seção H1 dedicada à landing (STORY-024).
- Companion design assinado pelo PO (referência visual obrigatória durante a validação):
  - `epics/EPIC-004-app-shell-navegacao/design/mock-shell.html` (protótipo navegável com 6 telas do shell autenticado)
  - `epics/EPIC-004-app-shell-navegacao/design/logo.svg` e `logo.html` (logomarca "D")
  - `epics/EPIC-004-app-shell-navegacao/design/ux-specs.md` (políticas UX consolidadas)
  - `epics/EPIC-004-app-shell-navegacao/design/fluxo-navegacao.md` (fluxo ponta-a-ponta)
- Documentos canônicos do Validador:
  - `defonline-docs/skills/validador/SKILL.md`
  - `defonline-docs/skills/validador/references/validation-workflow.md`
  - `defonline-docs/skills/validador/references/evidence-discipline.md`
  - `defonline-docs/skills/validador/references/verdict-criteria.md`
  - `defonline-docs/skills/validador/references/reporting-craft.md`
  - `defonline-docs/skills/validador/templates/validation-report.md`
- Precedentes úteis: `epics/EPIC-000-foundation/validation/report.md` e `epics/EPIC-001-cadastro-minimo/validation/report.md`.

## O quê

Executar o checklist de validação do EPIC-004 em ordem, registrando para cada item: status (`pass | fail | n/a`), evidência (link, screenshot, log, comando reproduzível), e observações úteis. Produzir `epics/EPIC-004-app-shell-navegacao/validation/report.md` usando o template do `validador`.

Particularidades desta validação (vs as anteriores):

- **Grande peso em inspeção visual** — épico é primariamente de UX/UI. Capturar screenshots em **mobile (360x800)** e **desktop (1280x800)** é mais importante aqui do que em validações anteriores.
- **Comparar contra o mock assinado pelo PO** (`design/mock-shell.html`) — divergência visual com o mock é evidência válida de falha, ainda que o código rode. Mock é "design de referência", não wireframe descartável.
- **Bug fix funcional para verificar** — o caso "Roberto com 1 empresa cadastrada e quer adicionar outra" hoje não tem caminho de UI. A correção tem que estar visível em homologação com print explícito (`/home` com card de empresa + botão `Adicionar empresa` no header da seção).
- **Landing pública nova** — visitar `https://defonline.xandrix.com.br/` em browser anônimo e verificar que não vê mais `hello DEFOnline`. Print obrigatório.

## Por quê

Sem validação independente, o EPIC-004 fecharia por confiança em quem implementou — anti-padrão. A validação garante que o chassi visual e a landing estão coerentes com o design system materializado, com gates de qualidade ativos, sem regressão funcional do EPIC-001 e sem o bug funcional do "Adicionar empresa".

## Critérios de aceite

- [ ] **CA-1:** Cada item do `validation/checklist.md` do EPIC-004 (seções A, B, C, D, E, F, G, H1, H, I) foi exercido pelo Validador com evidência registrada.
- [ ] **CA-2:** `validation/report.md` produzido a partir do template, com veredito (`approved | approved_with_pending | rejected`) e justificativa por item.
- [ ] **CA-3:** Em caso de **rejected**, o relatório lista explicitamente quais CAs/itens falharam e sugere estórias de correção (sem implementá-las — apenas propor).
- [ ] **CA-4:** Em caso de **approved**, o `index.json` é atualizado para: EPIC-004 `status: done` + `validation_report` apontando para `validation/report.md` (com `verdict_history` se houver mais de um passe); STORY-020 `status: done`; STORY-019 e STORY-024 já em `done`.
- [ ] **CA-5:** Notas do agente preenchidas com tempo investido, dificuldades, observações úteis ao PO. Em particular: dificuldades de inspeção visual (se houve), divergências entre código e mock (se houve), itens onde o checklist do PO ficou ambíguo (para PO aprimorar para o próximo épico).
- [ ] **CA-6:** Screenshots obrigatórios anexados ao relatório:
  - Landing `/` em 360x800 e 1280x800.
  - `/home` (com ≥1 empresa) em 360x800 e 1280x800 — botão `Adicionar empresa` visível.
  - `/empresas/nova` em 360x800 e 1280x800 — botão `Cancelar` visível.
  - `/empresas/{id}/show` em 1280x800 — botão `Voltar para Minhas Empresas` visível.
  - Drawer mobile aberto em 360x800.
  - Dropdown "Conta" aberto.
  - Footer com versão visível.

## Fora de escopo

- **Consertar** falhas — papel do Programador na estória seguinte.
- Validar épicos seguintes — escopo desta estória é apenas EPIC-004.
- Re-validar itens dos EPIC-000 e EPIC-001 (esses já foram aprovados).
- Avaliar acessibilidade WCAG AAA — alvo deste épico é AA básico (CA-10 da STORY-019).
- Auditar performance (Lighthouse score, LCP, CLS, etc.) — fora de escopo desta sprint; pode virar story dedicada se PO quiser.

## Padrões de qualidade exigidos

Estória de validação — exceção declarada em `quality-standards.md`: Validador não escreve código de produção; produz relatório verificável. **Exigência mantida:** rigor de evidência (sem "passei no olho"; cada `pass` tem link/screenshot/log reproduzível). Para itens visuais, screenshot anexado é a evidência canônica.

## Dependências

- **Bloqueada por:** STORY-019 e STORY-024 estarem em `in_review` (ou `done`). Promoção de `draft` → `ready` pelo PO.
- **Bloqueia:** fechamento do EPIC-004 (status `done` no `index.json`); primeira story do EPIC-002 (recomendação do PO: começa só após EPIC-004 `done` para não nascer com drift).

## Decisões já tomadas

- **Mock-shell.html é o "design assinado" pelo PO** — divergência visual conta como evidência de falha.
- **Validação cobre ambas as stories de implementação** (STORY-019 + STORY-024) — não separamos em duas validações para economizar overhead.
- **Veredito pode ser `approved_with_pending`** — se houver F-NB (falha não-bloqueante) que não compromete o entregável visível mas merece registro para correção futura. Mesma mecânica usada nas validações dos EPIC-000 e EPIC-001.

## Liberdade técnica do agente

- Ordem de execução dos itens do checklist é livre, desde que cada um seja exercido.
- Ferramenta de captura de screenshot é livre (browser, Dusk, ferramenta nativa do SO).
- Pode pedir ao PO no chat para reproduzir um cenário difícil (ex.: criar 2 empresas para validar o estado "com empresas") — não conta como falha, conta como suporte.

## Definição de Pronto (DoD)

- [ ] CA-1 a CA-6 passam.
- [ ] `validation/report.md` commitado no repo `defonline-docs`.
- [ ] Veredito explícito no `report.md` e refletido no `index.json` (se `approved`).
- [ ] PO avisado no chat com o veredito.
- [ ] `index.json` atualizado conforme o veredito.

## Notas do agente

### Tempo investido
- <total em horas>

### Dificuldades encontradas
- <descrição>

### Observações úteis ao PO
- <gaps no checklist, ambiguidades, sugestões de processo>

### Screenshots anexados
- <lista de paths/links>

### Links de evidência
- Report: `epics/EPIC-004-app-shell-navegacao/validation/report.md`
- Index.json commit: <sha>
