# Runbook — Backup off-site no Cloudflare R2

**Escopo:** criar os buckets e os tokens da R2 (um par por ambiente), gravá-los no
Ansible Vault, aplicar o playbook e validar o primeiro backup e o primeiro restore.

**Referências:** ADR-005 §4.2 (política de backup), IDR-012 (por que R2 e não B2),
`infra/ansible/playbooks/backup.yml`, `infra/ansible/playbooks/restore.yml`.

**Quem executa:** PO (passos 1–5 e 7, no dashboard da Cloudflare e no vault) +
agente/dev (passos 6 e 8).

---

## Modelo de isolamento

Um bucket e um token **por ambiente**, sem cruzamento:

| Ambiente | Bucket | Token | Onde vive a credencial |
|---|---|---|---|
| Homologação | `defonline-backup-homolog` | `defonline-backup-homolog` | `inventories/homolog/group_vars/all/vault.yml` |
| Produção | `defonline-backups-producao` | `defonline-backup-producao` | `inventories/producao/...` (inventário ainda não existe — ver passo 5) |

O token de homologação é escopado **apenas** no bucket de homologação. Se ele vazar
(VPS comprometida, `.config/rclone/rclone.conf` lido), o atacante não alcança nem
lista os backups de produção. O contrário também vale.

O **Account ID é o mesmo** para os dois — ele identifica a conta Cloudflare, não o
bucket, e compõe o endpoint S3. Não é secreto por si só, mas é gravado no vault
junto com as chaves porque endpoint + chave = acesso.

---

## 1. Habilitar a R2 na conta

Dashboard Cloudflare → **R2 Object Storage** no menu lateral → **Enable R2**.

A Cloudflare exige um **método de pagamento cadastrado** mesmo para o free tier.
O free tier cobre 10 GB-mês de armazenamento, 1M operações classe A (escrita) e
10M classe B (leitura) por mês — e **egress zero, sempre**. O volume projetado do
DEFOnline (um dump/dia + 12 mensais, na ordem de MBs) cabe inteiro no free tier;
a expectativa de custo é **R$ 0/mês** nos dois ambientes durante o MVP.

## 2. Anotar o Account ID

Ainda na home da R2, no painel da direita (**Account details**), copie o
**Account ID** — 32 caracteres hexadecimais.

É ele que forma o endpoint usado pelo rclone:

```
https://<ACCOUNT_ID>.r2.cloudflarestorage.com
```

## 3. Criar os dois buckets

R2 → **Create bucket**, uma vez para cada:

| Campo | Homologação | Produção |
|---|---|---|
| Nome | `defonline-backup-homolog` | `defonline-backups-producao` |
| Location | **Automatic** (ou hint `ENAM`) | **Automatic** (ou hint `ENAM`) |
| Default storage class | **Standard** | **Standard** |

Notas:

- **Não existe região no Brasil na R2.** Isso é irrelevante para a LGPD neste
  desenho: o que sobe é um `pg_dump` cifrado com GPG AES-256 cuja passphrase só o
  administrador possui — a Cloudflare armazena bytes opacos. O banco operacional
  continua 100% na VPS BR. Análise completa em ADR-005 §4.2.
- **Standard, não Infrequent Access.** O IA cobra taxa de recuperação e tem
  mínimo de 30 dias por objeto; para um dump/dia dentro do free tier ele não
  economiza nada e complica o restore.
- **Deixe o bucket privado** (é o padrão): nunca conecte domínio público nem
  habilite acesso público a esses buckets.

## 4. Criar um token por ambiente

R2 → **API** (menu lateral, dentro de R2) → **Manage API tokens** →
**Create Account API token**.

Preencha, para homologação:

| Campo | Valor |
|---|---|
| Token name | `defonline-backup-homolog` |
| Permissions | **Object Read & Write** |
| Specify bucket(s) | **Apply to specific buckets only** → `defonline-backup-homolog` |
| TTL | `Forever` (ou 1 ano, se preferir rotação obrigatória) |
| Client IP Address Filtering | *(opcional)* o IP do host de homologação |

Sobre as escolhas:

