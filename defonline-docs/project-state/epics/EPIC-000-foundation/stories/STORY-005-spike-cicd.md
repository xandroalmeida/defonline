---
story_id: STORY-005
slug: spike-cicd
title: SPIKE — Decisão de CI/CD (pipeline, branching, deploy, rollback)
epic_id: EPIC-000
sprint_id: null
type: spike
target_role: arquiteto
status: done
owner_agent: arquiteto (claude-opus-4-7)
created_at: 2026-05-20
updated_at: 2026-05-21
estimated_session_size: M
---

# STORY-005 — SPIKE de CI/CD

> **Para o agente Arquiteto:** spike → ADR `accepted`, sem código de produção.

## Contexto

O critério de pronto do EPIC-000 é "merge na main faz deploy automático em homologação". Esta spike decide como esse "faz deploy automático" acontece: ferramenta de CI/CD, modelo de branching, gates de qualidade, política de rollback, feature flags (se aplicáveis). Princípios aplicáveis: automação por padrão (PO), simplicidade de branching para time pequeno (princípio do Tipo 7 do `adr-types.md`).

- Épico: `epics/EPIC-000-foundation/epic.md`
- Documentos canônicos:
  - `defonline-docs/skills/arquiteto/SKILL.md` e `architecture-principles.md`
  - `defonline-docs/skills/arquiteto/references/adr-types.md` — Tipo 7 Política de evolução (mini-checklist)
  - `defonline-docs/skills/arquiteto/templates/adr.md`
  - `defonline-docs/skills/po/references/quality-standards.md` (cobertura mínima a ser gateada pelo pipeline)

## O quê

Produzir **ADR `proposed`** com `type: politica-evolucao` decidindo: modelo de branching (trunk-based vs GitFlow leve vs outro); ferramenta de CI/CD (GitHub Actions, GitLab CI, CircleCI, etc.); pipeline mínimo (lint + test + build + deploy); gates de cobertura conforme `quality-standards.md`; estratégia de deploy para homologação (automático a cada merge na main); estratégia de deploy para produção (automático ou gated, a decidir); estratégia de rollback (revert + redeploy, blue-green, ou outra); política de feature flags (ferramenta + padrão de remoção).

## Por quê

Sem CI/CD decidida, STORY-007 (hello world deploy) não tem trilho. Princípio "automação por padrão" do PO exige pipeline desde o dia 1. Cobertura sem gateamento vira "depois eu vejo" — falha cultural conhecida.

## Critérios de aceite

- [ ] **CA-1:** ADR em `decisions/adr/ADR-XXX-cicd.md`, `type: politica-evolucao`.
- [ ] **CA-2:** ADR cobre integralmente o mini-checklist do `adr-types.md` Tipo 7: modelo de branching simples; deploy automático para homologação a cada merge; deploy para produção definido; estratégia de rollback documentada; feature flags com ferramenta e política de remoção.
- [ ] **CA-3:** ADR explicita os gates de cobertura no pipeline (≥80% geral, ≥98% núcleo/regras de negócio) conforme `quality-standards.md` — pipeline vermelho se cobertura cair abaixo.
- [ ] **CA-4:** Mínimo 2 alternativas reais de ferramenta CI/CD + status quo (ex.: GitHub Actions vs GitLab CI vs solução do provedor cloud).
- [ ] **CA-5:** ADR inclui pseudocódigo ou diagrama Mermaid do pipeline (etapas: lint → test → build → deploy → smoke test em homolog).
- [ ] **CA-6:** ADR submetida ao PO. `index.json` atualizado.

## Fora de escopo

- Detalhes de provedor cloud onde rodam workers de CI — pode ter coupling com STORY-004 (Infra), sinalizar.
- Implementação real do `.yml` do pipeline — fica em STORY-007 (implementação) ou em IDR.
- Observabilidade do pipeline (que sinais coletar de duração, falha) — pode mencionar mas STORY-006 trata em detalhe.

## Padrões de qualidade exigidos

Spike — mesma exceção declarada em STORY-001.

