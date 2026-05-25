# Secrets do CI/CD — provisionamento, escopo e rotação

Referência operacional dos secrets de repositório consumidos pelos workflows do `.github/workflows/`. Mantém o **inventário canônico** + o **como gerar / como rotacionar** num só lugar, para evitar que o conhecimento more na cabeça de quem provisionou.

Princípio: **escopo mínimo + rotação documentada + zero valor no repo**. O valor vive no GitHub Secrets; aqui só fica o **nome**, o **porquê**, e o **comando de provisionamento**.

> **Vai mexer em workflow?** Antes de adicionar um novo secret, leia `security-discipline.md` (mentalidade "segredos não são código" + "menor privilégio"). Antes de mudar trigger de workflow, leia `ADR-006-cicd.md`.

---

## Inventário

| Secret | Tipo | Consumido por | Escopo / permissões | Owner | Cadência de rotação |
|---|---|---|---|---|---|
| `RELEASE_TAG_PAT` | Fine-grained PAT | `.github/workflows/bump-rc.yml` (step `actions/checkout`) | Single repo (`DEFOnline`), permissão `Contents: Read and write` | Alexandro (conta pessoal) | Anual ou ao expirar (ver §Rotação) |
| `DEPLOY_SSH_KEY` | SSH private key (ed25519) | `release-homolog.yml`, `release-production.yml` | Acesso `deploy@vps-homolog`, `deploy@vps-prod` (chave dedicada por ambiente, sem shell interativo) | Alexandro | Anual ou em incidente |
| `DEPLOY_SSH_KNOWN_HOSTS` | string | idem | Fingerprint SSH das VPS — atualizar quando o host trocar de chave (raro) | Alexandro | Sob demanda |
| `ANSIBLE_VAULT_PASS` | string | idem | Decripta `infra/ansible/group_vars/*/vault.yml` | Alexandro | Anual ou em incidente |
| `TELEGRAM_BOT_TOKEN` | string | `release-homolog.yml`, `release-production.yml` (step `notify`) | Token do bot de alertas | Alexandro | Quando o bot for rotacionado |
| `TELEGRAM_CHAT_ID_HOMOLOG` / `TELEGRAM_CHAT_ID_PRODUCTION` | string | idem | ID do chat de destino | Alexandro | Sob demanda |
| `GHCR_PAT` (opcional) | PAT clássico | reserva — `release-homolog.yml` usa `GITHUB_TOKEN` para push em GHCR | `write:packages` | Alexandro | Não provisionado por padrão |

---

## `RELEASE_TAG_PAT` — detalhe

### Por que existe

`bump-rc.yml` cria e empurra uma tag `vX.Y.Z-rc.N`. O `release-homolog.yml` escuta `on: push: tags` e deveria disparar deploy em homologação.

**Mas o GitHub protege contra loops de workflow:** uma tag empurrada com `GITHUB_TOKEN` implícito **não dispara** o workflow downstream. Sem fix, todo `bump-rc` exige o workaround manual "deletar a tag remota + re-empurrar do host local com credenciais pessoais" — exatamente o atrito que `bump-rc.yml` foi feito para eliminar.

Solução adotada (STORY-023, opção A — ADR-006 aditivo 2026-05-24): `actions/checkout` do `bump-rc.yml` recebe `token: ${{ secrets.RELEASE_TAG_PAT }}`. As credenciais do PAT ficam configuradas no remote, e o `git push` subsequente sai como o owner do PAT — disparando o workflow downstream normalmente.

Alternativas consideradas e rejeitadas:
- **Opção B (`gh release create`)**: na documentação oficial do GitHub, eventos disparados por `GITHUB_TOKEN` **não criam novos workflow runs** (exceção: `workflow_dispatch` e `repository_dispatch`). Há relatos contraditórios sobre tags criadas via API/`gh release create` dispararem ou não — risco de virar "às vezes funciona". Rejeitada.
- **Opção C (GitHub App token)**: melhor higiene de permissões, mas overhead alto (provisionar App, instalar no repo, manter segredo da App) para um projeto de 1 dev no MVP. Rejeitada.

### Como gerar (primeira vez ou rotação)

1. Acessar https://github.com/settings/personal-access-tokens/new (Fine-grained PAT, **não** o clássico).
2. **Token name:** `defonline-release-tag-pat` (ou similar, identificável).
3. **Expiration:** 1 ano (máximo permitido em fine-grained sem custom — anotar no calendário).
4. **Resource owner:** Alexandro (conta pessoal — owner do repo).
5. **Repository access:** *Only select repositories* → `xandroalmeida/DEFOnline`. **Nunca** "All repositories".
6. **Repository permissions:** marcar **apenas**:
    - `Contents`: **Read and write** (para push de tag).
    - Todas as outras: `No access` (default).
7. **Generate token** → copiar o valor (`github_pat_...`) — aparece **uma única vez**.

### Como provisionar como secret do repo

Do host local, com `gh` autenticado:

```bash
gh secret set RELEASE_TAG_PAT --repo xandroalmeida/DEFOnline
# cola o valor github_pat_... quando o gh pedir; ele lê do stdin sem ecoar
```

Verificar que ficou:

```bash
gh secret list --repo xandroalmeida/DEFOnline | grep RELEASE_TAG_PAT
# deve aparecer: RELEASE_TAG_PAT  Updated YYYY-MM-DD
```

### Como rotacionar

GitHub manda email ~7 dias antes de expirar. Quando chegar (ou em incidente que comprometa o token):

1. Gerar PAT novo (seguir §Como gerar).
2. `gh secret set RELEASE_TAG_PAT --repo xandroalmeida/DEFOnline` (sobrescreve).
3. Revogar o PAT antigo em https://github.com/settings/personal-access-tokens (lista o ativo + os expirados).
4. Disparar um `gh workflow run bump-rc.yml --repo xandroalmeida/DEFOnline` de fumaça pra confirmar que o novo token funciona (a próxima tag rc deve disparar `release-homolog.yml`).

### Como verificar (teste empírico)

Após o secret estar provisionado:

```bash
# Dispara o bump no remote
gh workflow run bump-rc.yml --repo xandroalmeida/DEFOnline -f bump=patch

# Aguarda 5–10s e confere
gh run list --workflow=bump-rc.yml --repo xandroalmeida/DEFOnline --limit 1
gh run list --workflow=release-homolog.yml --repo xandroalmeida/DEFOnline --limit 1
```

**O que esperar:** `bump-rc` termina ok → na sequência, `release-homolog` aparece com `event: push` e `display_title: <tag-criada>` (ex.: `v0.3.0-rc.7`). Se não aparecer em até 1min, o token está sem permissão ou expirado.

---

## `DEPLOY_SSH_KEY` / `DEPLOY_SSH_KNOWN_HOSTS` / `ANSIBLE_VAULT_PASS`

Provisionamento descrito em ADR-005 (host VPS + Ansible). Não duplicar aqui — manter o ponteiro:

- ADR-005 §"Como provisionar acessos" — geração das chaves SSH + cadastro em `authorized_keys` do `deploy@`.
- `infra/ansible/group_vars/*/vault.yml` — variáveis sensíveis decriptadas pelo `ANSIBLE_VAULT_PASS`.

---

## `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID_*`

Provisionamento descrito em ADR-004 (observabilidade + Telegram). Manter o ponteiro.