- **`Object Read & Write` e não `Admin Read & Write`.** O necessário é
  `PutObject` (upload), `GetObject` (restore), `DeleteObject` (retenção),
  `ListBucket` e `CopyObject` (cópia mensal server-side) — tudo coberto. O nível
  Admin adicionaria criar/apagar **buckets**, que a rotina nunca faz e que
  transformaria um vazamento de token em perda total dos backups.
- Como o token **não** pode fazer operações de bucket, o `rclone.conf` renderizado
  já traz `no_check_bucket = true`. Sem isso o rclone tentaria um HeadBucket antes
  do primeiro upload e levaria 403 mesmo com o bucket existindo.
- **IP filtering** endurece bem, mas prenda-o só ao IP da VPS se aceitar que
  qualquer `rclone` rodado do seu laptop com esse token vai falhar — e lembre de
  atualizar se a VPS trocar de IP.

Ao criar, a tela mostra **uma única vez**:

- `Token value` — usado pela API REST nativa da R2. **Não usamos.**
- **`Access Key ID`** ← este
- **`Secret Access Key`** ← e este
- O endpoint `https://<account_id>.r2.cloudflarestorage.com`

Copie os dois valores antes de fechar. Se perder, não há como reexibir: apague o
token e crie outro.

Repita tudo para produção, trocando nome do token e bucket alvo pelos da coluna
de produção da tabela do topo.

## 5. Gravar as credenciais

**Homologação** — no vault já existente:

```bash
cd infra/ansible
ansible-vault edit inventories/homolog/group_vars/all/vault.yml \
  --vault-password-file ~/.ansible-vault-pass-homolog
```

Substitua as três chaves:

```yaml
vault_r2_account_id: "<account id do passo 2>"
vault_r2_access_key_id: "<access key id do passo 4>"
vault_r2_secret_access_key: "<secret access key do passo 4>"
```

(As chaves antigas `vault_b2_app_key_id` / `vault_b2_app_key` podem ser removidas —
estavam vazias, a integração B2 nunca chegou a rodar.)

**Produção** — o inventário `inventories/producao/` ainda não existe (produção só
sai do papel no gatilho de promoção, ADR-005 §6). Até lá, guarde o par
Access Key ID + Secret do token de produção **no gerenciador de senhas**, junto da
passphrase GPG. Nada de commitar em texto claro nem deixar só no histórico do chat.

> Alternativa igualmente válida: criar **só o bucket** de produção agora e deixar
> o token para quando o inventário existir. Tokens R2 se criam em 30 segundos e o
> bucket é o que garante o nome reservado.

## 6. Aplicar na VPS

```bash
cd infra/ansible
ansible-playbook playbooks/backup.yml \
  --vault-password-file ~/.ansible-vault-pass-homolog \
  --private-key ../../secrets/deploy-homolog.key \
  --ssh-common-args="-o UserKnownHostsFile=$PWD/../../secrets/deploy-homolog.known_hosts"
```

(O inventário não declara `ansible_ssh_private_key_file` — no CI a chave vai para
`~/.ssh/id_ed25519`, então rodando da máquina do dev é preciso apontá-la à mão,
senão dá `Permission denied (publickey)`.)

O playbook instala `rclone`/`gnupg`, renderiza `~/.config/rclone/rclone.conf`
(modo 0600), instala `scripts/pg-backup.sh`, grava a passphrase GPG e registra o
cron das 03:00 BRT. A task **"Validar acesso ao bucket R2"** roda um
`rclone lsjson` e falha ali mesmo se credencial, endpoint ou nome de bucket
estiverem errados — em vez de você descobrir num log às 03:00.

## 7. Validar o primeiro backup à mão

Sem esperar o cron:

```bash
ssh -i secrets/deploy-homolog.key -o UserKnownHostsFile=secrets/deploy-homolog.known_hosts \
  deploy@<HOST_HOMOLOG> '/opt/defonline/scripts/pg-backup.sh'
```

Esperado: linhas `pg_dump → gzip → GPG`, tamanho do dump, `Upload → r2:...` e
`Backup ok.` Depois, confira o que chegou:

