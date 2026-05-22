# RUNBOOK — provisionar homologação do zero (STORY-007 Phase 3)

> **Audiência:** PO + agente programador.
> **Pré-requisito:** Phase 2 mergeada (CI verde, imagens GHCR sendo publicadas).
> **Resultado esperado ao final:** `https://defonline.xandrix.com.br` ao vivo, /health 200, smoke E2E verde, deploy automático em tag `vX.Y.Z-rc.N`.
>
> **Tempo estimado:** 60-90 min, sendo ~30 min de espera (propagação DNS + Let's Encrypt).

---

## 0. Checklist do que você precisa em mãos antes de começar

- [ ] Conta na VPS BR (Magalu/Locaweb/Hostinger/DO/...) com cartão.
- [ ] Acesso ao painel do DNS DigitalOcean (`xandrix.com.br`).
- [ ] Conta Backblaze B2 (já tem, conforme alinhado).
- [ ] Telegram instalado (no celular).
- [ ] Acesso de admin no repositório GitHub `xandroalmeida/defonline` (para criar Secrets).
- [ ] `ssh-keygen`, `openssl`, `ansible-playbook` instalados no host (já estão).

---

## 1. Provisionar a VPS BR (~15 min)

Perfil técnico mínimo (ADR-005 §1.1):
- Ubuntu 24.04 LTS
- 4 GB RAM, 2 vCPU, 80 GB SSD
- IPv4 público
- Localização: BR (preferência SP)

1. Contrate a VPS no provedor de sua escolha. Hoje (~ R$ 80-120/mês). Opções pré-validadas:
   - **Magalu Cloud** (https://magalu.cloud)
   - **Locaweb VPS** (https://locaweb.com.br)
   - **Hostinger BR VPS** (https://hostinger.com.br)
2. Anote: `VPS_IP=...` (IPv4 público).
3. Quando a VPS estiver "running" e o painel mostrar a senha root inicial (ou já tiver injetado sua chave SSH), teste:
   ```bash
   ssh root@$VPS_IP
   # ou, se foi via senha:
   ssh root@$VPS_IP   # cola a senha do painel
   ```

---

## 2. Configurar DNS no DigitalOcean (~5 min + ~10 min de propagação)

1. Acesse https://cloud.digitalocean.com/networking/domains/xandrix.com.br.
2. Crie um **A record**:
   - Hostname: `defonline`
   - Value: `<VPS_IP>` (do passo 1)
   - TTL: `300` (5 min — propagação rápida durante setup).
3. Salve. Teste em ~3-5 min:
   ```bash
   host defonline.xandrix.com.br
   # deve resolver para $VPS_IP
   ```

---

## 3. Gerar chave SSH dedicada ao deploy (~2 min)

```bash
cd ~/Projetos/DEFOnline
./scripts/gen-deploy-ssh-key.sh
```

Saída em `secrets/deploy-homolog.key` (privada) e `secrets/deploy-homolog.key.pub` (pública).

---

## 4. Subir a chave pública e known_hosts (~5 min)

```bash
# Sobe pública para a VPS (vai pedir senha root uma única vez)
ssh-copy-id -i secrets/deploy-homolog.key.pub root@$VPS_IP

# Captura known_hosts para o GitHub Actions confiar no host
ssh-keyscan -p 22 -H $VPS_IP > secrets/deploy-homolog.known_hosts
```

> O playbook `bootstrap.yml` ainda vai criar o usuário `deploy` e mover a chave para `/home/deploy/.ssh/authorized_keys`. Esse upload inicial via root é só para o primeiro contato; após o primeiro `ansible-playbook site.yml`, root login fica desabilitado.

---

## 5. Gerar passphrase GPG para backup (~30 s)

```bash
./scripts/gen-backup-gpg-key.sh
# Saída: secrets/backup-gpg-passphrase
```

**⚠ Guarde esta passphrase em cofre externo** (1Password / Bitwarden / pen drive físico). Sem ela, backups B2 são **irrecuperáveis** — Backblaze só vê bytes opacos.

---

## 6. Criar bucket Backblaze B2 (~5 min)

1. https://secure.backblaze.com → **Buckets** → **Create a Bucket**
   - Name: `defonline-backups-homolog`
   - Files in Bucket: **Private**
   - Encryption: Disable Server-Side Encryption (estamos cifrando com GPG client-side).
   - Default file retention: opcionalmente `30 days` (defesa em profundidade).
2. **App Keys** → **Add a New Application Key**
   - Name: `defonline-backup-homolog`
   - Allow access to Bucket: `defonline-backups-homolog`
   - Type of Access: `Read and Write`
   - Allow List All Bucket Names: **NO**
3. Copie `keyID` e `applicationKey` (aparece **uma única vez**).

---

## 7. Criar bot Telegram + chat de homol (~10 min)

1. Abra Telegram no celular.
2. Inicie chat com `@BotFather`.
3. Digite `/newbot`. Nome: `DEFOnline Alertas`. Username: `defonline_alertas_bot` (precisa ser único — adapte).
4. BotFather devolve um token tipo `0000000000:AAAAAAAAAAAAAAAAA`. Guarde — este é o `TELEGRAM_BOT_TOKEN`.
5. No Telegram, crie um **grupo** chamado "DEFOnline Homol". Adicione o bot como membro.
6. Para descobrir o `chat_id` do grupo, mande qualquer mensagem no grupo e depois acesse:
   ```
   https://api.telegram.org/bot<TOKEN>/getUpdates
   ```
   Procure por `"chat":{"id":-1000...,"title":"DEFOnline Homol"}`. O id (com sinal negativo) é o `TELEGRAM_CHAT_ID_HOMOLOG`.
7. Mande um teste:
   ```bash
   curl -X POST "https://api.telegram.org/bot<TOKEN>/sendMessage" \
        -d "chat_id=<CHAT_ID>" -d "text=hello DEFOnline"
   ```

> **⚠ Lição aprendida (1ª execução):** Por padrão o BotFather habilita
> "Privacy mode" no bot — bot só recebe mensagens em grupo se for **mencionado**
> (`@nomebot ...`) ou se a mensagem for um comando (`/...`). Se o `getUpdates`
> mostrar só o chat privado, peça uma das duas opções:
> - mande `@<seubot> ping` no grupo (com `@` real, selecionado no autocomplete);
> - **OU** em `@BotFather` → `/setprivacy` → `Disable` para o bot ver tudo.

---

## 8. Preencher o Ansible Vault (~5 min)

```bash
cd ~/Projetos/DEFOnline/infra/ansible
cp inventories/homolog/group_vars/all/vault.yml.example inventories/homolog/group_vars/all/vault.yml
```

> **⚠ Convenção:** vault fica em `group_vars/all/vault.yml` (não em
> `group_vars/vault.yml`). Ansible só carrega arquivos cujo path corresponde
> a um nome de grupo; "vault" não é grupo, "all" é. Estrutura em pasta
> permite separar `vars.yml` (público) de `vault.yml` (cifrado).

Edite `inventories/homolog/group_vars/all/vault.yml` substituindo todos os `REPLACE_ME`:

- `vault_db_app_password` / `vault_db_backup_password` / `vault_db_superuser_password`: gere com `openssl rand -base64 32` para cada.
- `vault_b2_app_key_id` / `vault_b2_app_key`: passos 6.3.
- `vault_backup_gpg_passphrase`: `cat secrets/backup-gpg-passphrase`.
- `vault_telegram_bot_token` / `vault_telegram_chat_id_homolog`: passos 7.4 e 7.6.
- `vault_ghcr_pull_token`: deixe vazio se o repo for público (não é o caso hoje — repo é privado, então vai precisar de PAT com `read:packages`).

Cifre o vault:

```bash
# Crie uma senha forte e SALVE em ~/.ansible-vault-pass-homolog (chmod 600)
openssl rand -base64 32 > ~/.ansible-vault-pass-homolog
chmod 600 ~/.ansible-vault-pass-homolog

ansible-vault encrypt inventories/homolog/group_vars/all/vault.yml \
    --vault-password-file ~/.ansible-vault-pass-homolog
```

A partir daqui o `vault.yml` está cifrado — pode comitar. **NUNCA comite a senha do vault.**

> **⚠ Lição aprendida (1ª execução):** o `.gitignore` ignora `vault.yml` (em
> qualquer pasta) por segurança. Quando estiver cifrado, force o add com
> `git add -f infra/ansible/inventories/homolog/group_vars/all/vault.yml`.

---

## 9. Atualizar inventário com IP da VPS (~30 s)

```bash
sed -i.bak 's|__VPS_IP__|<VPS_IP>|' infra/ansible/inventories/homolog/hosts.yml
rm infra/ansible/inventories/homolog/hosts.yml.bak
```

---

## 10. Primeira execução do `site.yml` (provisionamento completo, ~15 min)

```bash
cd ~/Projetos/DEFOnline/infra/ansible

# Antes: instalar coleções no path declarado em ansible.cfg (collections_path = collections).
ansible-galaxy collection install -r requirements.yml -p collections

# Primeira execução: conecta como root para criar usuário deploy.
# `-e ansible_user=root` força root (sobrescreve `ansible_user: deploy` do inventário).
# `-e initial_remote_user=root` é redundante mas explícito.
# Paths devem ser ABSOLUTOS — `lookup('file', ...)` é relativo ao playbook, não ao CWD.
ansible-playbook -i inventories/homolog/hosts.yml playbooks/site.yml \
    --vault-password-file ~/.ansible-vault-pass-homolog \
    -e ansible_user=root \
    -e initial_remote_user=root \
    -e ansible_ssh_private_key_file="$(realpath ../../secrets/deploy-homolog.key)" \
    -e deploy_authorized_key_path="$(realpath ../../secrets/deploy-homolog.key.pub)"
```

O playbook orquestra (em ordem):
1. `bootstrap.yml` — APT update, cria deploy, desliga root SSH, UFW (22+80+443), fail2ban, unattended-upgrades.
2. `docker.yml` — Docker Engine + Compose plugin via repo oficial.
3. `app.yml` — `.env` + `docker-compose.yml` + `Caddyfile` gerados de templates Jinja, GHCR login, `docker compose pull`, `up -d`, `php artisan migrate`, smoke via URL pública.
4. `backup.yml` — rclone + GPG passphrase + cron diário 03:00 BRT.

Se algo falhar, o output do Ansible aponta o passo. Resolva e re-execute (idempotente).

> **⚠ Lições aprendidas (1ª execução):**
> - **Depois do bootstrap, root SSH fica desligado.** Execuções seguintes
>   conectam como `deploy` (chave). Não precisa mais de `-e ansible_user=root`.
> - **Sudo do `deploy` é restritivo** (NOPASSWD apenas para docker/systemctl/ufw).
>   Playbooks `app.yml` e `deploy.yml` NÃO usam `become: true` no play — todas as
>   tasks operam em `/opt/defonline` (de propriedade do deploy) ou via docker.
> - **Smoke usa URL pública** (`uri` module), não `docker compose exec`.
>   Durante recreate, o exec pega container antigo em transição.
> - **VPS BR pode demorar segundos** após UFW + restart sshd para portas
>   voltarem a aceitar TCP. Se SSH falhar com "Connection refused" logo após
>   o primeiro `site.yml`, aguarde ~60s e retente.

---

## 11. Validar manual de fora (~5 min)

```bash
# Aguarde ~30 s para Caddy negociar Let's Encrypt
curl -fsSL https://defonline.xandrix.com.br/health
# Deve retornar: {"status":"ok","service":"DEFOnline","version":"...","env":"staging"}

curl -fsSL https://defonline.xandrix.com.br/ready
# Deve retornar checks de db/cache/queue todos `ok`

# Abra no browser:
open https://defonline.xandrix.com.br
# Deve mostrar a página HelloWorld com status OK
```

---

## 12. Adicionar Secrets no GitHub (~5 min)

`Settings → Secrets and variables → Actions → New repository secret` (ou por Environment `homolog`):

| Nome | Valor |
|---|---|
| `DEPLOY_SSH_KEY` | `cat secrets/deploy-homolog.key` (privada inteira, com BEGIN/END) |
| `DEPLOY_SSH_KNOWN_HOSTS` | `cat secrets/deploy-homolog.known_hosts` |
| `ANSIBLE_VAULT_PASS` | `cat ~/.ansible-vault-pass-homolog` |
| `TELEGRAM_BOT_TOKEN` | token do BotFather (passo 7.4) |
| `TELEGRAM_CHAT_ID_HOMOLOG` | id do chat (passo 7.6) |

---

## 13. Disparar primeira release-candidate (~3 min)

```bash
# No host (ou via GitHub UI: Actions → bump-rc → Run workflow → patch)
git checkout main
git pull
git tag v0.1.0-rc.1 -m "primeira homol publica"
git push --tags
```

GHA dispara `release-homolog.yml`:
1. Re-roda PR pipeline contra a tag.
2. Build da imagem com `APP_VERSION=v0.1.0-rc.1`.
3. Push para `ghcr.io/xandroalmeida/defonline/app:v0.1.0-rc.1`.
4. `ansible-playbook deploy.yml` via SSH na VPS, pull da imagem nova, `up -d`, `migrate --force`.
5. Smoke test HTTP + Dusk contra `https://defonline.xandrix.com.br`.
6. Notifica Telegram.

---

## 14. Verificar Telegram e logs

- Chat "DEFOnline Homol" deve receber: `✅ DEFOnline homol: v0.1.0-rc.1 — deploy=success smoke=success`.
- `ssh deploy@$VPS_IP` → `cd /opt/defonline` → `docker compose logs -f web worker scheduler`.
- Acesso à página: `https://defonline.xandrix.com.br` mostrando "hello DEFOnline" + versão `v0.1.0-rc.1`.

---

## 15. Confirmar primeiro backup (próxima 03:00 BRT)

Ou force agora para testar:

```bash
ssh deploy@$VPS_IP
/opt/defonline/scripts/pg-backup.sh
# Confira no painel B2: defonline-backups-homolog/postgres/homolog/<ano>/<mês>/dump-*.sql.gz.gpg
```

---

## Rollback se algo der errado

```bash
# Re-publicar tag anterior:
# GitHub → Actions → deploy-by-tag → Run workflow
#   tag: v0.0.x-rc.N (anterior funcional)
#   environment: homolog
```

Ou direto via Ansible:

```bash
cd infra/ansible
ansible-playbook -i inventories/homolog/hosts.yml playbooks/deploy.yml \
    --vault-password-file ~/.ansible-vault-pass-homolog \
    -e image_tag=v0.0.x-rc.N
```

A imagem antiga já está no GHCR (imagens são imutáveis). Downtime estimado: < 2 min.

---

## Re-provisionar do zero

Em caso de incidente catastrófico na VPS:

```bash
# 1. Contrate nova VPS (passo 1).
# 2. Atualize hosts.yml com novo IP (passo 9).
# 3. Re-rode site.yml (passo 10).
# 4. Restaure último dump:
ansible-playbook -i inventories/homolog/hosts.yml playbooks/restore.yml \
    --vault-password-file ~/.ansible-vault-pass-homolog \
    -e dump_date=YYYYMMDD-HHMM
```

RTO esperado: ~30-45 min. RPO: 24h (último backup diário).
