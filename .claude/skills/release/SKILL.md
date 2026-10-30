---
name: release
description: Publica o DEFOnline em produção (defonline.zalk.com.br, ambiente defonline-prod na ZalkVPS) por tag vX.Y.Z — commita o pendente, sincroniza com a origin, exige o CI de main verde, atualiza o secret APP_ENV_FILE, cria e envia a tag, acompanha o workflow zalk-deploy e valida a produção.
argument-hint: "[patch|minor]"
disable-model-invocation: true
---

# Release em produção

Use esta skill somente quando o usuário a invocar explicitamente (`/release`). A invocação já
autoriza as etapas abaixo, inclusive commitar, dar push na `main`, gravar o secret `APP_ENV_FILE` e
enviar a tag; não peça confirmação adicional só para começar. Criar ou alterar esta skill não
autoriza executar o fluxo.

Leia antes: `ZALKVPS.md` (contrato da plataforma), `defonline-docs/project-state/decisions/adr/ADR-009-infra-zalkvps.md`
e `.github/workflows/zalk-deploy.yml`. Siga os passos em ordem. Pergunte com `AskUserQuestion` e
espere a resposta. Nunca avance por suposição.

## Versão e tag

Tag `vX.Y.Z` (SemVer, sem `-rc`). Tag publicada não se move nem se apaga: correção = versão nova.

```bash
git fetch origin --tags
ULTIMA=$(git tag -l 'v[0-9]*' --sort=-v:refname | head -1)   # pode ser uma rc (ex.: v0.11.0-rc.2)
BASE=${ULTIMA%%-*}                                           # v0.11.0
```

- Se `BASE` ainda **não** existe como tag final (só houve rc dela): a release é `BASE` (promove a rc).
- Se já existe: `patch` (padrão) soma 1 em Z; `minor` soma 1 em Y e zera Z.
- Sem nenhuma tag: `v0.1.0`.

## 1. Pré-condições da plataforma

- `gh api repos/{owner}/{repo}/environments/prod --jq .name` responde `prod`, e
  `gh variable list --env prod` tem `ZALK_HOST`, `ZALK_USER`, `ZALK_KNOWN_HOSTS` (e
  `gh secret list --env prod` tem `ZALK_SSH_KEY`). Faltou algo: pare e diga que é pedido ao
  administrador da ZalkVPS — nunca mexa no repositório ZalkVPS.
- `.env.prod` existe (senão `scripts/segredos.sh pull`). Confira **só os nomes, nunca os valores**:
  `APP_KEY`, `RESEND_API_KEY` e `RFB_CNPJA_API_KEY` preenchidos; **nenhum** `APP_URL`, `APP_VERSION`
  ou `DB_*` (vêm do `.env.zalk` e da imagem — no `.env` eles sobrescreveriam a plataforma).

  ```bash
  for v in APP_KEY RESEND_API_KEY RFB_CNPJA_API_KEY; do grep -qE "^$v=.+" .env.prod || echo "FALTA $v"; done
  grep -oE '^(APP_URL|APP_VERSION|DB_[A-Z_]+)=' .env.prod
  ```

## 2. Árvore limpa e em dia com a origin

1. `git status --short` e `git branch --show-current`: tem de estar na `main`; em outra branch,
   pergunte antes de seguir.
2. Mudanças não commitadas → commite-as. Outras sessões podem estar editando o diretório: nunca
   `git add -A`; leia o diff de cada arquivo, agrupe por assunto e adicione caminho a caminho, com
   mensagem em português no estilo do `git log` (Conventional Commits). Migration vai num commit só
   dela. O que parecer trabalho em andamento de outra sessão: pergunte se entra; o que ficar de fora
   não vai na tag.
3. `.env.prod` ou `app/.env` alterado e não enviado (`scripts/segredos.sh status`) →
   `scripts/segredos.sh push` e commit de `secrets/` (o hook de pre-commit confere).
4. `git fetch origin --tags`. Atrás da `origin/main` → `git pull --rebase` (conflito: pare e
   pergunte). À frente → `git push origin main` (o pre-push roda Pint, Larastan, Pest e Pennant;
   precisa do stack local de pé — `./up.sh`). A tag só nasce num commit que já está na `origin/main`.