```bash
ssh ... deploy@<HOST_HOMOLOG> 'rclone tree r2:defonline-backup-homolog/postgres/homolog'
```

Layout esperado no bucket:

```
postgres/<ambiente>/daily/YYYY/MM/dump-YYYYMMDD-HHMM.sql.gz.gpg
postgres/<ambiente>/monthly/YYYY/dump-YYYYMM.sql.gz.gpg     # só nos dias 01
```

Os dois prefixos existem porque a política da ADR-005 (30 dias rolantes **+** 12
cópias mensais) é impossível num prefixo único: qualquer limpeza por idade
apagaria as mensais junto com as diárias. A retenção fica no próprio
`pg-backup.sh`, escopada por prefixo — versionada no repo, e não numa regra de
dashboard que ninguém revisa.

## 8. Exercitar o restore (trimestral)

O backup só vale o que o restore prova. Exercício em homologação:

```bash
cd infra/ansible
ansible-playbook playbooks/restore.yml -e dump_date=YYYYMMDD-HHMM \
  --vault-password-file ~/.ansible-vault-pass-homolog
# mensal: -e dump_month=YYYYMM
```

⚠ O playbook **dropa** os objetos do banco alvo antes de restaurar e pede
confirmação digitada (`RESTAURAR`). Nunca aponte para produção fora de um
incidente declarado.

---

## Armadilha conhecida — versão do rclone

**Não instale o rclone pelo apt.** O Ubuntu 26.04 empacota a `1.60.1-DEV` (2022),
que quebra contra a R2 do pior jeito possível — silenciosamente:

```
HEAD  /bucket/objeto                       → 404   (ainda não existe)
PUT   /bucket/objeto                       → 200   upload OK, devolve x-amz-version-id
HEAD  /bucket/objeto?versionId=<id>        → 501   Not Implemented
```

A R2 não implementa versionamento de objeto, e a 1.60 insiste em reler o objeto
pelo `versionId` que a própria R2 devolveu. O upload **já foi**; só a verificação
falha. Resultado: `rclone copyto` erra na 1ª tentativa, acerta na 2ª, e o script
termina com `Backup ok.` — o erro vira ruído no `backup.log`. Com as 3 tentativas
caindo juntas, seria uma falha muda às 03:00.

A `1.75.0` sobe limpo, sem nenhum workaround. O `backup.yml` instala o `.deb`
oficial da versão fixada em `rclone_version` (group_vars) e pula a task se já
estiver na versão certa. Para subir de versão: mude `rclone_version` e rode o
playbook.

Para diagnosticar problemas parecidos no futuro (o `--dump headers` do rclone
**não** mostra o header `Authorization`, então é seguro):

```bash
rclone copyto /tmp/probe.bin r2:<bucket>/_probe/probe.bin -vv --retries 1 --dump headers \
  | grep -oE "(GET|PUT|HEAD|POST|DELETE) [^ ]+ HTTP/1\.1|HTTP/1\.1 [0-9]{3}[A-Za-z ]*"
```

## Lifecycle rules (opcional)

A retenção primária é do script. Se quiser uma rede de segurança que funcione
mesmo com a VPS morta, configure lifecycle no bucket com prazos **folgados**, para
não competir com o script:

| Prefixo | Expiração sugerida |
|---|---|
| `postgres/homolog/daily/` | 45 dias |
| `postgres/homolog/monthly/` | 800 dias |

Bucket → **Settings** → **Object lifecycle rules** → Add rule, com o prefixo no
campo de escopo.

## Rotação e revogação

- **Rotacionar:** crie um token novo com o mesmo escopo, atualize o vault, rode o
  `backup.yml` (a task de validação prova o token novo), só então apague o antigo
  em R2 → API → Manage API tokens.
- **Suspeita de vazamento:** apague o token na hora — o `rclone` da VPS passa a
  levar 403 e o backup falha ruidosamente, que é o comportamento desejado. Depois
  siga o fluxo de rotação. Os dumps já no bucket continuam cifrados: o token dá
  acesso aos **bytes**, não ao conteúdo, que depende da passphrase GPG.
