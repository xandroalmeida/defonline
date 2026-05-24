---
story_id: STORY-023
slug: fix-bump-rc-dispara-release-homolog
title: Fix — `bump-rc.yml` precisa disparar `release-homolog.yml` automaticamente
epic_id: EPIC-001
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-05-24
updated_at: 2026-05-24
estimated_session_size: S
---

# STORY-023 — `bump-rc.yml` deve disparar `release-homolog.yml`

> **Para o agente programador:** débito técnico identificado pela STORY-018 (notas "Gotchas no pipeline"). Workflow `bump-rc.yml` cria uma tag rc mas o `release-homolog.yml` **não dispara** por design do GitHub (`GITHUB_TOKEN` implícito não aciona workflows downstream). Workaround atual é deletar a tag remota e re-empurrar do host local. Você fecha isso permanentemente.

## Contexto (por que esta estória existe)

Trecho literal das Notas do agente da STORY-018:

> 2026-05-24 — **`bump-rc.yml` não dispara `release-homolog.yml`.** A tag criada pelo workflow `bump-rc` com `GITHUB_TOKEN` implícito **não acionou** o trigger `on: push: tags`. É a proteção do GitHub contra loops de workflow (`GITHUB_TOKEN` não dispara workflows downstream). Workaround imediato: deletar a tag remota e re-empurrar do host local com credenciais pessoais. Correção definitiva (proposta para IDR ou aditivo ADR-006): trocar `secrets.GITHUB_TOKEN` por PAT dedicado no `actions/checkout` do `bump-rc.yml`, ou usar `gh release create` (que dispara workflow corretamente).

Isso vai voltar a morder em qualquer epic que use o `bump-rc.yml` (provavelmente todos os próximos). Vale fechar antes do EPIC-002.

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md` (origem do débito)
- Documentos canônicos:
  - [`.github/workflows/bump-rc.yml`](.github/workflows/bump-rc.yml) — workflow atual.
  - [`.github/workflows/release-homolog.yml`](.github/workflows/release-homolog.yml) — workflow downstream que deveria disparar.
  - [defonline-docs/project-state/decisions/adr/ADR-006-cicd.md](defonline-docs/project-state/decisions/adr/ADR-006-cicd.md) — pode precisar de aditivo se a escolha exigir secret novo.
  - GitHub docs sobre "Triggering a workflow from a workflow" (limitação do `GITHUB_TOKEN`).

## O quê (objetivo desta estória)

Garantir que rodar `bump-rc.yml` (manualmente via `workflow_dispatch` ou automaticamente) **dispare** o `release-homolog.yml` na sequência, sem intervenção manual.

## Por quê (valor para o usuário)

Para Roberto: zero impacto direto. Para o time: tira o passo manual "deletar tag remota + re-empurrar do host local" que aparece toda vez que `bump-rc` é usado. Diminui fricção do ciclo bump → deploy + remove a tentação de pular o `bump-rc` por causa do atrito (o que deixaria a numeração inconsistente).

## Critérios de aceite

- [ ] **CA-1:** A correção escolhe entre **três opções viáveis** e justifica em IDR ou em PR comment:
  - **Opção A — PAT (Personal Access Token):** `secrets.RELEASE_TAG_PAT` definido como secret do repo; `actions/checkout` do `bump-rc.yml` usa esse PAT em vez de `GITHUB_TOKEN`. Custo: secret novo + rotação manual quando expirar.
  - **Opção B — `gh release create`:** substitui o `git tag + git push` do `bump-rc.yml` por `gh release create` (que **dispara** workflows downstream mesmo com `GITHUB_TOKEN`). Custo: criar release-as-tag mesmo que vazio.
  - **Opção C — GitHub App token:** usar uma GitHub App dedicada como ator do push (workflows downstream disparam). Custo: maior — provisionar a App.
- [ ] **CA-2:** A opção escolhida é **implementada** no `bump-rc.yml`. PR (ou commit direto em main por workflow do projeto) inclui descrição clara da mudança.
- [ ] **CA-3:** **Teste empírico**: depois do merge da correção, rodar `bump-rc.yml` (manualmente via `gh workflow run bump-rc.yml`) num teste e confirmar que `release-homolog.yml` dispara automaticamente para a tag gerada. Evidência: link para o run do `release-homolog` disparado pela tag de teste.
- [ ] **CA-4:** Se Opção A ou C exigir secret novo: documentado em `defonline-docs/skills/programador/references/` (ou IDR equivalente) o nome do secret, como gerar, como rotacionar.
- [ ] **CA-5:** Se a escolha contradisser o ADR-006 ou exigir mudança no fluxo nele documentado, **registrar aditivo formal** no ADR (ou abrir uma IDR de ajuste).

## Fora de escopo

- Refatorar `bump-rc.yml` por completo (semver bump logic, etc.) — só consertar o trigger.
- Refatorar `release-homolog.yml` — não é o problema.
- Mudar para tag não-semver — fora do escopo (`v[0-9]+.[0-9]+.[0-9]+-rc.[0-9]+` é convenção do projeto).

## Padrões de qualidade exigidos

- Solução **idempotente**: rodar `bump-rc.yml` duas vezes não deve gerar tags duplicadas ou estado inconsistente.
- **Sem aumentar superfície de ataque desnecessariamente** — se PAT, escopo mínimo (`contents:write` + `actions:write`). Se GitHub App, permissões granulares.
- **Smoke test do CI** com a mudança aplicada antes de declarar pronto.
- Mudanças em workflow CI versionadas + descritas em commit message.

## Dependências

- **Bloqueada por:** nenhuma.
- **Bloqueia:** nenhuma formalmente; recomendado fechar antes do EPIC-002 começar a usar `bump-rc` ativamente.

## Decisões já tomadas

- **Workflow `bump-rc.yml` permanece** como ferramenta de geração de tags rc (ADR-006).
- **Workflow `release-homolog.yml` dispara em `on: push: tags`** (ADR-006) — não muda.

## Liberdade técnica do agente

Você decide:
- Opção A, B ou C.
- Nome do secret (se Opção A): sugestão `RELEASE_TAG_PAT`.
- Quem é o owner do PAT (se Opção A): provavelmente o usuário Alexandro pessoal — mas pode propor bot account se preferir.

Você NÃO decide:
- Trocar `bump-rc` por outro mecanismo (sair do semver, voltar a tagging manual permanente, etc).
- Mexer no `release-homolog.yml` (já funciona).

## Definição de Pronto

- [ ] CAs cumpridos.
- [ ] Teste empírico do `bump-rc` → `release-homolog` ao vivo, com link do run.
- [ ] Documentação atualizada se aplicável (CA-4, CA-5).
- [ ] STORY-023 `status: done` no frontmatter + `index.json`.
- [ ] Notas do agente preenchidas com a opção escolhida + justificativa.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`. **Importante**: a mudança envolve secret do repositório se Opção A. Não comite o valor do PAT — só o **nome** da variável de secret. PO provisiona o valor manualmente via `gh secret set RELEASE_TAG_PAT` antes do teste empírico (CA-3).

## Notas do agente

### Tempo investido
- <horas>

### Opção escolhida
- <A | B | C> — <justificativa em 1-2 linhas>

### Secret(s) provisionado(s)
- <nome do secret> — escopo: <permissões>; owner: <Alexandro | bot>; rotação: <quando>.

### Link do teste empírico
- bump-rc run: <link>
- release-homolog disparado: <link>

### Observações úteis ao PO
- <opcional>