## Dependências

- **Bloqueada por:** nada (paralelizável com STORY-001, 002, 003, 004, 006).
- **Bloqueia:** STORY-007.
- **Acoplamento leve com STORY-004 (Infra):** alvo de deploy vem da Infra.

## Decisões já tomadas

- Princípio "automação por padrão" — cliques manuais inaceitáveis.
- Princípio "qualidade é requisito" — cobertura gateada no pipeline.

## Liberdade técnica do agente Arquiteto

Você decide branching, ferramenta CI/CD, pipeline, rollback, flags. Você **não decide** stack, topologia, persistência, infra de hospedagem, observabilidade — cada um em sua spike.

## Definição de Pronto

- [ ] CAs satisfeitos. ADR `proposed`. `index.json` atualizado. Notas preenchidas. `status: in_review`.

## Protocolo do agente

Siga `agent-task-format.md`. Igual à STORY-001.

## Notas do agente

### Decisões tomadas

- **2026-05-21** — **ADR-006-cicd** redigida como `proposed`. Stack escolhida: **GitHub Actions (SaaS)** + branching **trunk-based** com `main` única branch protegida + cadência **tag-based dual** (`vX.Y.Z-rc.N` dispara deploy em **homologação**, `vX.Y.Z` sem sufixo dispara deploy em **produção** com gate humano via GitHub Environment) + **Ansible deploy** (consome `playbooks/deploy.yml` da ADR-005) + **Laravel Pennant** (driver `database`, princípio #3) com política de remoção (`@owner` + `@cleanup_due` em PHPDoc, CI alerta após data vencer, bloqueia após +30 dias). Gates de cobertura: **80% geral** + **98% em `app/Domain/**`** via Pest `--coverage --min`. Pipeline mínimo: lint (pint + larastan + ansible-lint + commitlint) → testes (Pest unit + Dusk E2E) → segurança (composer audit + trivy) → build Docker → push GHCR → ansible deploy → smoke (`/health` + `/ready` + 1 Dusk crítico) → notifica Telegram. Rollback = recriar tag anterior ou acionar `deploy-by-tag.yml` (mesma imagem imutável no GHCR). Custo recorrente: ~R$ 0–2/mês (free tier GHA + GHCR).

- **2026-05-21** — Inputs operacionais confirmados pelo PO via `AskUserQuestion`:
  - Ferramenta CI/CD: **GitHub Actions** (sobre Gitea Actions self-hosted e GitLab CI — opções B e C).
  - Cadência: **tag-based dual** explícita — merge em main **não** deploya por si só, apenas valida + publica imagem. PO clarificou que a forma é `-rc.N` para homologação + tag semver final para produção (não tag `staging/<sha>`).
  - Feature flags: **Laravel Pennant desde o dia 1** com política de remoção (sobre adiar — princípio "evitar dívida silenciosa" pesou mais que princípio #1 puro aqui).

### Descobertas

- **2026-05-21** — A escolha do PO de **tag-based para homologação** muda o critério do `quality-standards.md §2.2` ("merge em main dispara deploy automático para homologação") e o critério de pronto do EPIC-000 ("merge na main faz deploy automático em homologação"). **Ações derivadas** registradas na ADR § "Consequências / Para o time" — devem ser executadas após o aceite da ADR-006 (edição direta nos dois documentos, com referência cruzada).
- **2026-05-21** — **Gate humano em produção via GitHub Environment** é gratuito em todos os planos GitHub (incluindo repos privados). Aplicamos como **segunda barreira adicional** ao tag-based — mesmo o PO criando a tag prod, o workflow aguarda 1 clique de "Approve and deploy". Mitiga risco de "tag prod criada por engano". Execução continua 100% automatizada (princípio "automação por padrão" do PO atendido).
- **2026-05-21** — Decisão deliberada de **re-rodar testes na tag** (em vez de promover imagem já testada do `main-<sha>` correspondente). Trade-off de ~5 min de runner para garantir que a tag exata foi testada — defesa contra drift entre commit mergeado e commit tagueado. Sinal de revisão registrado se virar gargalo (sustentadamente > 12 min total de pipeline).
- **2026-05-21** — **Núcleo / regras de negócio** ficou fixado como **`app/Domain/**`** para a métrica de 98%. A criação concreta da pasta (mesmo com placeholder) + o teste `CoreCoverageTest` são **IDR do Programador** na STORY-007. ADR não invade escopo de programador.
- **2026-05-21** — **GHCR** (GitHub Container Registry) escolhido como registry padrão por simplicidade e free tier (500 MB). Lifecycle policy mantém últimas 20 tags `-rc.*` e últimas 10 tags `vX.Y.Z` — ~1,5 GB total estimado, ~R$ 1,30/mês de cobrança incremental.
- **2026-05-21** — **Notificações Telegram** reusam o **mesmo bot da ADR-004**, mas em canal/chat separado (`defonline-ci`) para evitar misturar alertas operacionais com eventos de produto. Operação do PO criar o canal.
- **2026-05-21** — Política de migrations: **`migrate --force`** seguro porque ADR-003 fixou migrations Laravel reversíveis. Migrations destrutivas (drop coluna/tabela) exigem **label `dangerous-migration`** no PR e ficam em PR isolado — pipeline alerta mas não bloqueia (decisão humana com mais peso).

### Bloqueios encontrados

- Nenhum bloqueio que exigisse escalonamento. Duas perguntas ao PO via `AskUserQuestion` resolveram ambiguidades operacionais antes da redação (ferramenta CI/CD + cadência exata do tag-based + feature flags).

### Revisão 2026-05-21 (mesma sessão)

- **Feedback do PO:** "no CI/CD não quero testes que envolvam browser e que tenha que subir infra (Postgres). Pesa muito. Estes testes devem ser locais."
- **Pivot na ADR-006** sem mudar a Opção A (continua GitHub Actions + tag-based dual + Ansible + Pennant), apenas **realocando** onde os testes pesados rodam:
  - **Saem do runner GHA SaaS:** Pest Feature (DB-dependent), suíte Dusk completa, medição de cobertura 80%/98%.
  - **Entram no `git pre-push hook` local versionado** em `scripts/git-hooks/pre-push.sh`, instalado por `scripts/install-hooks.sh` rodado automaticamente via Composer `post-install-cmd` em `composer install`. Hook falha = push abortado pelo git.
  - **Mantido no CI remoto:** lint (pint, larastan, ansible-lint, commitlint), `composer audit`, `trivy fs`, `gitleaks`, Pest Unit puros opcionais, build da imagem Docker, push GHCR, `ansible-playbook deploy.yml`, **smoke pós-deploy** (HTTP `/health` + `/ready` + 1 Dusk leve com Chromium baixado on-the-fly **contra URL real já no ar** — não sobe Postgres no runner).
- **3 confirmações via `AskUserQuestion`** antes da reescrita: pre-push hook como gate (sobre disciplina pura e self-hosted noturno); smoke pós-deploy contra URL real mantido (sobre só-curl ou zero-CI); cobertura medida local no hook (sobre noturno + relaxamento de gates).
- **Nova força F13 (risco de bypass do hook via `--no-verify`)** registrada explicitamente como trade-off central da política + sinal de revisão correspondente (2+ incidentes em 6 meses por bypass → reabrir para runner self-hosted assíncrono).
- **Tempo estimado de pipeline CI:** PR ~2–3 min (vs ~8 min antes); release ~5–6 min (vs ~10 min antes). Free tier GHA fica ainda mais folgado — ~200–400 min/mês estimado vs ~600–900 min/mês antes.
- **Implica atualização do `quality-standards.md §2.2` em DOIS pontos** (não apenas "merge em main → homol", mas também "todo push dispara unit+E2E em paralelo"). Documentado em "Consequências / Para o time / Ações derivadas" da ADR-006.
- **Status da ADR:** continua `proposed` (apenas conteúdo revisado, não houve aceite humano da versão anterior).

### Aceite final 2026-05-21

- **ADR-006 aceita** pelo PO Alexandro em chat: `proposed` → `accepted`. Frontmatter da ADR, seção "Aprovação humana" e `index.json` atualizados.
- **Ações derivadas executadas nesta sessão pelo Arquiteto:**
  - **Ação 1:** [`quality-standards.md §2.2`](../../../../skills/po/references/quality-standards.md) atualizado nos dois pontos descritos na ADR-006 (Consequências / Neutras).
  - **Ação 2:** Critério de pronto + métrica primária do [`epic.md`](../epic.md) ajustados para refletir tag-based + pre-push hook.
- **Ações derivadas pendentes (não executadas aqui — escopo do Programador na STORY-007 ou operação manual do PO):**
  - **Ação 3:** IDR do Programador na STORY-007 com workflows YAML, `scripts/git-hooks/pre-push.sh`, `scripts/install-hooks.{sh,php}`, gancho `post-install-cmd` no `composer.json`, branch protection rule, `CoreCoverageTest`, pasta `app/Domain/` placeholder.
  - **Ação 4:** PO criar GitHub Environment `production` com required reviewer = Alexandro (painel GitHub).
  - **Ação 5:** Provisionar deploy SSH key ED25519 por VPS — extensão do `bootstrap.yml` da ADR-005 (Programador na STORY-007).
  - **Ação 6:** PO criar canal Telegram `defonline-ci` (ou chat ID separado no mesmo bot da ADR-004).
  - **Ação 7:** README.md com seção "Setup" incluindo `composer install` como obrigatório (Programador na STORY-007).
- **Status da estória:** `in_review` → `done`. ADR-006 `accepted`. EPIC-000 destrava STORY-007 (todas as 6 ADRs prerrequisitas — ADR-001 a ADR-006 — agora `accepted`).

### ADR produzida

- **ADR-006 — CI/CD do DEFOnline — GitHub Actions + trunk-based + release tags (rc.N → homol, semver → prod) + Ansible deploy + Pennant** — `decisions/adr/ADR-006-cicd.md` (status: `proposed`).

### Links de evidência

- **Sem spike de prova de conceito separado.** A STORY-007 (hello world deployado) absorve a validação: criará os workflows YAML reais, exercitará tag → deploy → smoke → rollback contra a VPS de homologação real. ADR registra esse plano em "Plano de verificação / Spike de validação proposto".
- ADR cobre integralmente o mini-checklist do `adr-types.md` Tipo 7 (Política de evolução): branching simples (Decisão 1), deploy automático para homologação a cada criação de tag rc (Decisão 5), deploy para produção definido com gate humano (Decisão 5), estratégia de rollback documentada (Decisão 6), feature flags com ferramenta e política de remoção (Decisão 7).
- ADR cobre CA-3 (gates 80%/98% via Pest no pipeline — Decisão 4), CA-4 (3 opções reais consideradas + status quo — Opções A/B/C/D), CA-5 (diagrama Mermaid do pipeline end-to-end na seção "Diagrama"), CA-6 (`index.json` atualizado com entrada `ADR-006` e datas em `2026-05-21T18:00:00Z`).

### Ações derivadas após aceite

(documentadas na ADR § "Consequências / Para o time / Ações derivadas" — não executadas nesta sessão para não invadir escopo de aceite humano; ficam como TODOs do Programador na STORY-007 ou como edição direta na sessão de aprovação da ADR pelo PO.)

1. Atualizar `defonline-docs/skills/po/references/quality-standards.md §2.2`.
2. Atualizar critério de pronto do `epics/EPIC-000-foundation/epic.md`.
3. IDR do Programador na STORY-007 com pasta `app/Domain/`, `CoreCoverageTest`, composite actions GHA, branch protection rule.
4. Criar GitHub Environment `production` com required reviewer = Alexandro (operação no painel GitHub).
5. Provisionar deploy SSH key ED25519 por VPS (extensão do `bootstrap.yml` da ADR-005).
6. Criar canal Telegram `defonline-ci` (ou reusar chat ID separado do bot da ADR-004).
