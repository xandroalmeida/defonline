---
story_id: STORY-023
slug: fix-bump-rc-dispara-release-homolog
title: Fix — `bump-rc.yml` precisa disparar `release-homolog.yml` automaticamente
epic_id: EPIC-001
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: in_review
owner_agent: programador (claude-opus-4-7)
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
- ~0.5h (análise das 3 opções + implementação + documentação + aditivo ADR).

### Opção escolhida
- **Opção A (PAT)** — substituir `GITHUB_TOKEN` por `secrets.RELEASE_TAG_PAT` em `actions/checkout@v4` do `bump-rc.yml`. Justificativa: opção B (`gh release create`) é arriscada porque a limitação do GitHub é sobre **a origem do evento ser o `GITHUB_TOKEN`**, não sobre o **mecanismo** (push vs API) — relatos online são contraditórios e o risco de virar "às vezes dispara, às vezes não" é incompatível com um workflow que precisa ser determinístico. Opção C (GitHub App) tem overhead alto demais (provisionar App, gerenciar segredo da App, instalação no repo) para um projeto de 1 dev no MVP. Opção A é o caminho clássico documentado pelo próprio GitHub, com escopo mínimo (`Contents: write` em single repo) e rotação anual previsível.

### Secret(s) provisionado(s)
- **`RELEASE_TAG_PAT`** — Fine-grained PAT; escopo: `Contents: Read and write` no único repo `xandroalmeida/DEFOnline`; owner: Alexandro (conta pessoal); rotação: anual (ou ao expirar — GitHub avisa por email 7 dias antes). **Procedimento de geração + provisionamento + rotação + teste de fumaça documentados em [defonline-docs/skills/programador/references/cicd-secrets.md](../../../skills/programador/references/cicd-secrets.md).**
- **Pendente do PO** (CA-3 depende disto): provisionar o valor do secret com `gh secret set RELEASE_TAG_PAT --repo xandroalmeida/DEFOnline` antes do teste empírico.

### Link do teste empírico
- bump-rc run: **pendente** — depende do PO (1) provisionar `RELEASE_TAG_PAT` no remote, (2) push do commit desta estória, (3) rodar `gh workflow run bump-rc.yml --repo xandroalmeida/DEFOnline -f bump=patch`.
- release-homolog disparado: **pendente** — esperar via `gh run list --workflow=release-homolog.yml --repo xandroalmeida/DEFOnline --limit 1` (deve aparecer com `event: push` e `display_title: <tag-criada>` em até 1min).

### Observações úteis ao PO

- **CA-2 (implementação)**: arquivo único alterado — [.github/workflows/bump-rc.yml](../../../../.github/workflows/bump-rc.yml). Diff é mínimo: adiciona `token: ${{ secrets.RELEASE_TAG_PAT }}` no `actions/checkout` + comentário-cabeçalho explicando o porquê.
- **CA-4 (documentação)**: criado novo arquivo [defonline-docs/skills/programador/references/cicd-secrets.md](../../../skills/programador/references/cicd-secrets.md) consolidando todos os secrets de CI/CD (não só este — aproveitei para mapear `DEPLOY_SSH_KEY`, `ANSIBLE_VAULT_PASS`, etc. com ponteiros para os ADRs onde foram decididos).
- **CA-5 (aditivo ADR)**: ADR-006 §1.4 + §2.3 (tabela de secrets) + `last_amendment_note` no frontmatter atualizados.
- **Idempotência (padrão de qualidade)**: rodar `bump-rc.yml` duas vezes seguidas continua gerando `-rc.(N+1)` para a próxima tag (lógica do step "Calcular próxima tag" já garantia isso desde antes — não tocada). Sem efeito colateral do PAT nessa garantia.
- **Sem commit em main remoto ainda** (por padrão da memória do projeto: nunca push/PR/tag sem ordem explícita do PO). Quando você aprovar, basta `git push origin main` + o procedimento do CA-3.
- **Rollback do fix**: se algum dia o PAT estiver vazando ou indesejado, basta `git revert` do commit + `gh secret delete RELEASE_TAG_PAT` — voltamos ao estado pré-STORY-023 (com o workaround manual de re-empurrar tag do host).