## 3. CI de main verde (o portão)

O `zalk-deploy.yml` não testa: ele confia que o SHA da tag já passou pelo `main.yml` (suíte completa
do `pr.yml`, com cobertura ≥80/98 e Dusk). Antes da tag:

```bash
SHA=$(git rev-parse HEAD)
gh run list --workflow main.yml --commit "$SHA" --json databaseId,status,conclusion
```

- Em andamento → `gh run watch <id> --exit-status --interval 30` em segundo plano
  (`run_in_background`). Não consulte o GitHub em laço.
- Sem execução para o SHA → `gh workflow run main.yml --ref main` e acompanhe.
- Falhou → `gh run view <id> --log-failed | tail -80`, mostre o trecho relevante e pergunte
  **"eu corrijo"** ou **"paro para você corrigir"**. Corrigindo: corrija, commite (passo 2), push e
  espere o novo `main.yml`. Parando: encerre sem tag.

## 4. Impacto da troca

Publicar recria `web`, `worker` e `scheduler`. Jobs em curso na fila (`pdf`, `emails`, `lgpd`) são
reprocessados pelo `--tries=3`; não há carga longa a proteger. Migrations rodam antes do `up`
(serviço `migrate`, `profiles: [predeploy]`); se a release tiver migration, confira que ela é
compatível com a versão anterior — o `zalk-deploy --rollback` volta o código, nunca o banco. Migration
destrutiva (drop/rename de coluna em uso): avise em uma linha e pergunte se publica.

## 5. Segredos, tag e push

1. `gh secret set APP_ENV_FILE --env prod --repo xandroalmeida/defonline < .env.prod`
   (nunca mostre valores).
2. Mostre a versão e o resumo dos commits desde a última tag final (`git log --oneline <última>..HEAD`;
   sem tag final, desde a última rc).
3. `git tag -a vX.Y.Z -m "<resumo em uma linha>" && git push origin vX.Y.Z`.

## 6. Acompanhar o workflow

1. Ache a execução da tag: `gh run list --workflow zalk-deploy.yml --event push --limit 5 --json databaseId,headBranch,status`
   e pegue a de `headBranch == vX.Y.Z` (pode levar alguns segundos).
2. `gh run watch <id> --exit-status --interval 30` em segundo plano (limite de 60 min). Os jobs são
   `build-and-push` → `deploy` → `smoke` (curl `/health` + `/ready` e Dusk `--group=smoke`, read-only)
   → `notify`.
3. Falha → `gh run view <id> --log-failed | tail -80`, diga o job e a causa provável e pergunte
   **"eu corrijo"** ou **"você corrige"**. Corrigindo: commit, CI verde (passo 3), push e **versão
   nova** (nunca reenvie a mesma tag). Falha só de infraestrutura (SSH, GHCR, `zalk-check`,
   `zalk-deploy`) sem mudança de código: sugira `gh run rerun <id> --failed`. Se o `zalk-deploy`
   falhou no `up`, a versão anterior pode ter caído: o rollback é `zalk-deploy --rollback` como
   `defonline-prod` no servidor — diga o comando ao usuário, não o execute sem acesso explícito.

## 7. Validar a produção

Todas precisam passar (repita por até 2 min; o `up --wait` já esperou o healthcheck):

- `curl -s https://defonline.zalk.com.br/health` tem `"status":"ok"` e `"version":"vX.Y.Z"`;
- `curl -s https://defonline.zalk.com.br/ready` tem `"status":"ok"` (banco e dependências);
- `curl -s -o /dev/null -w '%{http_code}' https://defonline.zalk.com.br/` = 200 (landing no ar);
- `curl -sI https://defonline.zalk.com.br/ | grep -i strict-transport-security` (cabeçalhos da
  aplicação — o Caddy da ZalkVPS não os injeta).

Nunca submeta formulário nem escreva no banco de produção para validar (smoke é read-only).

## Fim

Mensagem curta: versão e commit publicados, link da execução (`gh run view <id> --json url -q .url`),
resultado das validações e pendências. Atualize `defonline-docs/project-state/index.json` só se a
release fechar estórias em `in_review` (e mencione no fim).