- **A passphrase GPG é o ponto de falha real.** Sem ela os backups são
  irrecuperáveis, para você e para a Cloudflare. Cópia offline obrigatória
  (`scripts/gen-backup-gpg-key.sh` explica o protocolo).

## Alertas no Telegram

Canal: bot `defonlineHom_bot`, grupo **DefOnline Alertas**. Credencial em
`~/.backup-telegram` na VPS (0600), renderizada pelo playbook a partir de
`vault_telegram_bot_token` / `vault_telegram_chat_id_homolog`.

O alerta chama a Bot API por HTTP direto, sem passar pelo Laravel: quando o
backup falha é plausível que o container `web` também esteja quebrado, e o canal
de aviso não pode depender do que ele monitora.

| Quando | Mensagem | Origem |
|---|---|---|
| Backup sai com código ≠ 0 | 🚨 [P1] — etapa, código de saída e últimas 12 linhas do log | `pg-backup.sh` |
| Retenção falhou, ou execução anterior ainda rodando | ⚠️ [P2] — lista de ressalvas | `pg-backup.sh` |
| Nenhum dump em `daily/`, ou mais velho que `backup_max_idade_horas` (26h) | 🚨 [P1] | `pg-backup-verify.sh` |
| Dump na R2 não baixa, não decifra, ou o `pg_restore` não lê | 🚨 [P1] | `pg-backup-verify.sh` |
| Segunda-feira, tudo certo | ✅ resumo semanal | `pg-backup-verify.sh` |

**Por que dois scripts.** Nenhum processo consegue alertar sobre a própria
ausência: se o cron for removido, o `docker compose` morrer antes da primeira
linha, ou a VPS for reprovisionada sem o `backup.yml`, o `pg-backup.sh`
simplesmente não roda — e no desenho anterior silêncio era indistinguível de
sucesso. O `pg-backup-verify.sh` (cron 04:30) cobra a existência de um dump
recente **na R2**, não no disco local, e ainda baixa, decifra e passa pelo
`pg_restore --list`. É um mini-drill de restore diário — barato porque a R2 não
cobra egress — e é o que separa "o arquivo chegou" de "o arquivo serve".

**O resumo semanal não é enfeite.** Silêncio absoluto é indistinguível de canal
quebrado — token revogado, bot removido do grupo, `curl` desinstalado. Uma
mensagem por semana prova que o caminho de alerta continua de pé. Para desligar:
`backup_verify_resumo_semanal: false` em group_vars.

**Para testar os alertas** sem esperar uma falha real (injeções usadas na
validação de 2026-08-04, todas reversíveis):

```bash
# falha do backup: esconde a passphrase e restaura ao sair
ssh deploy@<vps> 'trap "mv -f ~/.backup-gpg-pass.bak ~/.backup-gpg-pass" EXIT; \
  mv ~/.backup-gpg-pass ~/.backup-gpg-pass.bak; /opt/defonline/scripts/pg-backup.sh'

# backup velho demais: roda uma cópia com o limite de idade negativo
ssh deploy@<vps> 'sed "s/^MAX_IDADE_H=.*/MAX_IDADE_H=\"-1\"/" \
  /opt/defonline/scripts/pg-backup-verify.sh > /tmp/v.sh && chmod +x /tmp/v.sh && /tmp/v.sh; rm -f /tmp/v.sh'
```

## Pendências conhecidas

- **VPS morta continua invisível.** Backup e verificação rodam *na própria VPS*.
  Se ela cair, ninguém alerta. Um dead man's switch de verdade precisa de
  observador externo: o UptimeRobot da ADR-004 tem monitor tipo *heartbeat* no
  plano free — bastaria o `pg-backup-verify.sh` pingar a URL dele ao terminar
  bem, e o UptimeRobot avisa quando o ping não chega. Depende de conta fora do
  repo, por isso não está implementado.
- **Produção.** Todos os playbooks declaram `hosts: homolog`. Promover produção
  exige, além do inventário novo e do par bucket+token, generalizar esse alvo.
